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
    
    // Nur Admins und Techniker können Zeiterfassung nutzen
    if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Für Admins: Zielbenutzer für Anzeige (nur Lesen); leer = eigener Benutzer
$viewUserId = $userId;
if ($userRole === 'Admin' && isset($_GET['view_user_id']) && $_GET['view_user_id'] !== '') {
    $requestedViewId = (int) $_GET['view_user_id'];
    if ($requestedViewId > 0) {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND (rolle = 'Admin' OR rolle = 'Techniker') LIMIT 1");
        $checkStmt->bindValue(':id', $requestedViewId, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $viewUserId = $requestedViewId;
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            // Kollegen-Liste (nur Admins, für Dropdown)
            if (isset($_GET['list_colleagues']) && $_GET['list_colleagues'] == '1' && $userRole === 'Admin') {
                $colleaguesStmt = $pdo->prepare("
                    SELECT id, vorname, nachname 
                    FROM users 
                    WHERE (rolle = 'Admin' OR rolle = 'Techniker') 
                    ORDER BY nachname, vorname
                ");
                $colleaguesStmt->execute();
                $colleagues = $colleaguesStmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $colleagues]);
                break;
            }
            // Aktuellen Status abrufen oder alle Zeiten
            if (isset($_GET['status'])) {
                // Prüfen ob gerade eine Zeit läuft
                $stmt = $pdo->prepare("
                    SELECT id, user_id, start_time, end_time, duration_minutes, description
                    FROM time_tracking
                    WHERE user_id = :user_id AND end_time IS NULL
                    ORDER BY start_time DESC
                    LIMIT 1
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $activeEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $todayCompletedMinutes = getTodayCompletedMinutes($pdo, $userId);
                $minutesPerDay = getUserMinutesPerDay($pdo, $userId);
                $todayTotalMinutes = getTodayTotalMinutes($pdo, $userId, $activeEntry);

                echo json_encode([
                    'success' => true,
                    'isRunning' => !empty($activeEntry),
                    'entry' => $activeEntry,
                    'today_completed_minutes' => $todayCompletedMinutes,
                    'today_total_minutes' => $todayTotalMinutes,
                    'minutes_per_day' => $minutesPerDay,
                ]);
            } elseif (isset($_GET['year_overview']) && $_GET['year_overview'] == '1') {
                // Jahresübersicht direkt abrufen (ohne date_from/date_to)
                $year = $_GET['year'] ?? date('Y');
                $yearData = getYearOverview($pdo, $viewUserId, $year);
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'data' => $yearData
                ]);
            } elseif (isset($_GET['stats']) && $_GET['stats'] == 'year') {
                // Statistiken für ein bestimmtes Jahr (nur bis zum heutigen Datum)
                $year = $_GET['year'] ?? date('Y');
                $dateFrom = $year . '-01-01';
                // Nur bis zum heutigen Datum, nicht bis zum Jahresende
                $dateTo = date('Y-m-d');
                // Wenn das Jahr nicht das aktuelle Jahr ist, dann bis zum Jahresende
                $currentYear = date('Y');
                if ($year < $currentYear) {
                    $dateTo = $year . '-12-31';
                }
                $stats = calculateTimeStats($pdo, $viewUserId, $dateFrom, $dateTo);
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'stats' => $stats
                ]);
            } elseif (isset($_GET['stats']) && $_GET['stats'] == 'total') {
                // Statistiken insgesamt (ab Anstellungsdatum, nur bis zum heutigen Datum)
                // Anstellungsdatum aus Einstellungen abrufen
                $settingsStmt = $pdo->prepare("
                    SELECT setting_value 
                    FROM user_settings 
                    WHERE user_id = :user_id AND setting_key = 'employment_start_date'
                ");
                $settingsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $settingsStmt->execute();
                $startDateResult = $settingsStmt->fetch(PDO::FETCH_ASSOC);
                
                // Standard: 2020-01-01 oder Anstellungsdatum
                $dateFrom = $startDateResult['setting_value'] ?? '2020-01-01';
                if (empty($dateFrom)) {
                    $dateFrom = '2020-01-01';
                }
                
                // Nur bis zum heutigen Datum, nicht in die Zukunft
                $dateTo = date('Y-m-d');
                $stats = calculateTimeStats($pdo, $viewUserId, $dateFrom, $dateTo);
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'start_date' => $dateFrom
                ]);
            } elseif (isset($_GET['stats']) && $_GET['stats'] == 'monthly') {
                // Monatliche Statistiken für visuelle Darstellung
                $year = $_GET['year'] ?? date('Y');
                $yearStart = $year . '-01-01';
                $yearEnd = $year . '-12-31';
                $currentDate = date('Y-m-d');
                
                // Wenn es das aktuelle Jahr ist, nur bis heute
                if ($year == date('Y')) {
                    $yearEnd = $currentDate;
                }
                
                // Hole monatliche Daten
                $monthlyData = [];
                $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                          'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                
                for ($month = 1; $month <= 12; $month++) {
                    $monthStart = sprintf('%04d-%02d-01', $year, $month);
                    $monthEnd = sprintf('%04d-%02d-%d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
                    
                    // Wenn es das aktuelle Jahr und Monat ist, nur bis heute
                    if ($year == date('Y') && $month == date('n')) {
                        $monthEnd = $currentDate;
                    }
                    
                    // Nur Monate bis zum aktuellen Datum laden
                    if ($monthStart <= $yearEnd) {
                        $monthStats = calculateTimeStats($pdo, $viewUserId, $monthStart, $monthEnd);
                        $monthlyData[] = [
                            'month' => $month,
                            'monthName' => $months[$month - 1],
                            'total_hours' => round($monthStats['total_minutes'] / 60, 2),
                            'total_minutes' => $monthStats['total_minutes'],
                            'soll_hours' => round($monthStats['soll_minutes'] / 60, 2),
                            'soll_minutes' => $monthStats['soll_minutes'],
                            'overtime_hours' => $monthStats['overtime_hours']
                        ];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'data' => $monthlyData
                ]);
            } else {
                // Alle Zeiten abrufen
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Erster Tag des Monats
                $dateTo = $_GET['date_to'] ?? date('Y-m-t'); // Letzter Tag des Monats
                
                // Zeiteinträge abrufen
                $stmt = $pdo->prepare("
                    SELECT id, user_id, start_time, end_time, duration_minutes, description,
                           DATE(start_time) as date, 'time' as entry_type
                    FROM time_tracking
                    WHERE user_id = :user_id 
                    AND DATE(start_time) BETWEEN :date_from AND :date_to
                    ORDER BY date DESC, start_time DESC
                ");
                $stmt->bindValue(':user_id', $viewUserId, PDO::PARAM_INT);
                $stmt->bindValue(':date_from', $dateFrom);
                $stmt->bindValue(':date_to', $dateTo);
                $stmt->execute();
                $times = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Urlaubstage abrufen
                $vacations = [];
                try {
                    $vacationStmt = $pdo->prepare("
                        SELECT id, user_id, date, hours, type
                        FROM time_tracking_vacation
                        WHERE user_id = :user_id 
                        AND date BETWEEN :date_from AND :date_to
                    ");
                    $vacationStmt->bindValue(':user_id', $viewUserId, PDO::PARAM_INT);
                    $vacationStmt->bindValue(':date_from', $dateFrom);
                    $vacationStmt->bindValue(':date_to', $dateTo);
                    $vacationStmt->execute();
                    $vacationsRaw = $vacationStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Urlaubstage für die Anzeige formatieren
                    foreach ($vacationsRaw as $vacation) {
                        $vacations[] = [
                            'id' => $vacation['id'],
                            'user_id' => $vacation['user_id'],
                            'date' => $vacation['date'],
                            'start_time' => $vacation['date'] . ' 00:00:00',
                            'end_time' => $vacation['date'] . ' ' . str_pad((int)$vacation['hours'], 2, '0', STR_PAD_LEFT) . ':00:00',
                            'duration_minutes' => (int)($vacation['hours'] * 60),
                            'hours' => (float)$vacation['hours'],
                            'type' => $vacation['type'],
                            'description' => $vacation['type'] === 'vacation' ? 'Urlaub' : 
                                           ($vacation['type'] === 'sick' ? 'Krank' : 
                                           ($vacation['type'] === 'holiday' ? 'Feiertag' : 
                                           ($vacation['type'] === 'school' ? 'Berufsschule' : 'Sonstiges'))),
                            'entry_type' => 'vacation'
                        ];
                    }
                } catch (PDOException $e) {
                    // Tabelle existiert möglicherweise noch nicht - einfach mit leerem Array weitermachen
                    error_log("Urlaubstage-Abfrage Fehler: " . $e->getMessage());
                    $vacations = [];
                }
                
                // Beide kombinieren und sortieren
                $allEntries = array_merge($times, $vacations);
                
                // Nach Datum sortieren (neueste zuerst)
                usort($allEntries, function($a, $b) {
                    try {
                        $dateA = isset($a['date']) ? $a['date'] : '';
                        if (!$dateA && isset($a['start_time'])) {
                            $dateA = date('Y-m-d', strtotime($a['start_time']));
                        }
                        
                        $dateB = isset($b['date']) ? $b['date'] : '';
                        if (!$dateB && isset($b['start_time'])) {
                            $dateB = date('Y-m-d', strtotime($b['start_time']));
                        }
                        
                        if ($dateA === $dateB) {
                            $timeA = isset($a['start_time']) ? strtotime($a['start_time']) : 0;
                            $timeB = isset($b['start_time']) ? strtotime($b['start_time']) : 0;
                            return $timeB <=> $timeA;
                        }
                        return $dateB <=> $dateA;
                    } catch (Exception $e) {
                        error_log("Sortierung Fehler: " . $e->getMessage());
                        return 0;
                    }
                });
                
                // Statistiken berechnen
                $stats = calculateTimeStats($pdo, $viewUserId, $dateFrom, $dateTo);
                
                echo json_encode([
                    'success' => true,
                    'times' => $allEntries,
                    'stats' => $stats,
                    'view_user_id' => $viewUserId
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';
            
            if ($action === 'start') {
                // Prüfen ob bereits eine Zeit läuft
                $stmt = $pdo->prepare("
                    SELECT id, start_time FROM time_tracking
                    WHERE user_id = :user_id AND end_time IS NULL
                    ORDER BY start_time DESC
                    LIMIT 1
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $activeEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($activeEntry) {
                    // Prüfe, ob die laufende Zeiterfassung sehr alt ist (älter als 24 Stunden)
                    // Wenn ja, beende sie automatisch
                    $startTime = new DateTime($activeEntry['start_time']);
                    $now = new DateTime();
                    $diffHours = ($now->getTimestamp() - $startTime->getTimestamp()) / 3600;
                    
                    // Wenn älter als 24 Stunden ODER länger als einen normalen Arbeitstag (z.B. mehr als 10 Stunden)
                    if ($diffHours > 24 || $diffHours > 10) {
                        // Alte hängende Zeiterfassung automatisch beenden
                        // Berechne Dauer basierend auf Startzeit und jetzt (maximal 8 Stunden pro Tag)
                        $durationMinutes = min(480, ceil(($now->getTimestamp() - $startTime->getTimestamp()) / 60)); // Max 8 Stunden
                        
                        $updateStmt = $pdo->prepare("
                            UPDATE time_tracking
                            SET end_time = :end_time,
                                duration_minutes = :duration_minutes
                            WHERE id = :id
                        ");
                        $updateStmt->bindValue(':id', $activeEntry['id'], PDO::PARAM_INT);
                        $updateStmt->bindValue(':end_time', $now->format('Y-m-d H:i:s'));
                        $updateStmt->bindValue(':duration_minutes', $durationMinutes, PDO::PARAM_INT);
                        $updateStmt->execute();
                        
                        // Log für Debugging
                        error_log("Alte hängende Zeiterfassung automatisch beendet (ID: {$activeEntry['id']}, Dauer: {$diffHours} Stunden)");
                        
                        // Jetzt können wir eine neue Zeiterfassung starten
                    } else {
                        // Es läuft noch eine aktive Zeiterfassung
                        http_response_code(400);
                        echo json_encode([
                            'success' => false, 
                            'error' => 'Zeiterfassung läuft bereits seit ' . round($diffHours, 1) . ' Stunden. Bitte beenden Sie die laufende Zeiterfassung zuerst.'
                        ]);
                        exit;
                    }
                }
                
                // Neue Zeiterfassung starten
                $description = $data['description'] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO time_tracking (user_id, start_time, description)
                    VALUES (:user_id, NOW(), :description)
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':description', $description);
                $stmt->execute();
                
                $entryId = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Zeiterfassung gestartet',
                    'id' => $entryId
                ]);
                
            } elseif ($action === 'stop') {
                // Aktive Zeiterfassung beenden
                $stmt = $pdo->prepare("
                    SELECT id, start_time FROM time_tracking
                    WHERE user_id = :user_id AND end_time IS NULL
                    ORDER BY start_time DESC
                    LIMIT 1
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $activeEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$activeEntry) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Keine laufende Zeiterfassung gefunden']);
                    exit;
                }
                
                $description = $data['description'] ?? null;
                
                // Dauer berechnen (inkl. Sekunden, aufgerundet)
                $startTime = new DateTime($activeEntry['start_time']);
                $endTime = new DateTime();
                $diffSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
                // Auf Minuten aufrunden (mindestens 1 Minute wenn Zeit läuft)
                $durationMinutes = max(1, ceil($diffSeconds / 60));
                
                // Zeiterfassung beenden
                $updateStmt = $pdo->prepare("
                    UPDATE time_tracking
                    SET end_time = NOW(),
                        duration_minutes = :duration_minutes,
                        description = COALESCE(:description, description)
                    WHERE id = :id
                ");
                $updateStmt->bindValue(':id', $activeEntry['id'], PDO::PARAM_INT);
                $updateStmt->bindValue(':duration_minutes', $durationMinutes, PDO::PARAM_INT);
                $updateStmt->bindValue(':description', $description);
                $updateStmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Zeiterfassung beendet',
                    'duration_minutes' => $durationMinutes
                ]);
                
            } elseif ($action === 'add') {
                // Manuelles Eintragen von Zeit
                $startTime = $data['start_time'] ?? null;
                $endTime = $data['end_time'] ?? null;
                $description = $data['description'] ?? null;
                
                if (!$startTime || !$endTime) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Start- und Endzeit sind erforderlich']);
                    exit;
                }
                
                // Validierung der Zeiten
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                
                if ($end <= $start) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Endzeit muss nach Startzeit liegen']);
                    exit;
                }
                
                // Dauer berechnen
                $diffSeconds = $end->getTimestamp() - $start->getTimestamp();
                $durationMinutes = ceil($diffSeconds / 60);
                
                // Zeiteintrag speichern
                $stmt = $pdo->prepare("
                    INSERT INTO time_tracking (user_id, start_time, end_time, duration_minutes, description)
                    VALUES (:user_id, :start_time, :end_time, :duration_minutes, :description)
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':start_time', $startTime);
                $stmt->bindValue(':end_time', $endTime);
                $stmt->bindValue(':duration_minutes', $durationMinutes, PDO::PARAM_INT);
                $stmt->bindValue(':description', $description);
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Zeiteintrag hinzugefügt',
                    'duration_minutes' => $durationMinutes
                ]);
                
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
            }
            break;
            
        case 'PUT':
            // Zeiteintrag bearbeiten
            $data = json_decode(file_get_contents('php://input'), true);
            $entryId = $data['id'] ?? null;
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $description = $data['description'] ?? null;
            
            if (!$entryId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            if (!$startTime || !$endTime) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Start- und Endzeit sind erforderlich']);
                exit;
            }
            
            // Prüfen ob Eintrag dem Benutzer gehört
            $stmt = $pdo->prepare("SELECT id FROM time_tracking WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$entry) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Eintrag nicht gefunden']);
                exit;
            }
            
            // Validierung der Zeiten
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            
            if ($end <= $start) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Endzeit muss nach Startzeit liegen']);
                exit;
            }
            
            // Dauer berechnen
            $diffSeconds = $end->getTimestamp() - $start->getTimestamp();
            $durationMinutes = ceil($diffSeconds / 60);
            
            // Zeiteintrag aktualisieren
            $updateStmt = $pdo->prepare("
                UPDATE time_tracking
                SET start_time = :start_time,
                    end_time = :end_time,
                    duration_minutes = :duration_minutes,
                    description = :description
                WHERE id = :id
            ");
            $updateStmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $updateStmt->bindValue(':start_time', $startTime);
            $updateStmt->bindValue(':end_time', $endTime);
            $updateStmt->bindValue(':duration_minutes', $durationMinutes, PDO::PARAM_INT);
            $updateStmt->bindValue(':description', $description);
            $updateStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Zeiteintrag aktualisiert',
                'duration_minutes' => $durationMinutes
            ]);
            break;
            
        case 'DELETE':
            // Zeiterfassungseintrag löschen
            $data = json_decode(file_get_contents('php://input'), true);
            $entryId = $data['id'] ?? null;
            
            if (!$entryId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            // Prüfen ob Eintrag dem Benutzer gehört
            $stmt = $pdo->prepare("SELECT id FROM time_tracking WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$entry) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Eintrag nicht gefunden']);
                exit;
            }
            
            $deleteStmt = $pdo->prepare("DELETE FROM time_tracking WHERE id = :id");
            $deleteStmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Eintrag gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}

function getUserMinutesPerDay($pdo, $userId) {
    $settingsStmt = $pdo->prepare("
        SELECT setting_key, setting_value FROM user_settings
        WHERE user_id = :user_id AND setting_key IN ('weekly_hours', 'work_start_time', 'work_end_time')
    ");
    $settingsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $settingsStmt->execute();
    $settingsResults = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [];
    foreach ($settingsResults as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $weeklyHours = isset($settings['weekly_hours']) ? (float)$settings['weekly_hours'] : 40.0;
    $workStartTime = $settings['work_start_time'] ?? '08:00';
    $workEndTime = $settings['work_end_time'] ?? '17:00';

    $startParts = explode(':', $workStartTime);
    $endParts = explode(':', $workEndTime);
    $startHour = (int)$startParts[0] + ((int)$startParts[1] / 60);
    $endHour = (int)$endParts[0] + ((int)$endParts[1] / 60);
    $workHoursPerDay = $endHour - $startHour;
    $calculatedHoursPerDay = $weeklyHours / 5;

    return round(min($workHoursPerDay, $calculatedHoursPerDay) * 60);
}

function getTodayCompletedMinutes($pdo, $userId) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, start_time, end_time))), 0) as total_minutes
        FROM time_tracking
        WHERE user_id = :user_id
        AND DATE(start_time) = :today
        AND end_time IS NOT NULL
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':today', $today);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int)($result['total_minutes'] ?? 0);
}

function getTodayActiveSessionMinutes($activeEntry) {
    if (empty($activeEntry['start_time'])) {
        return 0;
    }

    $today = date('Y-m-d');
    $start = new DateTime($activeEntry['start_time']);
    $now = new DateTime();
    $todayStart = new DateTime($today . ' 00:00:00');

    if ($start < $todayStart) {
        $start = $todayStart;
    }
    if ($start > $now) {
        return 0;
    }

    return max(0, (int) floor(($now->getTimestamp() - $start->getTimestamp()) / 60));
}

function getTodayTotalMinutes($pdo, $userId, $activeEntry = null) {
    $total = getTodayCompletedMinutes($pdo, $userId);
    if (!empty($activeEntry)) {
        $total += getTodayActiveSessionMinutes($activeEntry);
    }

    return $total;
}

// Hilfsfunktion zur Berechnung der Statistiken
function calculateTimeStats($pdo, $userId, $dateFrom, $dateTo) {
    // Gesamtstunden im Zeitraum
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(duration_minutes), 0) as total_minutes
        FROM time_tracking
        WHERE user_id = :user_id 
        AND DATE(start_time) BETWEEN :date_from AND :date_to
        AND end_time IS NOT NULL
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':date_from', $dateFrom);
    $stmt->bindValue(':date_to', $dateTo);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalMinutes = $result['total_minutes'] ?? 0;
    
    // Einstellungen abrufen (Wochenstunden und Arbeitszeiten)
    $settingsStmt = $pdo->prepare("
        SELECT setting_key, setting_value FROM user_settings 
        WHERE user_id = :user_id AND setting_key IN ('weekly_hours', 'work_start_time', 'work_end_time')
    ");
    $settingsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $settingsStmt->execute();
    $settingsResults = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settingsResults as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $weeklyHours = isset($settings['weekly_hours']) ? (float)$settings['weekly_hours'] : 40.0;
    $workStartTime = $settings['work_start_time'] ?? '08:00';
    $workEndTime = $settings['work_end_time'] ?? '17:00';
    
    // Arbeitszeit pro Tag berechnen (aus Start- und Endzeit)
    $startParts = explode(':', $workStartTime);
    $endParts = explode(':', $workEndTime);
    $startHour = (int)$startParts[0] + ((int)$startParts[1] / 60);
    $endHour = (int)$endParts[0] + ((int)$endParts[1] / 60);
    $workHoursPerDay = $endHour - $startHour;
    
    // Stunden pro Tag basierend auf Wochenstunden (5 Arbeitstage)
    $calculatedHoursPerDay = $weeklyHours / 5;
    
    // Verwende das Minimum (falls Arbeitszeit kürzer als benötigt)
    $hoursPerDay = min($workHoursPerDay, $calculatedHoursPerDay);
    $minutesPerDay = round($hoursPerDay * 60);
    
    // Arbeitstage im Zeitraum (Montag-Freitag)
    $workDays = getWorkDays($dateFrom, $dateTo);
    
    // Urlaubstage, Krankheitstage und Feiertage abrufen
    $vacationStmt = $pdo->prepare("
        SELECT date, hours, type
        FROM time_tracking_vacation
        WHERE user_id = :user_id 
        AND date BETWEEN :date_from AND :date_to
    ");
    $vacationStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $vacationStmt->bindValue(':date_from', $dateFrom);
    $vacationStmt->bindValue(':date_to', $dateTo);
    $vacationStmt->execute();
    $vacations = $vacationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Urlaubstage zählen und von Arbeitstagen abziehen
    $vacationDays = 0;
    $sickDays = 0;
    $holidayDays = 0;
    $vacationMinutes = 0;
    
    foreach ($vacations as $vacation) {
        $vacationDate = $vacation['date'];
        $dayOfWeek = date('N', strtotime($vacationDate)); // 1 (Montag) bis 7 (Sonntag)
        
        // Nur Arbeitstage zählen (Montag-Freitag)
        if ($dayOfWeek <= 5) {
            $vacationMinutes += (int)($vacation['hours'] * 60);
            
            if ($vacation['type'] === 'vacation') {
                $vacationDays++;
            } elseif ($vacation['type'] === 'sick') {
                $sickDays++;
            } elseif ($vacation['type'] === 'holiday') {
                $holidayDays++;
            } elseif ($vacation['type'] === 'school') {
                // Berufsschule wird wie Urlaub behandelt (keine Arbeitszeit)
            }
        }
    }
    
    // Zähle auch Berufsschule als Abwesenheit
    $schoolDays = 0;
    foreach ($vacations as $vacation) {
        if ($vacation['type'] === 'school') {
            $vacationDate = $vacation['date'];
            $dayOfWeek = date('N', strtotime($vacationDate));
            if ($dayOfWeek <= 5) {
                $schoolDays++;
            }
        }
    }
    
    // Arbeitstage anpassen: Von Arbeitstagen Urlaubstage, Krankheitstage, Feiertage und Berufsschule abziehen
    $effectiveWorkDays = $workDays - $vacationDays - $sickDays - $holidayDays - $schoolDays;
    // Sicherstellen, dass es nicht negativ wird
    $effectiveWorkDays = max(0, $effectiveWorkDays);
    
    // Sollstunden basierend auf effektiven Arbeitstagen
    // Urlaubstage, Krankheitstage, Feiertage und Berufsschule zählen als voll gearbeitete Tage (8h oder konfigurierte Stunden)
    $sollHours = ($effectiveWorkDays * $hoursPerDay) + (($vacationDays + $sickDays + $holidayDays + $schoolDays) * $hoursPerDay);
    $sollMinutes = round($sollHours * 60);
    
    // Gesamtzeit: Echte Arbeitszeit + Urlaubstage/Krankheitstage/Feiertage
    // Urlaubstage werden zur Gesamtzeit hinzugezählt, da sie als gearbeitete Zeit gelten
    $totalMinutesWithVacations = $totalMinutes + $vacationMinutes;
    
    // Überstunden/Minusstunden berechnen
    // Die Gesamtzeit (inkl. Urlaub) sollte mit der Sollzeit verglichen werden
    // Da Urlaubstage zur Sollzeit zählen, müssen sie auch zur Gesamtzeit zählen
    $diffMinutes = $totalMinutesWithVacations - $sollMinutes;
    $overtimeHours = round($diffMinutes / 60, 2);
    
    return [
        'total_hours' => round($totalMinutesWithVacations / 60, 2),
        'total_minutes' => $totalMinutesWithVacations,
        'actual_work_minutes' => $totalMinutes, // Tatsächliche Arbeitszeit ohne Urlaub
        'actual_work_hours' => round($totalMinutes / 60, 2),
        'total_with_vacations_hours' => round($totalMinutesWithVacations / 60, 2),
        'total_with_vacations_minutes' => $totalMinutesWithVacations,
        'work_days' => $workDays,
        'effective_work_days' => $effectiveWorkDays,
        'soll_hours' => round($sollHours, 2),
        'soll_minutes' => $sollMinutes,
        'overtime_hours' => $overtimeHours,
        'vacation_days' => $vacationDays,
        'sick_days' => $sickDays,
        'holiday_days' => $holidayDays,
        'vacation_minutes' => $vacationMinutes,
        'weekly_hours' => $weeklyHours
    ];
}

// Hilfsfunktion zur Berechnung der Arbeitstage
function getWorkDays($dateFrom, $dateTo) {
    $start = new DateTime($dateFrom);
    $end = new DateTime($dateTo);
    $end->modify('+1 day'); // Inklusive Enddatum
    
    $workDays = 0;
    $current = clone $start;
    
    while ($current < $end) {
        $dayOfWeek = $current->format('N'); // 1 (Montag) bis 7 (Sonntag)
        if ($dayOfWeek <= 5) { // Montag bis Freitag
            $workDays++;
        }
        $current->modify('+1 day');
    }
    
    return $workDays;
}

// Funktion zur Berechnung der Jahresübersicht
function getYearOverview($pdo, $userId, $year) {
    $yearStart = $year . '-01-01';
    $yearEnd = $year . '-12-31';
    
    // Hole alle Zeiteinträge für das Jahr
    $stmt = $pdo->prepare("
        SELECT DATE(start_time) as date, 
               SUM(duration_minutes) as total_minutes
        FROM time_tracking
        WHERE user_id = :user_id 
        AND DATE(start_time) BETWEEN :year_start AND :year_end
        AND end_time IS NOT NULL
        GROUP BY DATE(start_time)
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':year_start', $yearStart);
    $stmt->bindValue(':year_end', $yearEnd);
    $stmt->execute();
    $timeEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hole alle Urlaubstage für das Jahr
    $vacationStmt = $pdo->prepare("
        SELECT date, hours, type
        FROM time_tracking_vacation
        WHERE user_id = :user_id 
        AND date BETWEEN :year_start AND :year_end
    ");
    $vacationStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $vacationStmt->bindValue(':year_start', $yearStart);
    $vacationStmt->bindValue(':year_end', $yearEnd);
    $vacationStmt->execute();
    $vacations = $vacationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Einstellungen abrufen
    $settingsStmt = $pdo->prepare("
        SELECT setting_key, setting_value FROM user_settings 
        WHERE user_id = :user_id AND setting_key IN ('weekly_hours', 'work_start_time', 'work_end_time')
    ");
    $settingsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $settingsStmt->execute();
    $settingsResults = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settingsResults as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $weeklyHours = isset($settings['weekly_hours']) ? (float)$settings['weekly_hours'] : 40.0;
    $workStartTime = $settings['work_start_time'] ?? '08:00';
    $workEndTime = $settings['work_end_time'] ?? '17:00';
    
    // Stunden pro Tag berechnen
    $startParts = explode(':', $workStartTime);
    $endParts = explode(':', $workEndTime);
    $startHour = (int)$startParts[0] + ((int)$startParts[1] / 60);
    $endHour = (int)$endParts[0] + ((int)$endParts[1] / 60);
    $workHoursPerDay = $endHour - $startHour;
    $calculatedHoursPerDay = $weeklyHours / 5;
    $hoursPerDay = min($workHoursPerDay, $calculatedHoursPerDay);
    $minutesPerDay = round($hoursPerDay * 60);
    
    // Organisiere Daten nach Datum
    $dailyData = [];
    
    // Zeiteinträge
    foreach ($timeEntries as $entry) {
        $date = $entry['date'];
        $dailyData[$date] = [
            'minutes' => (int)$entry['total_minutes'],
            'type' => 'work'
        ];
    }
    
    // Urlaubstage
    foreach ($vacations as $vacation) {
        $date = $vacation['date'];
        $vacationMinutes = (int)($vacation['hours'] * 60);
        if (!isset($dailyData[$date])) {
            $dailyData[$date] = ['minutes' => 0, 'type' => 'vacation'];
        }
        $dailyData[$date]['minutes'] += $vacationMinutes;
        $dailyData[$date]['type'] = $vacation['type'];
    }
    
    // Struktur für Monate und Tage
    $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    
    $yearData = [];
    
    for ($month = 1; $month <= 12; $month++) {
        $monthData = [
            'month' => $month,
            'monthName' => $months[$month - 1],
            'days' => []
        ];
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        for ($day = 1; $day <= 31; $day++) {
            if ($day <= $daysInMonth) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dayOfWeek = date('N', strtotime($date)); // 1 (Montag) bis 7 (Sonntag)
                
                $minutes = 0;
                $type = 'none';
                $status = 'normal'; // normal, overtime, minus
                
                if (isset($dailyData[$date])) {
                    $minutes = $dailyData[$date]['minutes'];
                    $type = $dailyData[$date]['type'];
                }
                
                // Status berechnen (nur für Arbeitstage)
                if ($dayOfWeek <= 5 && $type !== 'vacation' && $type !== 'sick' && $type !== 'holiday' && $type !== 'school') {
                    if ($minutes > 0) {
                        if ($minutes > $minutesPerDay) {
                            $status = 'overtime'; // Überstunden
                        } elseif ($minutes < $minutesPerDay) {
                            $status = 'minus'; // Minus
                        } else {
                            $status = 'normal'; // Normal
                        }
                    } else {
                        $status = 'minus'; // Keine Zeit eingetragen
                    }
                } elseif ($type === 'vacation' || $type === 'sick' || $type === 'holiday' || $type === 'school') {
                    $status = 'special'; // Urlaub/Krank/Feiertag/Berufsschule
                } else {
                    $status = 'weekend'; // Wochenende
                }
                
                $monthData['days'][$day] = [
                    'minutes' => $minutes,
                    'type' => $type,
                    'status' => $status,
                    'exists' => isset($dailyData[$date])
                ];
            } else {
                // Tag existiert nicht in diesem Monat
                $monthData['days'][$day] = null;
            }
        }
        
        $yearData[] = $monthData;
    }
    
    return [
        'year' => $year,
        'hoursPerDay' => $hoursPerDay,
        'minutesPerDay' => $minutesPerDay,
        'months' => $yearData
    ];
}
