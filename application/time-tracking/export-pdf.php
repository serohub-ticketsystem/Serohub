<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateTimeLocal($value) {
    if (empty($value)) {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : '-';
}

function formatDateLocal($value) {
    if (empty($value)) {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y', $timestamp) : '-';
}

function formatDurationMinutes($minutes) {
    $minutes = (int)$minutes;
    if ($minutes <= 0) {
        return '0h 0m';
    }
    $hours = intdiv($minutes, 60);
    $restMinutes = $minutes % 60;
    return $hours . 'h ' . $restMinutes . 'm';
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT id, rolle, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankfehler beim Laden des Benutzers.');
}

if (!$currentUser || ($currentUser['rolle'] !== 'Techniker' && $currentUser['rolle'] !== 'Admin')) {
    showPermissionDeniedPage();
}

$isAdmin = $currentUser['rolle'] === 'Admin';
$viewUserId = $currentUserId;

if ($isAdmin && isset($_GET['view_user_id']) && $_GET['view_user_id'] !== '') {
    $requestedViewUserId = (int)$_GET['view_user_id'];
    if ($requestedViewUserId > 0) {
        $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND (rolle = 'Admin' OR rolle = 'Techniker') LIMIT 1");
        $userCheckStmt->bindValue(':id', $requestedViewUserId, PDO::PARAM_INT);
        $userCheckStmt->execute();
        if ($userCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            $viewUserId = $requestedViewUserId;
        }
    }
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-t');
}
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$targetUser = null;
$targetCompanyName = '';
$targetCompanyLogoUrl = '';
try {
    $targetUserStmt = $pdo->prepare("
        SELECT
            u.id,
            u.vorname,
            u.nachname,
            u.company_id,
            c.name AS company_name,
            c.logo AS company_logo
        FROM users u
        LEFT JOIN companies c ON c.id = u.company_id
        WHERE u.id = :id
        LIMIT 1
    ");
    $targetUserStmt->bindValue(':id', $viewUserId, PDO::PARAM_INT);
    $targetUserStmt->execute();
    $targetUser = $targetUserStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$entries = [];
try {
    $timeStmt = $pdo->prepare("
        SELECT id, DATE(start_time) AS date, start_time, end_time, duration_minutes, description, 'time' AS entry_type
        FROM time_tracking
        WHERE user_id = :user_id
          AND DATE(start_time) BETWEEN :date_from AND :date_to
        ORDER BY start_time ASC
    ");
    $timeStmt->bindValue(':user_id', $viewUserId, PDO::PARAM_INT);
    $timeStmt->bindValue(':date_from', $dateFrom);
    $timeStmt->bindValue(':date_to', $dateTo);
    $timeStmt->execute();
    $timeEntries = $timeStmt->fetchAll(PDO::FETCH_ASSOC);

    $vacations = [];
    try {
        $vacationStmt = $pdo->prepare("
            SELECT id, date, hours, type
            FROM time_tracking_vacation
            WHERE user_id = :user_id
              AND date BETWEEN :date_from AND :date_to
            ORDER BY date ASC
        ");
        $vacationStmt->bindValue(':user_id', $viewUserId, PDO::PARAM_INT);
        $vacationStmt->bindValue(':date_from', $dateFrom);
        $vacationStmt->bindValue(':date_to', $dateTo);
        $vacationStmt->execute();
        $vacationsRaw = $vacationStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vacationsRaw as $vacation) {
            $hours = (float)$vacation['hours'];
            $label = 'Abwesenheit';
            if ($vacation['type'] === 'vacation') {
                $label = 'Urlaub';
            } elseif ($vacation['type'] === 'sick') {
                $label = 'Krank';
            } elseif ($vacation['type'] === 'holiday') {
                $label = 'Feiertag';
            } elseif ($vacation['type'] === 'school') {
                $label = 'Berufsschule';
            }

            $vacations[] = [
                'id' => $vacation['id'],
                'date' => $vacation['date'],
                'start_time' => $vacation['date'] . ' 00:00:00',
                'end_time' => $vacation['date'] . ' ' . str_pad((string)(int)$hours, 2, '0', STR_PAD_LEFT) . ':00:00',
                'duration_minutes' => (int)round($hours * 60),
                'description' => $label,
                'entry_type' => 'vacation',
                'vacation_type' => $vacation['type'],
            ];
        }
    } catch (PDOException $e) {
        $vacations = [];
    }

    $entries = array_merge($timeEntries, $vacations);
    usort($entries, function ($a, $b) {
        $timeA = strtotime((string)($a['start_time'] ?? '')) ?: 0;
        $timeB = strtotime((string)($b['start_time'] ?? '')) ?: 0;
        return $timeA <=> $timeB;
    });
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankfehler beim Laden der Zeiteinträge.');
}

$workMinutes = 0;
$absenceMinutes = 0;
$days = [];
foreach ($entries as $entry) {
    $minutes = (int)($entry['duration_minutes'] ?? 0);
    if (($entry['entry_type'] ?? '') === 'vacation') {
        $absenceMinutes += $minutes;
    } else {
        $workMinutes += $minutes;
    }
    if (!empty($entry['date'])) {
        $days[(string)$entry['date']] = true;
    }
}
$totalMinutes = $workMinutes + $absenceMinutes;
$totalDays = count($days);

$baseUrl = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/';
$pdfLogoPath = 'assets/images/Serohub_Icon.png';
$pdfNamePart1 = 'Serohub';
$pdfNamePart2 = '';
try {
    $brandStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_logo', 'branding_name_part1', 'branding_name_part2')");
    $brandStmt->execute();
    foreach ($brandStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['setting_key'] === 'branding_logo' && !empty(trim((string)($row['setting_value'] ?? '')))) {
            $pdfLogoPath = trim((string)$row['setting_value']);
        } elseif ($row['setting_key'] === 'branding_name_part1' && $row['setting_value'] !== null) {
            $pdfNamePart1 = trim((string)$row['setting_value']);
        } elseif ($row['setting_key'] === 'branding_name_part2' && $row['setting_value'] !== null) {
            $pdfNamePart2 = trim((string)$row['setting_value']);
        }
    }
} catch (PDOException $e) {
}

$pdfNamePart1 = trim((string)$pdfNamePart1);
$pdfNamePart2 = trim((string)$pdfNamePart2);
if ($pdfNamePart1 === '' && $pdfNamePart2 === '') {
    $pdfNamePart1 = 'Serohub';
}
$pdfAppName = trim($pdfNamePart1 . ' ' . $pdfNamePart2);
$pdfLogoUrl = $pdfLogoPath ? ($baseUrl . ltrim($pdfLogoPath, '/')) : '';
$targetUserName = trim((string)($targetUser['vorname'] ?? '') . ' ' . (string)($targetUser['nachname'] ?? ''));
if ($targetUserName === '') {
    $targetUserName = 'Unbekannter Benutzer';
}
if ($targetUser) {
    $targetCompany = [
        'name' => $targetUser['company_name'] ?? null,
    ];
    decrypt_company_row($targetCompany);
    $targetCompanyName = trim((string)($targetCompany['name'] ?? ''));

    $targetCompanyLogo = trim((string)($targetUser['company_logo'] ?? ''));
    if ($targetCompanyLogo !== '') {
        if (str_starts_with($targetCompanyLogo, 'http://') || str_starts_with($targetCompanyLogo, 'https://') || str_starts_with($targetCompanyLogo, '/')) {
            $targetCompanyLogoUrl = $targetCompanyLogo;
        } else {
            $targetCompanyLogoUrl = $baseUrl . ltrim($targetCompanyLogo, '/');
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pdfAppName); ?> - Zeiterfassung Export</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }
        .container {
            max-width: 1050px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .header {
            padding: 24px 28px;
            border-bottom: 2px solid #0f172a;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo {
            height: 44px;
            max-width: 180px;
            width: auto;
            object-fit: contain;
        }
        .header-title {
            margin: 0;
            font-size: 22px;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header-subtitle {
            margin: 2px 0 0 0;
            font-size: 13px;
            color: #475569;
        }
        .header-company {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            max-width: 320px;
        }
        .header-company-logo {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            object-fit: contain;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .header-company-label {
            margin: 0;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }
        .header-company-name {
            margin: 1px 0 0 0;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .content {
            padding: 24px 28px 18px 28px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 24px;
            margin-bottom: 20px;
        }
        .meta-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .meta-value {
            font-size: 14px;
            color: #111827;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
        }
        .stat-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-value {
            font-size: 19px;
            color: #0f172a;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        thead tr {
            background: #f1f5f9;
        }
        th, td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 12px;
        }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
        }
        tbody tr:nth-child(even) {
            background: #fbfdff;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .type-work {
            color: #0f766e;
            background: #ccfbf1;
        }
        .type-vacation {
            color: #1d4ed8;
            background: #dbeafe;
        }
        .empty {
            padding: 18px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
        }
        .actions {
            padding: 0 28px 22px 28px;
        }
        .btn {
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }
            .container {
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
            }
            .actions {
                display: none !important;
            }
            th, td {
                font-size: 11px;
                padding: 8px 10px;
            }
            .header-company {
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <?php if ($pdfLogoUrl) : ?>
                <img src="<?php echo h($pdfLogoUrl); ?>" alt="<?php echo h($pdfAppName); ?>" class="logo">
            <?php endif; ?>
            <div>
                <h1 class="header-title">Zeiterfassung Export</h1>
                <p class="header-subtitle"><?php echo h($pdfAppName); ?></p>
            </div>
            <?php if ($targetCompanyName !== '' || $targetCompanyLogoUrl !== '') : ?>
                <div class="header-company">
                    <?php if ($targetCompanyLogoUrl !== '') : ?>
                        <img src="<?php echo h($targetCompanyLogoUrl); ?>" alt="<?php echo h($targetCompanyName !== '' ? $targetCompanyName : 'Firma'); ?>" class="header-company-logo">
                    <?php endif; ?>
                    <div>
                        <p class="header-company-label">Firma</p>
                        <p class="header-company-name"><?php echo h($targetCompanyName !== '' ? $targetCompanyName : 'Nicht zugewiesen'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </header>

        <section class="content">
            <div class="meta-grid">
                <div>
                    <div class="meta-label">Mitarbeiter</div>
                    <div class="meta-value"><?php echo h($targetUserName); ?></div>
                </div>
                <div>
                    <div class="meta-label">Firma</div>
                    <div class="meta-value"><?php echo h($targetCompanyName !== '' ? $targetCompanyName : 'Nicht zugewiesen'); ?></div>
                </div>
                <div>
                    <div class="meta-label">Exportiert am</div>
                    <div class="meta-value"><?php echo h(date('d.m.Y H:i')); ?></div>
                </div>
                <div>
                    <div class="meta-label">Zeitraum von</div>
                    <div class="meta-value"><?php echo h(formatDateLocal($dateFrom)); ?></div>
                </div>
                <div>
                    <div class="meta-label">Zeitraum bis</div>
                    <div class="meta-value"><?php echo h(formatDateLocal($dateTo)); ?></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Gesamtzeit</div>
                    <div class="stat-value"><?php echo h(formatDurationMinutes($totalMinutes)); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Arbeitszeit</div>
                    <div class="stat-value"><?php echo h(formatDurationMinutes($workMinutes)); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Abwesenheit</div>
                    <div class="stat-value"><?php echo h(formatDurationMinutes($absenceMinutes)); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Erfasste Tage</div>
                    <div class="stat-value"><?php echo h((string)$totalDays); ?></div>
                </div>
            </div>

            <?php if (empty($entries)) : ?>
                <div class="empty">Für den gewählten Zeitraum wurden keine Zeiteinträge gefunden.</div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Start</th>
                            <th>Ende</th>
                            <th>Dauer</th>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $entry) : ?>
                        <?php $isVacation = ($entry['entry_type'] ?? '') === 'vacation'; ?>
                        <tr>
                            <td><?php echo h(formatDateLocal($entry['date'] ?? '')); ?></td>
                            <td><?php echo $isVacation ? '-' : h(formatDateTimeLocal($entry['start_time'] ?? '')); ?></td>
                            <td><?php echo $isVacation ? '-' : h(formatDateTimeLocal($entry['end_time'] ?? '')); ?></td>
                            <td><?php echo h(formatDurationMinutes((int)($entry['duration_minutes'] ?? 0))); ?></td>
                            <td>
                                <span class="type-badge <?php echo $isVacation ? 'type-vacation' : 'type-work'; ?>">
                                    <?php echo $isVacation ? 'Abwesenheit' : 'Arbeit'; ?>
                                </span>
                            </td>
                            <td><?php echo h($entry['description'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <div class="actions" id="actions">
            <button type="button" class="btn" onclick="window.print();">Als PDF speichern / Drucken</button>
        </div>
    </div>

    <script>
        (function() {
            var autoPrint = <?php echo (isset($_GET['print']) && $_GET['print'] === '1') ? 'true' : 'false'; ?>;
            if (autoPrint) {
                var actions = document.getElementById('actions');
                if (actions) {
                    actions.style.display = 'none';
                }
                window.onload = function() {
                    window.print();
                };
            }
        })();
    </script>
</body>
</html>
