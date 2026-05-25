<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$isPublic = isset($_GET['public']) && $_GET['public'] === 'true';

// Öffentlicher Aufruf: Aktive Umfrage für Popup (erste, die User noch nicht beantwortet hat)
if ($isPublic && $method === 'GET') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'survey' => null]);
        exit;
    }
    if (!empty($_SESSION['is_first_login'])) {
        echo json_encode(['success' => true, 'survey' => null]);
        exit;
    }
    try {
        $userCompanyId = null;
        $userStmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $u = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($u) $userCompanyId = $u['company_id'];

        $stmt = $pdo->prepare("
            SELECT s.id, s.frage
            FROM satisfaction_surveys s
            WHERE s.aktiv = 1
            AND (s.company_id IS NULL OR s.company_id = ?)
            ORDER BY s.id ASC
        ");
        $stmt->execute([$userCompanyId ?? 0]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check = $pdo->prepare("SELECT id FROM satisfaction_survey_responses WHERE user_id = ? AND survey_id = ? LIMIT 1");
            $check->execute([$_SESSION['user_id'], $row['id']]);
            if (!$check->fetch()) {
                echo json_encode(['success' => true, 'survey' => ['id' => (int)$row['id'], 'frage' => $row['frage']]]);
                exit;
            }
        }
        echo json_encode(['success' => true, 'survey' => null]);
    } catch (PDOException $e) {
        error_log('satisfaction-survey public: ' . $e->getMessage());
        echo json_encode(['success' => true, 'survey' => null]);
    }
    exit;
}

// Admin-Bereich
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

try {
    $st = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $st->execute([$_SESSION['user_id']]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Admin' && $user['rolle'] !== 'Techniker')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler']);
    exit;
}

$getStatsForSurvey = function($surveyId) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM satisfaction_survey_responses WHERE survey_id = ?");
    $stmt->execute([$surveyId]);
    $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->prepare("SELECT rating, COUNT(*) as cnt FROM satisfaction_survey_responses WHERE survey_id = ? GROUP BY rating ORDER BY rating");
    $stmt->execute([$surveyId]);
    $distribution = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $distribution[(int)$row['rating']] = (int)$row['cnt'];
    }

    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM satisfaction_survey_responses WHERE survey_id = ?");
    $stmt->execute([$surveyId]);
    $avg = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgRating = $avg && $avg['avg_rating'] !== null ? round((float)$avg['avg_rating'], 1) : 0;

    $stmt = $pdo->prepare("SELECT MAX(created_at) as last FROM satisfaction_survey_responses WHERE survey_id = ?");
    $stmt->execute([$surveyId]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastAt = $last && $last['last'] ? $last['last'] : null;

    require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.feedback, r.created_at, u.vorname, u.nachname, u.email, c.name as company_name
        FROM satisfaction_survey_responses r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN companies c ON c.id = r.company_id
        WHERE r.survey_id = ?
        ORDER BY r.created_at DESC
        LIMIT 500
    ");
    $stmt->execute([$surveyId]);
    $responsesList = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $responsesList[] = [
            'id' => (int)$row['id'],
            'rating' => (int)$row['rating'],
            'feedback' => trim((string)($row['feedback'] ?? '')),
            'created_at' => $row['created_at'],
            'user_name' => trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? '')) ?: 'Unbekannt',
            'user_email' => (string)($row['email'] ?? ''),
            'company_name' => decrypt_from_db($row['company_name'] ?? null)
        ];
    }

    return [
        'total' => $total,
        'distribution' => $distribution,
        'avg_rating' => $avgRating,
        'last_response_at' => $lastAt,
        'responses_list' => $responsesList
    ];
};

switch ($method) {
    case 'GET':
        try {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT s.id, s.titel, s.frage, s.aktiv, s.company_id, s.erstellt_datum, s.geaendert_datum FROM satisfaction_surveys s WHERE s.id = ?");
                $stmt->execute([$id]);
                $s = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$s) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Umfrage nicht gefunden']);
                    exit;
                }
                $stats = $getStatsForSurvey($id);
                echo json_encode([
                    'success' => true,
                    'survey' => [
                        'id' => (int)$s['id'],
                        'titel' => $s['titel'],
                        'frage' => $s['frage'],
                        'aktiv' => (int)$s['aktiv'],
                        'company_id' => $s['company_id']
                    ],
                    'stats' => $stats
                ]);
            } else {
                $stmt = $pdo->query("SELECT s.id, s.titel, s.frage, s.aktiv, s.company_id, s.erstellt_datum FROM satisfaction_surveys s ORDER BY s.id DESC");
                $surveys = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sid = (int)$row['id'];
                    $stats = $getStatsForSurvey($sid);
                    $surveys[] = [
                        'id' => $sid,
                        'titel' => $row['titel'],
                        'frage' => $row['frage'],
                        'aktiv' => (int)$row['aktiv'],
                        'company_id' => $row['company_id'],
                        'erstellt_datum' => $row['erstellt_datum'],
                        'total' => $stats['total'],
                        'avg_rating' => $stats['avg_rating'],
                        'last_response_at' => $stats['last_response_at']
                    ];
                }
                echo json_encode(['success' => true, 'surveys' => $surveys]);
            }
        } catch (PDOException $e) {
            error_log('satisfaction-survey GET: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Laden']);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
            exit;
        }
        $titel = trim($input['titel'] ?? '') ?: 'Neue Umfrage';
        $frage = trim($input['frage'] ?? '') ?: 'Wie zufrieden sind Sie mit unserem Service?';
        $aktiv = isset($input['aktiv']) && $input['aktiv'] ? 1 : 0;
        $companyId = isset($input['company_id']) && $input['company_id'] !== '' && $input['company_id'] !== null ? (int)$input['company_id'] : null;
        try {
            $stmt = $pdo->prepare("INSERT INTO satisfaction_surveys (titel, frage, aktiv, company_id, erstellt_von) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$titel, $frage, $aktiv, $companyId, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            error_log('satisfaction-survey POST: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Erstellen']);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
            exit;
        }
        $id = (int)$input['id'];
        $titel = trim($input['titel'] ?? '') ?: 'Umfrage';
        $frage = trim($input['frage'] ?? '') ?: 'Wie zufrieden sind Sie mit unserem Service?';
        $aktiv = isset($input['aktiv']) && $input['aktiv'] ? 1 : 0;
        $companyId = isset($input['company_id']) && $input['company_id'] !== '' && $input['company_id'] !== null ? (int)$input['company_id'] : null;
        try {
            $stmt = $pdo->prepare("UPDATE satisfaction_surveys SET titel = ?, frage = ?, aktiv = ?, company_id = ? WHERE id = ?");
            $stmt->execute([$titel, $frage, $aktiv, $companyId, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('satisfaction-survey PUT: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern']);
        }
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID fehlt']);
            exit;
        }
        try {
            $pdo->prepare("DELETE FROM satisfaction_survey_responses WHERE survey_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM satisfaction_surveys WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('satisfaction-survey DELETE: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
}
