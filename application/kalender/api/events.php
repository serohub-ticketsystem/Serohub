<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];

if (!$start || !$end || !is_array($filters)) {
    echo json_encode([]);
    exit;
}

$events = [];

$filterCompanyId = !empty($filters['company_id']) ? (int) $filters['company_id'] : 0;

// Prüfen, ob End-Spalten für Tickets existieren (Migration 062)
$hasTicketEndColumns = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'geplant_datum_ende'");
    $hasTicketEndColumns = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    // Spalten fehlen, z. B. Migration noch nicht ausgeführt
}

$selTicketEnd = $hasTicketEndColumns ? ', t.geplant_datum_ende, t.faellig_datum_ende' : '';

/**
 * Entschlüsselt Firma/Kunde (und Adressfelder für Kartenlinks) in Ticket-Zeilen.
 */
function calendarDecryptTicketContactFields(array &$row): void
{
    $fields = [
        'customer_name', 'company_name',
        'customer_adresse', 'customer_plz', 'customer_ort',
        'device_beschreibung',
    ];
    foreach ($fields as $field) {
        if (array_key_exists($field, $row) && ($row[$field] === null || $row[$field] === '' || is_string($row[$field]))) {
            $row[$field] = decrypt_from_db($row[$field]);
        }
    }
}

function calendarPlaintextLabel(?string $value): string
{
    $v = trim((string) ($value ?? ''));
    if ($v === '' || $v === DECRYPT_FAILED_PLACEHOLDER || strpos($v, ENCRYPTION_PREFIX) === 0) {
        return '';
    }
    return $v;
}

function calendarSetDeviceExtProps(array $row, array &$extProps): void
{
    if (empty($row['device_id'])) {
        return;
    }
    $extProps['deviceUrl'] = '/devices/detail.php?id=' . $row['device_id'];
    $extProps['deviceName'] = $row['device_name'] ?? 'Gerät';
    $standort = calendarPlaintextLabel($row['device_beschreibung'] ?? '');
    if ($standort !== '') {
        $extProps['deviceStandort'] = $standort;
    }
}

/**
 * Anzeigename für Ticket-Events im Kalender: Kunde (falls vorhanden), sonst Firma, sonst Ticketbetreff.
 */
function calendarTicketDisplayLabel(array $row): string
{
    if (!empty($row['customer_id'])) {
        $customerName = calendarPlaintextLabel($row['customer_name'] ?? '');
        if ($customerName !== '') {
            return $customerName;
        }
    }
    $companyName = calendarPlaintextLabel($row['company_name'] ?? '');
    if ($companyName !== '') {
        return $companyName;
    }
    $titel = calendarPlaintextLabel($row['titel'] ?? $row['ticket_titel'] ?? '');
    return $titel !== '' ? $titel : 'Ticket';
}

function calendarTicketEventTitle(array $row, ?string $suffix = null): string
{
    $label = calendarTicketDisplayLabel($row);
    $suffix = $suffix !== null ? trim($suffix) : '';
    if ($suffix === '' || $suffix === 'Geplant' || $suffix === 'Fällig') {
        return $label;
    }
    return $label . ' · ' . $suffix;
}

/**
 * Aufgaben-Fälligkeit ohne echte Uhrzeit (00:00 oder 12:00) → ganztägig im Kalender.
 */
function calendarTodoFaelligIsAllDay(?string $faelligAm): bool
{
    if ($faelligAm === null || trim($faelligAm) === '') {
        return false;
    }
    $faelligAm = trim($faelligAm);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $faelligAm)) {
        return true;
    }
    try {
        $dt = new DateTime($faelligAm);
    } catch (Exception $e) {
        return false;
    }
    $h = (int) $dt->format('H');
    $i = (int) $dt->format('i');
    $s = (int) $dt->format('s');

    return ($h === 0 && $i === 0 && $s === 0) || ($h === 12 && $i === 0 && $s === 0);
}

function calendarTodoTitle(array $row, ?string $prefix = null): string
{
    $titel = calendarPlaintextLabel(decrypt_from_db($row['titel'] ?? ''));
    if ($titel === '') {
        $titel = 'Aufgabe';
    }
    if ($prefix !== null && $prefix !== '') {
        return $prefix . $titel;
    }

    return $titel;
}

/**
 * @return array<string, mixed>
 */
function calendarBuildTodoCalendarEvent(
    array $row,
    string $eventId,
    string $assigneeName,
    bool $editable,
    string $backgroundColor,
    string $borderColor,
    ?string $titlePrefix = null,
    array $extraProps = []
): array {
    $faellig = $row['faellig_am'];
    $isAllDay = calendarTodoFaelligIsAllDay($faellig);
    $extendedProps = array_merge([
        'source' => 'todos',
        'sourceLabel' => 'Aufgabe',
        'todo_id' => (int) $row['id'],
        'user' => $assigneeName,
        'detailUrl' => '/todos/',
        'faellig_all_day' => $isAllDay,
    ], $extraProps);

    $event = [
        'id' => $eventId,
        'title' => calendarTodoTitle($row, $titlePrefix),
        'editable' => $editable,
        'backgroundColor' => $backgroundColor,
        'borderColor' => $borderColor,
        'textColor' => '#ffffff',
        'extendedProps' => $extendedProps,
    ];

    if ($isAllDay) {
        $startDay = (new DateTime($faellig))->format('Y-m-d');
        $endDay = (new DateTime($startDay))->modify('+1 day')->format('Y-m-d');
        $event['start'] = $startDay;
        $event['end'] = $endDay;
        $event['allDay'] = true;
    } else {
        $event['start'] = $faellig;
        $event['end'] = (new DateTime($faellig))->modify('+1 hour')->format('Y-m-d H:i:s');
        $event['allDay'] = false;
    }

    return $event;
}

try {
    $stmtUser = $pdo->prepare("SELECT id, rolle, vorname, nachname, company_id FROM users WHERE id = :id LIMIT 1");
    $stmtUser->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmtUser->execute();
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $isAdminOrTechniker = $currentUser && in_array($currentUser['rolle'], ['Admin', 'Techniker'], true);
    $userRole = $currentUser['rolle'] ?? '';
    $userCompanyId = $currentUser['company_id'] ?? null;

    $otherUserIds = [];
    if (!empty($filters['other_user_ids']) && is_array($filters['other_user_ids'])) {
        $otherUserIds = array_map('intval', $filters['other_user_ids']);
        $otherUserIds = array_filter($otherUserIds, function ($id) use ($userId) { return $id && $id !== $userId; });
    }

    // Nutzer ohne Admin/Techniker: nur eigene oder beobachtete Tickets anzeigen, keine anderen Aktionen
    if (!$isAdminOrTechniker) {
        $companyCondNonAdmin = $filterCompanyId ? ' AND t.company_id = :filter_company' : '';
        $stmt = $pdo->prepare("
            SELECT t.id, t.ticket_nummer, t.titel, t.status, t.geplant_datum, t.faellig_datum {$selTicketEnd}, t.zugewiesen_an, t.erstellt_von,
                   t.customer_id, t.device_id, t.company_id,
                   u.vorname, u.nachname,
                   cu.name AS customer_name, cu.adresse AS customer_adresse, cu.plz AS customer_plz, cu.ort AS customer_ort,
                   d.name AS device_name, d.beschreibung AS device_beschreibung,
                   co.name AS company_name
            FROM tickets t
            LEFT JOIN users u ON t.zugewiesen_an = u.id
            LEFT JOIN customers cu ON t.customer_id = cu.id
            LEFT JOIN devices d ON t.device_id = d.id
            LEFT JOIN companies co ON t.company_id = co.id
            WHERE (t.zugewiesen_an = :uid OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :uid2))
            AND (t.geplant_datum BETWEEN :start AND :end OR t.faellig_datum BETWEEN :start2 AND :end2)
            AND t.status != 'Geschlossen'
            {$companyCondNonAdmin}
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->bindValue(':start2', $start);
        $stmt->bindValue(':end2', $end);
        if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            calendarDecryptTicketContactFields($row);
            $assigneeName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            $extProps = [
                'source' => 'service',
                'sourceLabel' => 'Ticket',
                'ticket_id' => $row['id'],
                'ticket_nummer' => $row['ticket_nummer'],
                'titel' => $row['titel'] ?? '',
                'ticket_status' => $row['status'] ?? '',
                'user' => $assigneeName,
                'detailUrl' => '/tickets/view.php?id=' . $row['id']
            ];
            if (!empty($row['zugewiesen_an'])) {
                $extProps['zugewiesen_an'] = (int) $row['zugewiesen_an'];
            }
            if (!empty($row['customer_id'])) {
                $extProps['customerUrl'] = '/customers/detail.php?id=' . $row['customer_id'];
                $extProps['customerName'] = calendarPlaintextLabel($row['customer_name'] ?? '') ?: 'Kunde';
                $addrParts = array_filter([
                    $row['customer_adresse'] ?? '',
                    trim(($row['customer_plz'] ?? '') . ' ' . ($row['customer_ort'] ?? ''))
                ]);
                $fullAddress = implode(', ', $addrParts);
                if ($fullAddress) {
                    $extProps['mapsUrl'] = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($fullAddress);
                }
            }
            if (!empty($row['device_id'])) {
                calendarSetDeviceExtProps($row, $extProps);
            }
            $companyLabel = calendarPlaintextLabel($row['company_name'] ?? '');
            if ($companyLabel !== '') {
                $extProps['companyName'] = $companyLabel;
            }
            if (!empty($row['company_id'])) {
                $extProps['company_id'] = (int) $row['company_id'];
                $extProps['companyUrl'] = '/companies/detail.php?id=' . $row['company_id'];
            }
            if ($row['geplant_datum']) {
                $start = $row['geplant_datum'];
                $end = !empty($row['geplant_datum_ende']) ? $row['geplant_datum_ende'] : (new DateTime($start))->modify('+1 hour')->format('Y-m-d H:i:s');
                $events[] = [
                    'id' => 'ticket_plan_' . $row['id'],
                    'title' => calendarTicketDisplayLabel($row),
                    'start' => $start,
                    'end' => $end,
                    'editable' => false,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'textColor' => '#ffffff',
                    'extendedProps' => array_merge($extProps, ['sourceLabel' => 'Ticket'])
                ];
            }
            if ($row['faellig_datum'] && $row['faellig_datum'] !== $row['geplant_datum']) {
                $start = $row['faellig_datum'];
                $end = !empty($row['faellig_datum_ende']) ? $row['faellig_datum_ende'] : (new DateTime($start))->modify('+1 hour')->format('Y-m-d H:i:s');
                $faelligProps = array_merge($extProps, ['sourceLabel' => 'Ticket (Fällig)', 'isFaellig' => true]);
                $events[] = [
                    'id' => 'ticket_faellig_' . $row['id'],
                    'title' => calendarTicketDisplayLabel($row),
                    'start' => $start,
                    'end' => $end,
                    'editable' => false,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'textColor' => '#ffffff',
                    'extendedProps' => $faelligProps
                ];
            }
        }
        echo json_encode($events);
        exit;
    }

    // Mein Kalender: eigene Termine + Termine zu denen ich eingeladen wurde
    if (!empty($filters['my_calendar'])) {
        // Eigene Termine (erstellt von mir)
        $stmt = $pdo->prepare("
            SELECT e.id, e.user_id, e.title, e.description, e.meeting_link, e.invite_emails, e.start_at, e.end_at, e.all_day,
                   u.vorname, u.nachname
            FROM calendar_events e
            JOIN users u ON e.user_id = u.id
            WHERE e.user_id = :uid
            AND (e.start_at <= :end AND e.end_at >= :start)
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ownerName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            $isAllDay = (bool) $row['all_day'];
            $rowStart = $row['start_at'];
            $rowEnd = $row['end_at'];
            if ($isAllDay) {
                $rowStart = substr($rowStart, 0, 10);
                $rowEnd = substr($rowEnd, 0, 10);
            }
            $events[] = [
                'id' => 'custom_' . $row['id'],
                'title' => $row['title'],
                'start' => $rowStart,
                'end' => $rowEnd,
                'allDay' => $isAllDay,
                'editable' => true,
                'backgroundColor' => '#10b981',
                'borderColor' => '#059669',
                'textColor' => '#ffffff',
                'classNames' => ['fc-event-mein-kalender'],
                'extendedProps' => [
                    'source' => 'custom',
                    'sourceLabel' => 'Mein Termin',
                    'custom_id' => (int) $row['id'],
                    'description' => $row['description'],
                    'meeting_link' => $row['meeting_link'] ?? null,
                    'invite_emails' => $row['invite_emails'] ?? null,
                    'owner' => $ownerName,
                    'is_owner' => true,
                    'my_calendar' => true
                ]
            ];
        }
        
        // Termine zu denen ich eingeladen wurde
        $stmt = $pdo->prepare("
            SELECT e.id, e.user_id, e.title, e.description, e.meeting_link, e.invite_emails, e.start_at, e.end_at, e.all_day,
                   u.vorname, u.nachname
            FROM calendar_events e
            JOIN calendar_event_invitees i ON e.id = i.event_id
            JOIN users u ON e.user_id = u.id
            WHERE i.user_id = :uid
            AND e.user_id != :uid2
            AND (e.start_at <= :end AND e.end_at >= :start)
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ownerName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            $isAllDay = (bool) $row['all_day'];
            $rowStart = $isAllDay ? substr($row['start_at'], 0, 10) : $row['start_at'];
            $rowEnd = $isAllDay ? substr($row['end_at'], 0, 10) : $row['end_at'];
            $events[] = [
                'id' => 'custom_invited_' . $row['id'],
                'title' => $row['title'],
                'start' => $rowStart,
                'end' => $rowEnd,
                'allDay' => $isAllDay,
                'editable' => false,
                'backgroundColor' => '#06b6d4',
                'borderColor' => '#0891b2',
                'textColor' => '#ffffff',
                'classNames' => ['fc-event-invited'],
                'extendedProps' => [
                    'source' => 'custom',
                    'sourceLabel' => 'Einladung von ' . $ownerName,
                    'custom_id' => (int) $row['id'],
                    'description' => $row['description'],
                    'meeting_link' => $row['meeting_link'] ?? null,
                    'invite_emails' => $row['invite_emails'] ?? null,
                    'owner' => $ownerName,
                    'is_owner' => false,
                    'my_calendar' => true,
                    'invited' => true
                ]
            ];
        }
    }

    // Kalender-Termine anderer User (wenn einzelne User ausgewählt)
    foreach ($otherUserIds as $ouId) {
        // Eigene Termine des Kollegen
        $stmt = $pdo->prepare("
            SELECT e.id, e.title, e.description, e.meeting_link, e.start_at, e.end_at, e.all_day, u.vorname, u.nachname
            FROM calendar_events e
            JOIN users u ON e.user_id = u.id
            WHERE e.user_id = :ouid AND e.start_at <= :end AND e.end_at >= :start
        ");
        $stmt->bindValue(':ouid', $ouId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ownerName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'User #' . $ouId;
            $ouIsAllDay = (bool) $row['all_day'];
            $ouStart = $ouIsAllDay ? substr($row['start_at'], 0, 10) : $row['start_at'];
            $ouEnd = $ouIsAllDay ? substr($row['end_at'], 0, 10) : $row['end_at'];
            $events[] = [
                'id' => 'custom_ou_' . $ouId . '_' . $row['id'],
                'title' => $ownerName . ': ' . $row['title'],
                'start' => $ouStart,
                'end' => $ouEnd,
                'allDay' => $ouIsAllDay,
                'editable' => false,
                'backgroundColor' => '#8b5cf6',
                'borderColor' => '#7c3aed',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source' => 'other_users',
                    'custom_id' => (int) $row['id'],
                    'description' => $row['description'],
                    'meeting_link' => $row['meeting_link'] ?? null,
                    'owner' => $ownerName,
                    'user_id' => $ouId
                ]
            ];
        }
        
        // Termine zu denen der Kollege eingeladen wurde
        $stmt = $pdo->prepare("
            SELECT e.id, e.title, e.description, e.meeting_link, e.start_at, e.end_at, e.all_day,
                   invitee.vorname as invitee_vorname, invitee.nachname as invitee_nachname,
                   creator.vorname as creator_vorname, creator.nachname as creator_nachname
            FROM calendar_events e
            JOIN calendar_event_invitees i ON e.id = i.event_id
            JOIN users invitee ON i.user_id = invitee.id
            JOIN users creator ON e.user_id = creator.id
            WHERE i.user_id = :ouid
            AND e.start_at <= :end AND e.end_at >= :start
        ");
        $stmt->bindValue(':ouid', $ouId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inviteeName = trim(($row['invitee_vorname'] ?? '') . ' ' . ($row['invitee_nachname'] ?? '')) ?: 'User #' . $ouId;
            $creatorName = trim(($row['creator_vorname'] ?? '') . ' ' . ($row['creator_nachname'] ?? '')) ?: 'Unbekannt';
            $ouInvIsAllDay = (bool) $row['all_day'];
            $ouInvStart = $ouInvIsAllDay ? substr($row['start_at'], 0, 10) : $row['start_at'];
            $ouInvEnd = $ouInvIsAllDay ? substr($row['end_at'], 0, 10) : $row['end_at'];
            $events[] = [
                'id' => 'custom_ou_inv_' . $ouId . '_' . $row['id'],
                'title' => $inviteeName . ': ' . $row['title'],
                'start' => $ouInvStart,
                'end' => $ouInvEnd,
                'allDay' => $ouInvIsAllDay,
                'editable' => false,
                'backgroundColor' => '#a78bfa',
                'borderColor' => '#8b5cf6',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source' => 'other_users',
                    'custom_id' => (int) $row['id'],
                    'description' => $row['description'],
                    'meeting_link' => $row['meeting_link'] ?? null,
                    'owner' => $creatorName,
                    'user_id' => $ouId,
                    'invited' => true,
                    'sourceLabel' => 'Einladung von ' . $creatorName
                ]
            ];
        }
    }

    // Tickets Termine (geplant_datum, faellig_datum)
    if (!empty($filters['service']) && $isAdminOrTechniker) {
        $companyCondTickets = $filterCompanyId ? ' AND t.company_id = :filter_company' : '';
        $stmt = $pdo->prepare("
            SELECT t.id, t.ticket_nummer, t.titel, t.status, t.geplant_datum, t.faellig_datum {$selTicketEnd}, t.zugewiesen_an, t.erstellt_von,
                   t.customer_id, t.device_id, t.company_id,
                   u.vorname, u.nachname,
                   cu.name AS customer_name, cu.adresse AS customer_adresse, cu.plz AS customer_plz, cu.ort AS customer_ort,
                   d.name AS device_name, d.beschreibung AS device_beschreibung,
                   co.name AS company_name
            FROM tickets t
            LEFT JOIN users u ON t.zugewiesen_an = u.id
            LEFT JOIN customers cu ON t.customer_id = cu.id
            LEFT JOIN devices d ON t.device_id = d.id
            LEFT JOIN companies co ON t.company_id = co.id
            WHERE (t.geplant_datum BETWEEN :start AND :end OR t.faellig_datum BETWEEN :start2 AND :end2)
            AND t.status != 'Geschlossen'
            {$companyCondTickets}
        ");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->bindValue(':start2', $start);
        $stmt->bindValue(':end2', $end);
        if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            calendarDecryptTicketContactFields($row);
            $assigneeName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            // Admin und Techniker können alle Tickets per Drag&Drop verschieben und Dauer anpassen
            $canEditTicket = $isAdminOrTechniker;
            $extProps = [
                'source' => 'service',
                'sourceLabel' => 'Ticket',
                'ticket_id' => $row['id'],
                'ticket_nummer' => $row['ticket_nummer'],
                'titel' => $row['titel'] ?? '',
                'ticket_status' => $row['status'] ?? '',
                'user' => $assigneeName,
                'detailUrl' => '/tickets/view.php?id=' . $row['id']
            ];
            if (!empty($row['zugewiesen_an'])) {
                $extProps['zugewiesen_an'] = (int) $row['zugewiesen_an'];
            }
            if (!empty($row['customer_id'])) {
                $extProps['customerUrl'] = '/customers/detail.php?id=' . $row['customer_id'];
                $extProps['customerName'] = calendarPlaintextLabel($row['customer_name'] ?? '') ?: 'Kunde';
                $addrParts = array_filter([
                    $row['customer_adresse'] ?? '',
                    trim(($row['customer_plz'] ?? '') . ' ' . ($row['customer_ort'] ?? ''))
                ]);
                $fullAddress = implode(', ', $addrParts);
                if ($fullAddress) {
                    $extProps['mapsUrl'] = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($fullAddress);
                }
            }
            if (!empty($row['device_id'])) {
                calendarSetDeviceExtProps($row, $extProps);
            }
            $companyLabel = calendarPlaintextLabel($row['company_name'] ?? '');
            if ($companyLabel !== '') {
                $extProps['companyName'] = $companyLabel;
            }
            if (!empty($row['company_id'])) {
                $extProps['company_id'] = (int) $row['company_id'];
                $extProps['companyUrl'] = '/companies/detail.php?id=' . $row['company_id'];
            }
            if ($row['geplant_datum']) {
                $startPlan = $row['geplant_datum'];
                $endPlan = !empty($row['geplant_datum_ende']) ? $row['geplant_datum_ende'] : (new DateTime($startPlan))->modify('+1 hour')->format('Y-m-d H:i:s');
                $events[] = [
                    'id' => 'ticket_plan_' . $row['id'],
                    'title' => calendarTicketDisplayLabel($row),
                    'start' => $startPlan,
                    'end' => $endPlan,
                    'editable' => $canEditTicket,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'textColor' => '#ffffff',
                    'extendedProps' => array_merge($extProps, ['sourceLabel' => 'Ticket'])
                ];
            }
            if ($row['faellig_datum'] && $row['faellig_datum'] !== $row['geplant_datum']) {
                $startFaellig = $row['faellig_datum'];
                $endFaellig = !empty($row['faellig_datum_ende']) ? $row['faellig_datum_ende'] : (new DateTime($startFaellig))->modify('+1 hour')->format('Y-m-d H:i:s');
                $faelligProps = array_merge($extProps, ['sourceLabel' => 'Ticket (Fällig)', 'isFaellig' => true]);
                $events[] = [
                    'id' => 'ticket_faellig_' . $row['id'],
                    'title' => calendarTicketDisplayLabel($row),
                    'start' => $startFaellig,
                    'end' => $endFaellig,
                    'editable' => $canEditTicket,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'textColor' => '#ffffff',
                    'extendedProps' => $faelligProps
                ];
            }
        }
    }

    // Ticket-Termine (ticket_appointments)
    if (!empty($filters['service'])) {
        try {
            if ($isAdminOrTechniker) {
                // Admin/Techniker: alle Termine
                $companyCondAppt = $filterCompanyId ? ' AND t.company_id = :filter_company' : '';
                $stmt = $pdo->prepare("
                    SELECT ta.id, ta.ticket_id, ta.titel, ta.typ, ta.start_datum, ta.ende_datum,
                           t.id as ticket_id_full, t.ticket_nummer, t.titel as ticket_titel, t.status, t.company_id, t.customer_id, t.device_id, t.zugewiesen_an,
                           u.vorname, u.nachname,
                           c.name as company_name,
                           cust.name as customer_name, cust.adresse as customer_adresse, cust.plz as customer_plz, cust.ort as customer_ort,
                           d.name as device_name, d.beschreibung as device_beschreibung
                    FROM ticket_appointments ta
                    JOIN tickets t ON ta.ticket_id = t.id
                    LEFT JOIN users u ON t.zugewiesen_an = u.id
                    LEFT JOIN companies c ON t.company_id = c.id
                    LEFT JOIN customers cust ON t.customer_id = cust.id
                    LEFT JOIN devices d ON t.device_id = d.id
                    WHERE ta.start_datum BETWEEN :start AND :end
                    AND (t.company_id IS NULL OR c.status = 'aktiv')
                    AND t.titel NOT LIKE '[Gelöscht] %'
                    {$companyCondAppt}
                ");
                $stmt->bindValue(':start', $start);
                $stmt->bindValue(':end', $end);
                if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
            } elseif ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') {
                // Firmen-Admin/Firmen-User: nur Termine der eigenen Firma
                $stmt = $pdo->prepare("
                    SELECT ta.id, ta.ticket_id, ta.titel, ta.typ, ta.start_datum, ta.ende_datum,
                           t.id as ticket_id_full, t.ticket_nummer, t.titel as ticket_titel, t.status, t.company_id, t.customer_id, t.device_id, t.zugewiesen_an,
                           u.vorname, u.nachname,
                           c.name as company_name,
                           cust.name as customer_name, cust.adresse as customer_adresse, cust.plz as customer_plz, cust.ort as customer_ort,
                           d.name as device_name, d.beschreibung as device_beschreibung
                    FROM ticket_appointments ta
                    JOIN tickets t ON ta.ticket_id = t.id
                    LEFT JOIN users u ON t.zugewiesen_an = u.id
                    LEFT JOIN companies c ON t.company_id = c.id
                    LEFT JOIN customers cust ON t.customer_id = cust.id
                    LEFT JOIN devices d ON t.device_id = d.id
                    WHERE ta.start_datum BETWEEN :start AND :end
                    AND t.company_id = :user_company_id
                    AND (t.company_id IS NULL OR c.status = 'aktiv')
                    AND t.titel NOT LIKE '[Gelöscht] %'
                ");
                $stmt->bindValue(':user_company_id', $userCompanyId, PDO::PARAM_INT);
                $stmt->bindValue(':start', $start);
                $stmt->bindValue(':end', $end);
            } else {
                // Andere Benutzer: nur zugewiesene oder beobachtete Tickets
                $stmt = $pdo->prepare("
                    SELECT ta.id, ta.ticket_id, ta.titel, ta.typ, ta.start_datum, ta.ende_datum,
                           t.id as ticket_id_full, t.ticket_nummer, t.titel as ticket_titel, t.status, t.company_id, t.customer_id, t.device_id, t.zugewiesen_an,
                           u.vorname, u.nachname,
                           c.name as company_name,
                           cust.name as customer_name, cust.adresse as customer_adresse, cust.plz as customer_plz, cust.ort as customer_ort,
                           d.name as device_name, d.beschreibung as device_beschreibung
                    FROM ticket_appointments ta
                    JOIN tickets t ON ta.ticket_id = t.id
                    LEFT JOIN users u ON t.zugewiesen_an = u.id
                    LEFT JOIN companies c ON t.company_id = c.id
                    LEFT JOIN customers cust ON t.customer_id = cust.id
                    LEFT JOIN devices d ON t.device_id = d.id
                    WHERE ta.start_datum BETWEEN :start AND :end
                    AND (t.zugewiesen_an = :uid OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :uid2))
                    AND (t.company_id IS NULL OR c.status = 'aktiv')
                    AND t.titel NOT LIKE '[Gelöscht] %'
                ");
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':start', $start);
                $stmt->bindValue(':end', $end);
            }
            
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                calendarDecryptTicketContactFields($row);
                // Admin und Techniker können alle Termine per Drag&Drop verschieben
                $canEditAppointment = $isAdminOrTechniker;
                
                $assigneeName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
                
                $appointmentTitle = !empty($row['titel']) ? $row['titel'] : ($row['typ'] === 'geplant' ? 'Geplant' : 'Fällig');
                $eventTitle = calendarTicketEventTitle($row, $appointmentTitle);

                $startAppointment = $row['start_datum'];
                $endAppointment = !empty($row['ende_datum']) ? $row['ende_datum'] : (new DateTime($startAppointment))->modify('+1 hour')->format('Y-m-d H:i:s');
                
                // extendedProps genauso wie bei normalen Ticket-Terminen
                $ticketTitle = $row['ticket_titel'] ?? 'Ticket';
                $appointmentTitle = !empty($row['titel']) ? $row['titel'] : ($row['typ'] === 'geplant' ? 'Geplant' : 'Fällig');
                $extProps = [
                    'source' => 'service',
                    'sourceLabel' => $row['typ'] === 'geplant' ? 'Ticket' : 'Ticket (Fällig)',
                    'ticket_id' => $row['ticket_id'],
                    'ticket_nummer' => $row['ticket_nummer'] ?? '',
                    'titel' => $ticketTitle,
                    'appointment_titel' => $appointmentTitle,
                    'ticket_status' => $row['status'] ?? '',
                    'user' => $assigneeName,
                    'detailUrl' => '/tickets/view.php?id=' . $row['ticket_id'],
                    'appointment_id' => $row['id']
                ];
                if (!empty($row['zugewiesen_an'])) {
                    $extProps['zugewiesen_an'] = (int) $row['zugewiesen_an'];
                }
                
                if ($row['typ'] === 'faellig') {
                    $extProps['isFaellig'] = true;
                }
                
                $companyLabel = calendarPlaintextLabel($row['company_name'] ?? '');
                if ($companyLabel !== '') {
                    $extProps['companyName'] = $companyLabel;
                }
                if (!empty($row['company_id'])) {
                    $extProps['company_id'] = (int) $row['company_id'];
                    $extProps['companyUrl'] = '/companies/detail.php?id=' . $row['company_id'];
                }
                if (!empty($row['customer_id'])) {
                    $extProps['customerUrl'] = '/customers/detail.php?id=' . $row['customer_id'];
                    $extProps['customerName'] = calendarPlaintextLabel($row['customer_name'] ?? '') ?: 'Kunde';
                    $addrParts = array_filter([
                        $row['customer_adresse'] ?? '',
                        trim(($row['customer_plz'] ?? '') . ' ' . ($row['customer_ort'] ?? ''))
                    ]);
                    $fullAddress = implode(', ', $addrParts);
                    if ($fullAddress) {
                        $extProps['mapsUrl'] = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($fullAddress);
                    }
                }
                if (!empty($row['device_id'])) {
                    calendarSetDeviceExtProps($row, $extProps);
                }
                
                // Farben genauso wie bei normalen Ticket-Terminen
                $backgroundColor = $row['typ'] === 'geplant' ? '#3b82f6' : '#f59e0b';
                $borderColor = $row['typ'] === 'geplant' ? '#2563eb' : '#d97706';
                
                $events[] = [
                    'id' => 'ticket_appointment_' . $row['id'],
                    'title' => $eventTitle,
                    'start' => $startAppointment,
                    'end' => $endAppointment,
                    'editable' => $canEditAppointment,
                    'backgroundColor' => $backgroundColor,
                    'borderColor' => $borderColor,
                    'textColor' => '#ffffff',
                    'extendedProps' => $extProps
                ];
            }
        } catch (PDOException $e) {
            // Tabelle ticket_appointments existiert möglicherweise noch nicht
            error_log("Fehler beim Laden der Ticket-Termine: " . $e->getMessage());
        }
    }

    // Aufgaben Termine (faellig_am)
    if (!empty($filters['todos']) && $isAdminOrTechniker) {
        $companyCondTodos = $filterCompanyId ? ' AND t.company_id = :filter_company' : '';
        $stmt = $pdo->prepare("
            SELECT t.id, t.titel, t.faellig_am, t.zugewiesen_an,
                   u.vorname, u.nachname
            FROM todos t
            LEFT JOIN users u ON t.zugewiesen_an = u.id
            WHERE t.faellig_am BETWEEN :start AND :end AND t.status != 'erledigt'
            {$companyCondTodos}
        ");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $assigneeName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            $events[] = calendarBuildTodoCalendarEvent(
                $row,
                'todo_' . $row['id'],
                $assigneeName,
                $isAdminOrTechniker,
                '#22c55e',
                '#16a34a'
            );
        }
    }

    // Bestellungen Termine (erstellt_datum als Referenz)
    if (!empty($filters['orders']) && $isAdminOrTechniker) {
        $stmt = $pdo->prepare("
            SELECT o.id, o.bestellnummer, o.beschreibung, o.erstellt_datum, o.erstellt_von,
                   u.vorname, u.nachname
            FROM orders o
            LEFT JOIN users u ON o.erstellt_von = u.id
            WHERE o.erstellt_datum BETWEEN :start AND :end
            AND o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')
        ");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userName = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt';
            $title = $row['beschreibung'] ? mb_substr($row['beschreibung'], 0, 40) . (mb_strlen($row['beschreibung']) > 40 ? '…' : '') : ($row['bestellnummer'] ?: 'Bestellung #' . $row['id']);
            $events[] = [
                'id' => 'order_' . $row['id'],
                'title' => 'Bestellung: ' . $title,
                'start' => $row['erstellt_datum'],
                'editable' => false,
                'backgroundColor' => '#a855f7',
                'borderColor' => '#9333ea',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source' => 'orders',
                    'sourceLabel' => 'Bestellung',
                    'order_id' => $row['id'],
                    'bestellnummer' => $row['bestellnummer'] ?? null,
                    'description' => $row['beschreibung'] ?? null,
                    'user' => $userName,
                    'detailUrl' => '/orders/detail.php?id=' . $row['id']
                ]
            ];
        }
    }

    // Mein Urlaub
    if (!empty($filters['my_vacation'])) {
        $stmt = $pdo->prepare("
            SELECT id, date, hours, type
            FROM time_tracking_vacation
            WHERE user_id = :user_id AND date BETWEEN :start AND :end
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', substr($start, 0, 10));
        $stmt->bindValue(':end', substr($end, 0, 10));
        $stmt->execute();
        $types = ['vacation' => 'Urlaub', 'sick' => 'Krank', 'holiday' => 'Feiertag', 'other' => 'Sonstiges'];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = $types[$row['type']] ?? $row['type'];
            $events[] = [
                'id' => 'vacation_my_' . $row['id'],
                'title' => $label . ' (' . $row['hours'] . 'h)',
                'start' => $row['date'] . 'T08:00:00',
                'end' => $row['date'] . 'T17:00:00',
                'allDay' => false,
                'editable' => true,
                'backgroundColor' => '#06b6d4',
                'borderColor' => '#0891b2',
                'textColor' => '#ffffff',
                'extendedProps' => ['source' => 'my_vacation', 'sourceLabel' => 'Urlaub', 'vacation_id' => (int) $row['id']]
            ];
        }
    }

    // Meine Zeiten (Zeiterfassung)
    if (!empty($filters['my_times']) && $isAdminOrTechniker) {
        $stmt = $pdo->prepare("
            SELECT id, start_time, end_time, description
            FROM time_tracking
            WHERE user_id = :user_id
            AND start_time <= :end
            AND (end_time >= :start OR end_time IS NULL)
            ORDER BY start_time
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $endTime = $row['end_time'] ?? $row['start_time'];
            $title = $row['description'] ? mb_substr($row['description'], 0, 50) . (mb_strlen($row['description']) > 50 ? '…' : '') : 'Zeiterfassung';
            $events[] = [
                'id' => 'time_' . $row['id'],
                'title' => $title,
                'start' => $row['start_time'],
                'end' => $endTime,
                'allDay' => false,
                'editable' => false,
                'backgroundColor' => '#14b8a6',
                'borderColor' => '#0d9488',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source' => 'my_times',
                    'sourceLabel' => 'Meine Zeiten',
                    'time_tracking_id' => (int) $row['id'],
                    'description' => $row['description']
                ]
            ];
        }
    }

    // Urlaub Kollegen (andere Admins/Techniker)
    if (!empty($filters['colleagues_vacation']) && $isAdminOrTechniker) {
        $stmt = $pdo->prepare("
            SELECT v.id, v.user_id, v.date, v.hours, v.type, u.vorname, u.nachname
            FROM time_tracking_vacation v
            JOIN users u ON v.user_id = u.id
            WHERE v.user_id != :user_id
            AND u.rolle IN ('Admin', 'Techniker')
            AND v.date BETWEEN :start AND :end
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start', substr($start, 0, 10));
        $stmt->bindValue(':end', substr($end, 0, 10));
        $stmt->execute();
        $types = ['vacation' => 'Urlaub', 'sick' => 'Krank', 'holiday' => 'Feiertag', 'other' => 'Sonstiges'];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
            $label = $types[$row['type']] ?? $row['type'];
            $events[] = [
                'id' => 'vacation_col_' . $row['id'],
                'title' => $name . ': ' . $label,
                'start' => $row['date'] . 'T08:00:00',
                'end' => $row['date'] . 'T17:00:00',
                'allDay' => false,
                'editable' => false,
                'backgroundColor' => '#64748b',
                'borderColor' => '#475569',
                'textColor' => '#ffffff',
                'extendedProps' => ['source' => 'colleagues_vacation', 'sourceLabel' => 'Urlaub Kollege', 'user' => $name, 'vacation_id' => (int) $row['id']]
            ];
        }
    }

    // Kalender anderer Admins/Techniker: nur wenn konkrete User-IDs ausgewählt
    $usersToShow = $otherUserIds;
    if (empty($usersToShow) && !empty($filters['other_users']) && $isAdminOrTechniker) {
        $stmt = $pdo->prepare("
            SELECT id FROM users
            WHERE rolle IN ('Admin', 'Techniker') AND id != :uid AND status = 'aktiv'
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $usersToShow = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
    if (!empty($usersToShow) && $isAdminOrTechniker) {
        foreach ($usersToShow as $ouId) {
            $stmt = $pdo->prepare("SELECT id, vorname, nachname FROM users WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $ouId, PDO::PARAM_INT);
            $stmt->execute();
            $ou = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ou) continue;
            $ouId = (int) $ou['id'];
            $ouName = trim(($ou['vorname'] ?? '') . ' ' . ($ou['nachname'] ?? '')) ?: 'User #' . $ouId;
            // Tickets (geplant/fällig) dieses Users
            $companyCondOuTickets = $filterCompanyId ? ' AND company_id = :filter_company' : '';
            $stmt = $pdo->prepare("
                SELECT id, titel, ticket_nummer, geplant_datum, faellig_datum
                FROM tickets
                WHERE (zugewiesen_an = :ouid OR erstellt_von = :ouid2)
                AND (geplant_datum BETWEEN :start AND :end OR faellig_datum BETWEEN :start2 AND :end2)
                AND status != 'Geschlossen'
                {$companyCondOuTickets}
            ");
            $stmt->bindValue(':ouid', $ouId, PDO::PARAM_INT);
            $stmt->bindValue(':ouid2', $ouId, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start);
            $stmt->bindValue(':end', $end);
            $stmt->bindValue(':start2', $start);
            $stmt->bindValue(':end2', $end);
            if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['geplant_datum']) {
                    $events[] = [
                        'id' => 'ou_t_' . $ouId . '_' . $row['id'],
                        'title' => $ouName . ': ' . $row['titel'],
                        'start' => $row['geplant_datum'],
                        'editable' => false,
                        'backgroundColor' => '#8b5cf6',
                        'borderColor' => '#7c3aed',
                        'textColor' => '#ffffff',
                        'extendedProps' => ['source' => 'other_users', 'user_id' => $ouId, 'user' => $ouName, 'ticket_id' => $row['id']]
                    ];
                }
                if ($row['faellig_datum'] && $row['faellig_datum'] !== $row['geplant_datum']) {
                    $events[] = [
                        'id' => 'ou_tf_' . $ouId . '_' . $row['id'],
                        'title' => $ouName . ' [Fällig]: ' . $row['titel'],
                        'start' => $row['faellig_datum'],
                        'editable' => false,
                        'backgroundColor' => '#f59e0b',
                        'borderColor' => '#d97706',
                        'textColor' => '#ffffff',
                        'extendedProps' => ['source' => 'other_users', 'user_id' => $ouId, 'user' => $ouName, 'ticket_id' => $row['id']]
                    ];
                }
            }
            // Todos dieses Users
            $companyCondOuTodos = $filterCompanyId ? ' AND company_id = :filter_company' : '';
            $stmt = $pdo->prepare("
                SELECT id, titel, faellig_am FROM todos
                WHERE zugewiesen_an = :ouid AND faellig_am BETWEEN :start AND :end AND status != 'erledigt'
                {$companyCondOuTodos}
            ");
            $stmt->bindValue(':ouid', $ouId, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start);
            $stmt->bindValue(':end', $end);
            if ($filterCompanyId) $stmt->bindValue(':filter_company', $filterCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $events[] = calendarBuildTodoCalendarEvent(
                    $row,
                    'ou_todo_' . $ouId . '_' . $row['id'],
                    $ouName,
                    false,
                    '#10b981',
                    '#059669',
                    $ouName . ': ',
                    ['source' => 'other_users', 'user_id' => $ouId, 'user' => $ouName]
                );
            }
        }
    }

} catch (PDOException $e) {
    error_log('Kalender events API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
    exit;
}

echo json_encode($events);
