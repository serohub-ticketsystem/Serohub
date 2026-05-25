<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/email.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

header('Content-Type: application/json');

/**
 * Sendet Kalender-Einladungs-E-Mails an externe Adressen.
 * Nutzt E-Mail-Template wenn konfiguriert, sonst Standard-Mail.
 * Link führt zum ICS-Download (in eigenen Kalender einfügen).
 */
function sendCalendarInviteEmails($pdo, $emailList, $title, $startAt, $endAt, $allDay, $organizerName, $description = '', $meetingLink = '', $eventId = null, $icsToken = null) {
    if (empty($emailList)) return;
    $emails = array_filter(array_map('trim', preg_split('/[\s,;]+/', $emailList)), function($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL);
    });
    if (empty($emails)) return;

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') ?: '';
    $addToCalendarLink = '';
    if ($icsToken) {
        $addToCalendarLink = $baseUrl . $basePath . '/kalender/api/event-ics.php?token=' . urlencode($icsToken);
    } else {
        $addToCalendarLink = $baseUrl . $basePath . '/kalender/';
    }

    $startDt = new DateTime($startAt, new DateTimeZone('Europe/Berlin'));
    $endDt = new DateTime($endAt, new DateTimeZone('Europe/Berlin'));
    if ($allDay) {
        $zeitStr = $startDt->format('d.m.Y') . ' (ganztägig)';
    } else {
        $zeitStr = $startDt->format('d.m.Y H:i') . ' – ' . $endDt->format('d.m.Y H:i');
    }

    $meetingButtonHtml = $meetingLink ? '<p><a href="' . htmlspecialchars($meetingLink) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">Meeting beitreten</a></p>' : '';
    $variables = [
        'titel' => $title,
        'zeitStr' => $zeitStr,
        'organisator' => $organizerName,
        'beschreibung' => $description,
        'meeting_link' => $meetingLink,
        'meeting_button_html' => $meetingButtonHtml,
        'add_to_calendar_link' => $addToCalendarLink,
        'link' => $addToCalendarLink,
        'datum' => $zeitStr
    ];

    $templateId = null;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'email_template_calendar_invite' AND setting_value != '' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $templateId = (int) $row['setting_value'];
    } catch (PDOException $e) { /* ignore */ }

    if ($templateId) {
        foreach ($emails as $email) {
            try {
                sendEmailWithTemplate($templateId, $email, $variables, 'Termin-Einladung: ' . $title, 'Kalender · Einladung');
            } catch (Exception $e) {
                error_log('Kalender-Einladung an ' . $email . ' fehlgeschlagen: ' . $e->getMessage());
            }
        }
        return;
    }

    $msg = "Sie wurden zu einem Termin eingeladen:\n\n";
    $msg .= "Titel: " . $title . "\n";
    $msg .= "Datum/Zeit: " . $zeitStr . "\n";
    $msg .= "Von: " . $organizerName . "\n\n";
    if ($description) $msg .= "Beschreibung:\n" . $description . "\n\n";
    if ($meetingLink) $msg .= "Meeting-Link: " . $meetingLink . "\n\n";
    $msg .= "In eigenen Kalender übernehmen: " . $addToCalendarLink;

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Termin-Einladung</title></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">';
    $html .= '<div style="background:#f8f9fa;padding:20px;border-radius:5px;margin-bottom:20px;"><h1 style="color:#2563eb;margin:0;">Termin-Einladung</h1></div>';
    $html .= '<p>Sie wurden zu einem Termin eingeladen:</p>';
    $html .= '<div style="background:#f9fafb;padding:15px;border-left:4px solid #2563eb;margin:20px 0;">';
    $html .= '<p style="margin:0 0 8px 0;"><strong>Titel:</strong> ' . htmlspecialchars($title) . '</p>';
    $html .= '<p style="margin:0 0 8px 0;"><strong>Datum/Zeit:</strong> ' . htmlspecialchars($zeitStr) . '</p>';
    $html .= '<p style="margin:0;"><strong>Von:</strong> ' . htmlspecialchars($organizerName) . '</p>';
    if ($description) $html .= '<p style="margin:16px 0 0 0;">' . nl2br(htmlspecialchars($description)) . '</p>';
    $html .= '</div>';
    if ($meetingLink) $html .= '<p><a href="' . htmlspecialchars($meetingLink) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">Meeting beitreten</a></p>';
    $html .= '<p><a href="' . htmlspecialchars($addToCalendarLink) . '" style="display:inline-block;background:#16a34a;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">In eigenen Kalender übernehmen &rarr;</a></p>';
    $html .= '<p style="margin-top:30px;">Mit freundlichen Grüßen,<br>Ihr Serohub</p></body></html>';

    $subject = 'Termin-Einladung: ' . $title;
    foreach ($emails as $email) {
        try {
            sendEmail($email, $subject, $html, true, null, null, null, 'Kalender · Einladung');
        } catch (Exception $e) {
            error_log('Kalender-Einladung an ' . $email . ' fehlgeschlagen: ' . $e->getMessage());
        }
    }
}

/**
 * Sendet Termin-Update-E-Mails an externe Adressen (wenn Termin geändert wurde).
 */
function sendCalendarUpdateEmails($pdo, $emailList, $title, $startAt, $endAt, $allDay, $organizerName, $description = '', $meetingLink = '', $eventId = null, $icsToken = null) {
    if (empty($emailList)) return;
    $emails = array_filter(array_map('trim', preg_split('/[\s,;]+/', $emailList)), function($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL);
    });
    if (empty($emails)) return;

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') ?: '';
    $addToCalendarLink = $icsToken
        ? $baseUrl . $basePath . '/kalender/api/event-ics.php?token=' . urlencode($icsToken)
        : $baseUrl . $basePath . '/kalender/';

    $startDt = new DateTime($startAt, new DateTimeZone('Europe/Berlin'));
    $endDt = new DateTime($endAt, new DateTimeZone('Europe/Berlin'));
    if ($allDay) {
        $zeitStr = $startDt->format('d.m.Y') . ' (ganztägig)';
    } else {
        $zeitStr = $startDt->format('d.m.Y H:i') . ' – ' . $endDt->format('d.m.Y H:i');
    }

    $meetingButtonHtml = $meetingLink ? '<p><a href="' . htmlspecialchars($meetingLink) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">Meeting beitreten</a></p>' : '';
    $variables = [
        'titel' => $title,
        'zeitStr' => $zeitStr,
        'organisator' => $organizerName,
        'beschreibung' => $description,
        'meeting_link' => $meetingLink,
        'meeting_button_html' => $meetingButtonHtml,
        'add_to_calendar_link' => $addToCalendarLink,
        'link' => $addToCalendarLink,
        'datum' => $zeitStr
    ];

    $templateId = null;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'email_template_calendar_update' AND setting_value != '' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $templateId = (int) $row['setting_value'];
    } catch (PDOException $e) { /* ignore */ }

    if ($templateId) {
        foreach ($emails as $email) {
            try {
                sendEmailWithTemplate($templateId, $email, $variables, 'Termin geändert: ' . $title, 'Kalender · Termin geändert');
            } catch (Exception $e) {
                error_log('Kalender-Update-Mail an ' . $email . ' fehlgeschlagen: ' . $e->getMessage());
            }
        }
        return;
    }

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Termin geändert</title></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">';
    $html .= '<div style="background:#fef3c7;padding:20px;border-radius:5px;margin-bottom:20px;"><h1 style="color:#b45309;margin:0;">Termin wurde geändert</h1></div>';
    $html .= '<p>Der folgende Termin wurde aktualisiert:</p>';
    $html .= '<div style="background:#f9fafb;padding:15px;border-left:4px solid #f59e0b;margin:20px 0;">';
    $html .= '<p style="margin:0 0 8px 0;"><strong>Titel:</strong> ' . htmlspecialchars($title) . '</p>';
    $html .= '<p style="margin:0 0 8px 0;"><strong>Neues Datum/Zeit:</strong> ' . htmlspecialchars($zeitStr) . '</p>';
    $html .= '<p style="margin:0;"><strong>Von:</strong> ' . htmlspecialchars($organizerName) . '</p>';
    if ($description) $html .= '<p style="margin:16px 0 0 0;">' . nl2br(htmlspecialchars($description)) . '</p>';
    $html .= '</div>';
    if ($meetingLink) $html .= '<p><a href="' . htmlspecialchars($meetingLink) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">Meeting beitreten</a></p>';
    $html .= '<p><a href="' . htmlspecialchars($addToCalendarLink) . '" style="display:inline-block;background:#16a34a;color:#fff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;">Aktualisierten Termin in Kalender übernehmen &rarr;</a></p>';
    $html .= '<p style="margin-top:30px;">Mit freundlichen Grüßen,<br>Ihr Serohub</p></body></html>';

    $subject = 'Termin geändert: ' . $title;
    foreach ($emails as $email) {
        try {
            sendEmail($email, $subject, $html, true, null, null, null, 'Kalender · Termin geändert');
        } catch (Exception $e) {
            error_log('Kalender-Update-Mail an ' . $email . ' fehlgeschlagen: ' . $e->getMessage());
        }
    }
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $isAdminOrTechniker = $user && in_array($user['rolle'], ['Admin', 'Techniker'], true);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Nur Admin/Techniker dürfen Kalender-Termine anlegen, ändern oder löschen
if (!$isAdminOrTechniker && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT e.id, e.user_id, e.title, e.description, e.meeting_link, e.invite_emails, e.start_at, e.end_at, e.all_day, e.created_at,
                   u.vorname, u.nachname
            FROM calendar_events e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = :id AND (e.user_id = :uid OR EXISTS (SELECT 1 FROM calendar_event_invitees i WHERE i.event_id = e.id AND i.user_id = :uid2))
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Termin nicht gefunden']);
            exit;
        }
        $stmtInv = $pdo->prepare("SELECT i.user_id, u.vorname, u.nachname FROM calendar_event_invitees i JOIN users u ON i.user_id = u.id WHERE i.event_id = :eid");
        $stmtInv->bindValue(':eid', $id, PDO::PARAM_INT);
        $stmtInv->execute();
        $event['invitees'] = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'event' => $event]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID fehlt']);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $meetingLink = trim($input['meeting_link'] ?? '');
    $inviteEmails = trim($input['invite_emails'] ?? '');
    $startAt = $input['start_at'] ?? $input['start'] ?? '';
    $endAt = $input['end_at'] ?? $input['end'] ?? '';
    $allDay = !empty($input['all_day']);
    $invitees = isset($input['invitees']) && is_array($input['invitees']) ? array_map('intval', $input['invitees']) : [];

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Titel ist erforderlich']);
        exit;
    }
    if (!$startAt || !$endAt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Start und Ende sind erforderlich']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $icsToken = bin2hex(random_bytes(32));
        try {
            $stmt = $pdo->prepare("INSERT INTO calendar_events (user_id, title, description, meeting_link, invite_emails, ics_token, start_at, end_at, all_day) VALUES (:uid, :title, :desc, :meeting, :emails, :token, :start, :end, :allday)");
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':desc', $description === '' ? null : $description, PDO::PARAM_STR);
            $stmt->bindValue(':meeting', $meetingLink === '' ? null : $meetingLink, PDO::PARAM_STR);
            $stmt->bindValue(':emails', $inviteEmails === '' ? null : $inviteEmails, PDO::PARAM_STR);
            $stmt->bindValue(':token', $icsToken, PDO::PARAM_STR);
            $stmt->bindValue(':start', $startAt, PDO::PARAM_STR);
            $stmt->bindValue(':end', $endAt, PDO::PARAM_STR);
            $stmt->bindValue(':allday', $allDay ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'ics_token') !== false) {
                $icsToken = null;
                $stmt = $pdo->prepare("INSERT INTO calendar_events (user_id, title, description, meeting_link, invite_emails, start_at, end_at, all_day) VALUES (:uid, :title, :desc, :meeting, :emails, :start, :end, :allday)");
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':title', $title, PDO::PARAM_STR);
                $stmt->bindValue(':desc', $description === '' ? null : $description, PDO::PARAM_STR);
                $stmt->bindValue(':meeting', $meetingLink === '' ? null : $meetingLink, PDO::PARAM_STR);
                $stmt->bindValue(':emails', $inviteEmails === '' ? null : $inviteEmails, PDO::PARAM_STR);
                $stmt->bindValue(':start', $startAt, PDO::PARAM_STR);
                $stmt->bindValue(':end', $endAt, PDO::PARAM_STR);
                $stmt->bindValue(':allday', $allDay ? 1 : 0, PDO::PARAM_INT);
                $stmt->execute();
            } else throw $e;
        }
        $eventId = (int) $pdo->lastInsertId();

        foreach ($invitees as $invId) {
            if ($invId && $invId !== $userId) {
                $ins = $pdo->prepare("INSERT IGNORE INTO calendar_event_invitees (event_id, user_id) VALUES (:eid, :uid)");
                $ins->bindValue(':eid', $eventId, PDO::PARAM_INT);
                $ins->bindValue(':uid', $invId, PDO::PARAM_INT);
                $ins->execute();
            }
        }
        $pdo->commit();
        $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
        $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmtU->execute();
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
        if (!empty($inviteEmails)) {
            sendCalendarInviteEmails($pdo, $inviteEmails, $title, $startAt, $endAt, $allDay, $organizerName, $description, $meetingLink ?: '', $eventId, $icsToken);
        }
        $inviteeIds = array_values(array_filter($invitees, function ($id) use ($userId) { return $id && (int)$id !== (int)$userId; }));
        if (!empty($inviteeIds)) {
            createNotificationsForUsers($inviteeIds, 'calendar_invite', 'Termin-Einladung: ' . $title, 'Sie wurden zu einem Termin eingeladen: „' . $title . '“ von ' . $organizerName . '.', 'hoch', 'kalender/', 'calendar_event', $eventId, $userId);
        }
        echo json_encode(['success' => true, 'id' => $eventId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID fehlt']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM calendar_events WHERE id = :id AND user_id = :uid");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $meetingLink = trim($input['meeting_link'] ?? '');
    $inviteEmails = trim($input['invite_emails'] ?? '');
    $startAt = $input['start_at'] ?? $input['start'] ?? '';
    $endAt = $input['end_at'] ?? $input['end'] ?? '';
    $allDay = !empty($input['all_day']);
    $invitees = isset($input['invitees']) && is_array($input['invitees']) ? array_map('intval', $input['invitees']) : [];

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Titel ist erforderlich']);
        exit;
    }
    if (!$startAt || !$endAt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Start und Ende sind erforderlich']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE calendar_events SET title = :title, description = :desc, meeting_link = :meeting, invite_emails = :emails, start_at = :start, end_at = :end, all_day = :allday WHERE id = :id");
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':desc', $description === '' ? null : $description, PDO::PARAM_STR);
        $stmt->bindValue(':meeting', $meetingLink === '' ? null : $meetingLink, PDO::PARAM_STR);
        $stmt->bindValue(':emails', $inviteEmails === '' ? null : $inviteEmails, PDO::PARAM_STR);
        $stmt->bindValue(':start', $startAt, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endAt, PDO::PARAM_STR);
        $stmt->bindValue(':allday', $allDay ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $delInv = $pdo->prepare("DELETE FROM calendar_event_invitees WHERE event_id = :eid");
        $delInv->bindValue(':eid', $id, PDO::PARAM_INT);
        $delInv->execute();
        foreach ($invitees as $invId) {
            if ($invId && $invId !== $userId) {
                $ins = $pdo->prepare("INSERT IGNORE INTO calendar_event_invitees (event_id, user_id) VALUES (:eid, :uid)");
                $ins->bindValue(':eid', $id, PDO::PARAM_INT);
                $ins->bindValue(':uid', $invId, PDO::PARAM_INT);
                $ins->execute();
            }
        }
        $pdo->commit();
        if (!empty($inviteEmails)) {
            $icsToken = null;
            try {
                $stmtT = $pdo->prepare("SELECT ics_token FROM calendar_events WHERE id = :id LIMIT 1");
                $stmtT->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtT->execute();
                $rowT = $stmtT->fetch(PDO::FETCH_ASSOC);
                $icsToken = $rowT['ics_token'] ?? null;
                if (empty($icsToken)) {
                    $icsToken = bin2hex(random_bytes(32));
                    $upd = $pdo->prepare("UPDATE calendar_events SET ics_token = :token WHERE id = :id");
                    $upd->bindValue(':token', $icsToken, PDO::PARAM_STR);
                    $upd->bindValue(':id', $id, PDO::PARAM_INT);
                    $upd->execute();
                }
            } catch (PDOException $e) { /* Spalte existiert evtl. nicht */ }
            $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
            $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmtU->execute();
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
            sendCalendarUpdateEmails($pdo, $inviteEmails, $title, $startAt, $endAt, $allDay, $organizerName, $description, $meetingLink ?: '', $id, $icsToken);
        }
        $stmtInv = $pdo->prepare("SELECT user_id FROM calendar_event_invitees WHERE event_id = :eid");
        $stmtInv->bindValue(':eid', $id, PDO::PARAM_INT);
        $stmtInv->execute();
        $inviteeIds = array_column($stmtInv->fetchAll(PDO::FETCH_ASSOC), 'user_id');
        if (!empty($inviteeIds)) {
            $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
            $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmtU->execute();
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
            createNotificationsForUsers($inviteeIds, 'calendar_update', 'Termin geändert: ' . $title, 'Der Termin „' . $title . '“ wurde von ' . $organizerName . ' aktualisiert.', 'normal', 'kalender/', 'calendar_event', $id, $userId);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    exit;
}

if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID fehlt']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM calendar_events WHERE id = :id AND user_id = :uid");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    $startAt = $input['start_at'] ?? $input['start'] ?? '';
    $endAt = $input['end_at'] ?? $input['end'] ?? '';
    $allDay = array_key_exists('all_day', $input) ? (bool) $input['all_day'] : null;
    if (!$startAt || !$endAt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Start und Ende sind erforderlich']);
        exit;
    }
    $updates = ["start_at = :start", "end_at = :end"];
    $params = [':start' => $startAt, ':end' => $endAt, ':id' => $id];
    if ($allDay !== null) {
        $updates[] = "all_day = :allday";
        $params[':allday'] = $allDay ? 1 : 0;
    }
    $stmt = $pdo->prepare("UPDATE calendar_events SET " . implode(", ", $updates) . " WHERE id = :id");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    if ($stmt->rowCount()) {
        $stmtEv = $pdo->prepare("SELECT title, description, meeting_link, invite_emails, ics_token, all_day FROM calendar_events WHERE id = :id LIMIT 1");
        $stmtEv->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtEv->execute();
        $ev = $stmtEv->fetch(PDO::FETCH_ASSOC);
        if ($ev && !empty(trim($ev['invite_emails'] ?? ''))) {
            $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
            $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmtU->execute();
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
            $evAllDay = (bool) ($ev['all_day'] ?? false);
            sendCalendarUpdateEmails($pdo, $ev['invite_emails'], $ev['title'], $startAt, $endAt, $evAllDay, $organizerName, $ev['description'] ?? '', $ev['meeting_link'] ?? '', $id, $ev['ics_token'] ?? null);
        }
        $stmtInv = $pdo->prepare("SELECT user_id FROM calendar_event_invitees WHERE event_id = :eid");
        $stmtInv->bindValue(':eid', $id, PDO::PARAM_INT);
        $stmtInv->execute();
        $inviteeIds = array_column($stmtInv->fetchAll(PDO::FETCH_ASSOC), 'user_id');
        if (!empty($inviteeIds)) {
            $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
            $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmtU->execute();
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
            createNotificationsForUsers($inviteeIds, 'calendar_update', 'Termin geändert: ' . $ev['title'], 'Der Termin „' . $ev['title'] . '“ wurde von ' . $organizerName . ' aktualisiert.', 'normal', 'kalender/', 'calendar_event', $id, $userId);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID fehlt']);
        exit;
    }
    $stmtEv = $pdo->prepare("SELECT title, user_id FROM calendar_events WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmtEv->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtEv->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmtEv->execute();
    $ev = $stmtEv->fetch(PDO::FETCH_ASSOC);
    if (!$ev) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    $stmtInv = $pdo->prepare("SELECT user_id FROM calendar_event_invitees WHERE event_id = :eid");
    $stmtInv->bindValue(':eid', $id, PDO::PARAM_INT);
    $stmtInv->execute();
    $inviteeIds = array_column($stmtInv->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = :id AND user_id = :uid");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount()) {
        if (!empty($inviteeIds)) {
            $stmtU = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = :uid LIMIT 1");
            $stmtU->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmtU->execute();
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            $organizerName = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: 'Serohub';
            createNotificationsForUsers($inviteeIds, 'calendar_event_deleted', 'Termin gelöscht: ' . ($ev['title'] ?? 'Termin'), 'Der Termin „' . ($ev['title'] ?? 'Termin') . '“ wurde von ' . $organizerName . ' gelöscht.', 'hoch', 'kalender/', 'calendar_event', $id, $userId);
        }
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
