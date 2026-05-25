<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$ticketId) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'tickets/');
    exit;
}

$userId = $_SESSION['user_id'];

// User und Rolle
$userStmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'tickets/');
    exit;
}
$userRole = $user['rolle'];
$userCompanyId = $user['company_id'] ?? null;

// Optionale Spalten für Geplant/Fällig Ende
$hasTicketEndColumns = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'geplant_datum_ende'");
    $hasTicketEndColumns = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {}
$selTicketEnd = $hasTicketEndColumns ? ', t.geplant_datum_ende, t.faellig_datum_ende' : '';

$sql = "
    SELECT 
        t.id, t.ticket_nummer, t.titel, t.beschreibung, t.status, t.prioritaet,
        t.company_id, t.customer_id, t.device_id, t.erstellt_von, t.zugewiesen_an,
        t.erstellt_datum, t.geaendert_datum, t.faellig_datum, t.geplant_datum{$selTicketEnd},
        t.abgeschlossen_datum, t.abgerechnet, t.bearbeitungszeit_minuten,
        c.name as company_name, c.logo as company_logo, c.adresse as company_adresse,
        c.plz as company_plz, c.ort as company_ort, c.email as company_email,
        c.telefonnummer as company_telefon, c.hat_wartungsvertrag as company_hat_wartungsvertrag,
        c.ansprechpartner_user_id as company_ansprechpartner_user_id,
        c.ansprechpartner_manuell_name as company_ansprechpartner_manuell_name,
        c.ansprechpartner_manuell_email as company_ansprechpartner_manuell_email,
        c.ansprechpartner_manuell_telefon as company_ansprechpartner_manuell_telefon,
        u_ca.vorname as company_ansprechpartner_vorname, u_ca.nachname as company_ansprechpartner_nachname,
        u_ca.email as company_ansprechpartner_email, u_ca.telefonnummer as company_ansprechpartner_telefon,
        cust.name as customer_name, cust.logo as customer_logo, cust.email as customer_email,
        cust.telefon as customer_telefon, cust.adresse as customer_adresse, cust.plz as customer_plz, cust.ort as customer_ort,
        cust.kundennummer as customer_kundennummer,
        cust.ansprechpartner_user_id as customer_ansprechpartner_user_id,
        cust.ansprechpartner_manuell_name as customer_ansprechpartner_manuell_name,
        cust.ansprechpartner_manuell_email as customer_ansprechpartner_manuell_email,
        cust.ansprechpartner_manuell_telefon as customer_ansprechpartner_manuell_telefon,
        u_cust.vorname as customer_ansprechpartner_vorname, u_cust.nachname as customer_ansprechpartner_nachname,
        u_cust.email as customer_ansprechpartner_email, u_cust.telefonnummer as customer_ansprechpartner_telefon,
        d.name as device_name, d.typ as device_typ, d.hersteller as device_hersteller,
        d.modell as device_modell, d.seriennummer as device_seriennummer,
        d.mac_adresse as device_mac_adresse, d.ip_adresse as device_ip_adresse,
        d.betriebssystem as device_betriebssystem, d.beschreibung as device_beschreibung,
        u_erstellt.vorname as ersteller_vorname, u_erstellt.nachname as ersteller_nachname,
        u_erstellt.email as ersteller_email,
        u_zugewiesen.vorname as zugewiesen_vorname, u_zugewiesen.nachname as zugewiesen_nachname,
        u_zugewiesen.email as zugewiesen_email
    FROM tickets t
    LEFT JOIN companies c ON t.company_id = c.id
    LEFT JOIN users u_ca ON c.ansprechpartner_user_id = u_ca.id
    LEFT JOIN customers cust ON t.customer_id = cust.id
    LEFT JOIN users u_cust ON cust.ansprechpartner_user_id = u_cust.id
    LEFT JOIN devices d ON t.device_id = d.id
    LEFT JOIN users u_erstellt ON t.erstellt_von = u_erstellt.id
    LEFT JOIN users u_zugewiesen ON t.zugewiesen_an = u_zugewiesen.id
    WHERE t.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'tickets/');
    exit;
}

// Firmen- und Kundendaten entschlüsseln (in der DB verschlüsselt gespeichert)
$companyRow = [
    'name' => $ticket['company_name'] ?? null,
    'adresse' => $ticket['company_adresse'] ?? null,
    'plz' => $ticket['company_plz'] ?? null,
    'ort' => $ticket['company_ort'] ?? null,
    'email' => $ticket['company_email'] ?? null,
    'telefonnummer' => $ticket['company_telefon'] ?? null,
    'ansprechpartner_manuell_name' => $ticket['company_ansprechpartner_manuell_name'] ?? null,
    'ansprechpartner_manuell_email' => $ticket['company_ansprechpartner_manuell_email'] ?? null,
    'ansprechpartner_manuell_telefon' => $ticket['company_ansprechpartner_manuell_telefon'] ?? null,
];
decrypt_company_row($companyRow);
$ticket['company_name'] = $companyRow['name'];
$ticket['company_adresse'] = $companyRow['adresse'];
$ticket['company_plz'] = $companyRow['plz'];
$ticket['company_ort'] = $companyRow['ort'];
$ticket['company_email'] = $companyRow['email'];
$ticket['company_telefon'] = $companyRow['telefonnummer'];
$ticket['company_ansprechpartner_manuell_name'] = $companyRow['ansprechpartner_manuell_name'];
$ticket['company_ansprechpartner_manuell_email'] = $companyRow['ansprechpartner_manuell_email'];
$ticket['company_ansprechpartner_manuell_telefon'] = $companyRow['ansprechpartner_manuell_telefon'];

$customerRow = [
    'name' => $ticket['customer_name'] ?? null,
    'kundennummer' => $ticket['customer_kundennummer'] ?? null,
    'email' => $ticket['customer_email'] ?? null,
    'telefon' => $ticket['customer_telefon'] ?? null,
    'adresse' => $ticket['customer_adresse'] ?? null,
    'plz' => $ticket['customer_plz'] ?? null,
    'ort' => $ticket['customer_ort'] ?? null,
    'ansprechpartner_manuell_name' => $ticket['customer_ansprechpartner_manuell_name'] ?? null,
    'ansprechpartner_manuell_email' => $ticket['customer_ansprechpartner_manuell_email'] ?? null,
    'ansprechpartner_manuell_telefon' => $ticket['customer_ansprechpartner_manuell_telefon'] ?? null,
];
decrypt_customer_row($customerRow);
$ticket['customer_name'] = $customerRow['name'];
$ticket['customer_kundennummer'] = $customerRow['kundennummer'];
$ticket['customer_email'] = $customerRow['email'];
$ticket['customer_telefon'] = $customerRow['telefon'];
$ticket['customer_adresse'] = $customerRow['adresse'];
$ticket['customer_plz'] = $customerRow['plz'];
$ticket['customer_ort'] = $customerRow['ort'];
$ticket['customer_ansprechpartner_manuell_name'] = $customerRow['ansprechpartner_manuell_name'];
$ticket['customer_ansprechpartner_manuell_email'] = $customerRow['ansprechpartner_manuell_email'];
$ticket['customer_ansprechpartner_manuell_telefon'] = $customerRow['ansprechpartner_manuell_telefon'];

// Berechtigung (wie in Tickets-API)
$hasPermission = false;
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $hasPermission = true;
} elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
    $hasPermission = true;
} elseif ($ticket['erstellt_von'] == $userId) {
    $hasPermission = true;
} else {
    $obsStmt = $pdo->prepare("SELECT 1 FROM ticket_observers WHERE ticket_id = ? AND user_id = ? LIMIT 1");
    $obsStmt->execute([$ticketId, $userId]);
    if ($obsStmt->fetchColumn()) {
        $hasPermission = true;
    }
}
if (!$hasPermission) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'tickets/view.php?id=' . $ticketId);
    exit;
}

// Kommentare laden
$commentSql = "
    SELECT tc.id, tc.kommentar, tc.nachrichtentyp, tc.ist_intern, tc.erstellt_datum,
           u.vorname, u.nachname, u.email
    FROM ticket_comments tc
    LEFT JOIN users u ON tc.user_id = u.id
    WHERE tc.ticket_id = ?
    ORDER BY tc.erstellt_datum ASC
";
$commentStmt = $pdo->prepare($commentSql);
$commentStmt->execute([$ticketId]);
$comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

// Ticket-Anhänge (direkt am Ticket)
$ticketAttachments = [];
try {
    $taStmt = $pdo->prepare("SELECT id, dateiname, dateipfad, mime_type FROM ticket_attachments WHERE ticket_id = ? ORDER BY erstellt_datum ASC");
    $taStmt->execute([$ticketId]);
    $ticketAttachments = $taStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Kommentar-Anhänge (pro Kommentar)
$commentAttachmentsMap = [];
if (!empty($comments)) {
    $commentIds = array_column($comments, 'id');
    $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
    try {
        $caStmt = $pdo->prepare("SELECT id, comment_id, dateiname, dateipfad, mime_type FROM comment_attachments WHERE comment_id IN ($placeholders) ORDER BY comment_id, erstellt_datum ASC");
        $caStmt->execute($commentIds);
        while ($row = $caStmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = $row['comment_id'];
            if (!isset($commentAttachmentsMap[$cid])) $commentAttachmentsMap[$cid] = [];
            $commentAttachmentsMap[$cid][] = $row;
        }
    } catch (PDOException $e) {}
}

$baseUrl = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/';

// Erscheinungsbild (Logo + Name) für PDF-Header
$pdfLogoPath = '';
$pdfNamePart1 = 'Tickets';
$pdfNamePart2 = 'Portal';
try {
    $brandStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
    $brandStmt->execute();
    foreach ($brandStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['setting_key'] === 'branding_logo' && !empty(trim($r['setting_value'] ?? ''))) $pdfLogoPath = trim($r['setting_value']);
        if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) $pdfNamePart1 = trim($r['setting_value']);
        if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) $pdfNamePart2 = trim($r['setting_value']);
    }
} catch (PDOException $e) {}
$pdfAppName = trim($pdfNamePart1 . ' ' . $pdfNamePart2);
$pdfLogoUrl = $pdfLogoPath ? ($baseUrl . ltrim($pdfLogoPath, '/')) : '';

function formatDate($d) {
    if (empty($d) || $d === '0000-00-00 00:00:00') return '–';
    $t = strtotime($d);
    return $t ? date('d.m.Y', $t) : '–';
}
function formatDateTime($d) {
    if (empty($d) || $d === '0000-00-00 00:00:00') return '–';
    $t = strtotime($d);
    return $t ? date('d.m.Y H:i', $t) : '–';
}
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function isImageMime($mime) {
    return $mime && strpos((string)$mime, 'image/') === 0;
}
function isPdfMime($mime) {
    return $mime && (strpos((string)$mime, 'application/pdf') !== false || (string)$mime === 'application/pdf');
}
$nachrichtentypLabels = [
    'nachricht' => 'Nachricht',
    'loesung'   => 'Lösung',
    'aufgabe'   => 'Aufgabe',
    'bestellung'=> 'Bestellung',
];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pdfAppName); ?> – Ticket <?php echo h($ticket['ticket_nummer']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; font-size: 12px; line-height: 1.45; color: #1f2937; margin: 0; padding: 24px; background: #f9fafb; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 18px; margin: 0 0 6px 0; color: #111; }
        .section { margin-bottom: 22px; break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 10px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .label { color: #6b7280; font-size: 11px; margin-bottom: 2px; }
        .value { margin-bottom: 8px; }
        .comment { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 10px; break-inside: avoid; background: #fafafa; }
        .comment.intern { background: #fef3c7; border-color: #fcd34d; }
        .comment-header { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
        .comment-type { display: inline-block; font-weight: 600; color: #374151; margin-right: 8px; }
        .comment-body { white-space: pre-wrap; word-wrap: break-word; }
        .attachments { margin-top: 10px; padding-top: 8px; border-top: 1px solid #e5e7eb; }
        .attachment-img { max-width: 100%; height: auto; border-radius: 4px; margin: 6px 0; display: block; }
        .attachment-link { display: inline-block; margin: 4px 8px 4px 0; color: #2563eb; }
        .no-print { margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 18px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #1d4ed8; }
        .pdf-header { display: flex; align-items: center; gap: 16px; padding-bottom: 16px; margin-bottom: 20px; border-bottom: 2px solid #1e293b; break-inside: avoid; }
        .pdf-header-logo { height: 48px; width: auto; max-width: 180px; object-fit: contain; }
        .pdf-header-text { flex: 1; }
        .pdf-header-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 2px 0; letter-spacing: -0.02em; }
        .pdf-header-subtitle { font-size: 12px; color: #64748b; margin: 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .container { box-shadow: none; padding: 16px; max-width: 100%; }
            .no-print { display: none !important; }
            .comment { background: #f9fafb !important; }
            .comment.intern { background: #fef9c3 !important; }
            .pdf-header { border-bottom-color: #1e293b !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="pdf-header">
            <?php if ($pdfLogoUrl) : ?>
                <img src="<?php echo h($pdfLogoUrl); ?>" alt="<?php echo h($pdfAppName); ?>" class="pdf-header-logo" />
            <?php endif; ?>
            <div class="pdf-header-text">
                <h2 class="pdf-header-title"><?php echo h($pdfAppName); ?></h2>
                <p class="pdf-header-subtitle">Ticket · <?php echo h($ticket['ticket_nummer']); ?></p>
            </div>
        </header>

        <h1><?php echo h($ticket['titel']); ?></h1>

        <div class="section">
            <div class="section-title">Beschreibung</div>
            <div class="value"><?php echo nl2br(h($ticket['beschreibung'] ?: '–')); ?></div>
        </div>

        <?php if (!empty($ticketAttachments)) : ?>
        <div class="section">
            <div class="section-title">Anhänge zum Ticket</div>
            <?php foreach ($ticketAttachments as $att) :
                $attUrl = $baseUrl . ltrim($att['dateipfad'], '/');
                $name = h($att['dateiname'] ?: 'Anhang');
            ?>
            <?php if (isImageMime($att['mime_type'])) : ?>
                <img class="attachment-img" src="<?php echo h($attUrl); ?>" alt="<?php echo $name; ?>" />
            <?php else : ?>
                <a class="attachment-link" href="<?php echo h($attUrl); ?>" target="_blank"><?php echo isPdfMime($att['mime_type']) ? 'PDF: ' : ''; ?><?php echo $name; ?></a>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">Zeiten &amp; Zuweisung</div>
            <div class="grid-2">
                <div><span class="label">Erstellt</span><div class="value"><?php echo formatDateTime($ticket['erstellt_datum']); ?></div></div>
                <div><span class="label">Geändert</span><div class="value"><?php echo formatDateTime($ticket['geaendert_datum']); ?></div></div>
                <div><span class="label">Fällig</span><div class="value"><?php echo formatDateTime($ticket['faellig_datum']); ?></div></div>
                <div><span class="label">Geplant</span><div class="value"><?php echo formatDateTime($ticket['geplant_datum']); ?></div></div>
                <div><span class="label">Bearbeitungszeit</span><div class="value"><?php echo $ticket['bearbeitungszeit_minuten'] !== null && $ticket['bearbeitungszeit_minuten'] !== '' ? (int)$ticket['bearbeitungszeit_minuten'] . ' Min' : '–'; ?></div></div>
                <div><span class="label">Erstellt von</span><div class="value"><?php echo h(trim(($ticket['ersteller_vorname'] ?? '') . ' ' . ($ticket['ersteller_nachname'] ?? '')) ?: '–'); ?></div></div>
                <div><span class="label">Zugewiesen an</span><div class="value"><?php echo h(trim(($ticket['zugewiesen_vorname'] ?? '') . ' ' . ($ticket['zugewiesen_nachname'] ?? '')) ?: '–'); ?></div></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Firma</div>
            <div class="grid-2">
                <div><span class="label">Name</span><div class="value"><?php echo h($ticket['company_name'] ?: '–'); ?></div></div>
                <div><span class="label">Ansprechpartner</span><div class="value"><?php
                    $ap = trim(($ticket['company_ansprechpartner_vorname'] ?? '') . ' ' . ($ticket['company_ansprechpartner_nachname'] ?? ''));
                    if ($ap) echo h($ap); elseif (!empty($ticket['company_ansprechpartner_manuell_name'])) echo h($ticket['company_ansprechpartner_manuell_name']); else echo '–';
                ?></div></div>
                <div><span class="label">Adresse</span><div class="value"><?php echo h($ticket['company_adresse'] ?: '–'); ?></div></div>
                <div><span class="label">PLZ / Ort</span><div class="value"><?php echo h(trim(($ticket['company_plz'] ?? '') . ' ' . ($ticket['company_ort'] ?? '')) ?: '–'); ?></div></div>
                <div><span class="label">E-Mail</span><div class="value"><?php echo h($ticket['company_email'] ?: ($ticket['company_ansprechpartner_email'] ?? $ticket['company_ansprechpartner_manuell_email'] ?? '–')); ?></div></div>
                <div><span class="label">Telefon</span><div class="value"><?php echo h($ticket['company_telefon'] ?: ($ticket['company_ansprechpartner_telefon'] ?? $ticket['company_ansprechpartner_manuell_telefon'] ?? '–')); ?></div></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Kunde</div>
            <div class="grid-2">
                <div><span class="label">Name</span><div class="value"><?php echo h($ticket['customer_name'] ?: '–'); ?></div></div>
                <div><span class="label">Kundennummer</span><div class="value"><?php echo h($ticket['customer_kundennummer'] ?? '–'); ?></div></div>
                <div><span class="label">Ansprechpartner</span><div class="value"><?php
                    $cap = trim(($ticket['customer_ansprechpartner_vorname'] ?? '') . ' ' . ($ticket['customer_ansprechpartner_nachname'] ?? ''));
                    if ($cap) echo h($cap); elseif (!empty($ticket['customer_ansprechpartner_manuell_name'])) echo h($ticket['customer_ansprechpartner_manuell_name']); else echo '–';
                ?></div></div>
                <div><span class="label">Adresse</span><div class="value"><?php echo h($ticket['customer_adresse'] ?: '–'); ?></div></div>
                <div><span class="label">PLZ / Ort</span><div class="value"><?php echo h(trim(($ticket['customer_plz'] ?? '') . ' ' . ($ticket['customer_ort'] ?? '')) ?: '–'); ?></div></div>
                <div><span class="label">E-Mail</span><div class="value"><?php echo h($ticket['customer_email'] ?: ($ticket['customer_ansprechpartner_email'] ?? $ticket['customer_ansprechpartner_manuell_email'] ?? '–')); ?></div></div>
                <div><span class="label">Telefon</span><div class="value"><?php echo h($ticket['customer_telefon'] ?: ($ticket['customer_ansprechpartner_telefon'] ?? $ticket['customer_ansprechpartner_manuell_telefon'] ?? '–')); ?></div></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Gerät</div>
            <div class="grid-2">
                <div><span class="label">Name</span><div class="value"><?php echo h($ticket['device_name'] ?: '–'); ?></div></div>
                <div><span class="label">Typ</span><div class="value"><?php echo h($ticket['device_typ'] ?: '–'); ?></div></div>
                <div><span class="label">Hersteller / Modell</span><div class="value"><?php echo h(trim(($ticket['device_hersteller'] ?? '') . ' ' . ($ticket['device_modell'] ?? '')) ?: '–'); ?></div></div>
                <div><span class="label">Seriennummer</span><div class="value"><?php echo h($ticket['device_seriennummer'] ?? '–'); ?></div></div>
                <div><span class="label">MAC-Adresse</span><div class="value"><?php echo h($ticket['device_mac_adresse'] ?? '–'); ?></div></div>
                <div><span class="label">IP-Adresse</span><div class="value"><?php echo h($ticket['device_ip_adresse'] ?? '–'); ?></div></div>
                <div><span class="label">Betriebssystem</span><div class="value"><?php echo h($ticket['device_betriebssystem'] ?? '–'); ?></div></div>
                <div><span class="label">Beschreibung</span><div class="value"><?php echo nl2br(h($ticket['device_beschreibung'] ?? '–')); ?></div></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Nachrichten &amp; Verlauf</div>
            <?php
            if (empty($comments)) {
                echo '<div class="value" style="color:#6b7280;">Keine Nachrichten.</div>';
            } else {
                foreach ($comments as $c) {
                    $author = trim(($c['vorname'] ?? '') . ' ' . ($c['nachname'] ?? ''));
                    if (!$author) $author = $c['email'] ?? 'Unbekannt';
                    $typ = $nachrichtentypLabels[$c['nachrichtentyp']] ?? $c['nachrichtentyp'];
                    $intern = !empty($c['ist_intern']);
                    ?>
                    <div class="comment <?php echo $intern ? 'intern' : ''; ?>">
                        <div class="comment-header">
                            <span class="comment-type"><?php echo h($typ); ?></span>
                            <?php echo h($author); ?> · <?php echo formatDateTime($c['erstellt_datum']); ?>
                            <?php if ($intern) echo ' <em>(intern)</em>'; ?>
                        </div>
                        <div class="comment-body"><?php echo nl2br(h($c['kommentar'])); ?></div>
                        <?php
                        $attachments = $commentAttachmentsMap[$c['id']] ?? [];
                        if (!empty($attachments)) :
                            echo '<div class="attachments">';
                            foreach ($attachments as $att) {
                                $attUrl = $baseUrl . ltrim($att['dateipfad'], '/');
                                $name = h($att['dateiname'] ?: 'Anhang');
                                if (isImageMime($att['mime_type'])) {
                                    echo '<img class="attachment-img" src="' . h($attUrl) . '" alt="' . $name . '" />';
                                } else {
                                    echo '<a class="attachment-link" href="' . h($attUrl) . '" target="_blank">' . (isPdfMime($att['mime_type']) ? 'PDF: ' : '') . $name . '</a>';
                                }
                            }
                            echo '</div>';
                        endif;
                        ?>
                    </div>
                    <?php
                }
            }
            ?>
        </div>

        <div class="no-print" id="no-print-actions">
            <button type="button" class="btn" onclick="window.print();">Als PDF speichern / Drucken</button>
        </div>
    </div>
    <script>
        (function() {
            var autoPrint = <?php echo (isset($_GET['print']) && $_GET['print'] === '1') ? 'true' : 'false'; ?>;
            if (autoPrint) {
                document.getElementById('no-print-actions').style.display = 'none';
                window.onload = function() { window.print(); };
            }
        })();
    </script>
</body>
</html>
