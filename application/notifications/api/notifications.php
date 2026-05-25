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

require_once dirname(__DIR__, 2) . '/assets/notifications.php';

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Alte Benachrichtigungen (älter als 30 Tage) automatisch entfernen
    $cleanupStmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE user_id = :user_id
          AND erstellt_datum < (NOW() - INTERVAL 30 DAY)
    ");
    $cleanupStmt->execute([':user_id' => $userId]);

    switch ($method) {
        case 'GET':
            // Benachrichtigungen abrufen
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
            $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

            $readState = 'unread';
            if (isset($_GET['read_state'])) {
                $rs = strtolower(trim((string)$_GET['read_state']));
                if (in_array($rs, ['all', 'read', 'unread'], true)) {
                    $readState = $rs;
                }
            }
            if (!isset($_GET['read_state']) && isset($_GET['only_unread']) && $_GET['only_unread'] === 'true') {
                $readState = 'unread';
            }

            $searchQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
            if (function_exists('mb_strlen') && mb_strlen($searchQ) > 200) {
                $searchQ = mb_substr($searchQ, 0, 200);
            } elseif (strlen($searchQ) > 200) {
                $searchQ = substr($searchQ, 0, 200);
            }

            $sort = isset($_GET['sort']) ? strtolower(trim((string)$_GET['sort'])) : 'relevanz';
            if (!in_array($sort, ['relevanz', 'erstellt_datum'], true)) {
                $sort = 'relevanz';
            }
            $sortDirRaw = isset($_GET['sort_dir']) ? strtolower(trim((string)$_GET['sort_dir'])) : '';
            if ($sortDirRaw === 'asc') {
                $sortDirSql = 'ASC';
            } elseif ($sortDirRaw === 'desc') {
                $sortDirSql = 'DESC';
            } else {
                $sortDirSql = ($sort === 'relevanz') ? 'ASC' : 'DESC';
            }
            
            // Prüfen ob created_by_user_id Spalte existiert
            $hasCreatedByUserId = false;
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'created_by_user_id'");
                $hasCreatedByUserId = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                $hasCreatedByUserId = false;
            }
            
            // Prüfen ob logopfad Spalte in users existiert
            $hasLogopfad = false;
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'logopfad'");
                $hasLogopfad = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                $hasLogopfad = false;
            }
            
            // Prüfen ob logo Spalte in companies existiert
            $hasCompanyLogo = false;
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo'");
                $hasCompanyLogo = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                $hasCompanyLogo = false;
            }

            $hasJoin = ($hasCreatedByUserId && $hasLogopfad);
            $p = $hasJoin ? 'n.' : '';
            
            if ($hasJoin) {
                $selectFields = [
                    'n.id',
                    'n.typ',
                    'n.titel',
                    'n.nachricht',
                    'n.link',
                    'n.relevanz',
                    'n.ist_gelesen',
                    'n.gelesen_datum',
                    'n.referenz_typ',
                    'n.referenz_id',
                    'n.erstellt_datum',
                    'n.created_by_user_id',
                    'u_creator.logopfad as creator_logopfad',
                    'u_creator.company_id as creator_company_id',
                    'u_creator.vorname as creator_vorname',
                    'u_creator.nachname as creator_nachname',
                    'u_creator.email as creator_email'
                ];
                
                if ($hasCompanyLogo) {
                    $selectFields[] = 'c_creator.logo as creator_company_logo';
                }
                
                $sql = "
                    SELECT " . implode(', ', $selectFields) . "
                    FROM notifications n
                    LEFT JOIN users u_creator ON n.created_by_user_id = u_creator.id
                    " . ($hasCompanyLogo ? "LEFT JOIN companies c_creator ON u_creator.company_id = c_creator.id" : "") . "
                    WHERE n.user_id = :user_id
                ";
            } else {
                $sql = "
                    SELECT 
                        id,
                        typ,
                        titel,
                        nachricht,
                        link,
                        relevanz,
                        ist_gelesen,
                        gelesen_datum,
                        referenz_typ,
                        referenz_id,
                        erstellt_datum
                    FROM notifications
                    WHERE user_id = :user_id
                ";
            }
            
            $whereExtra = [];
            if ($readState === 'read') {
                $whereExtra[] = $hasJoin ? 'n.ist_gelesen = 1' : 'ist_gelesen = 1';
            } elseif ($readState === 'unread') {
                $whereExtra[] = $hasJoin ? 'n.ist_gelesen = 0' : 'ist_gelesen = 0';
            }

            $bindSearch = false;
            $likePattern = '';
            if ($searchQ !== '') {
                $bindSearch = true;
                $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQ);
                $likePattern = '%' . $esc . '%';
                $whereExtra[] = $hasJoin
                    ? '(n.titel LIKE :notif_q1 OR n.nachricht LIKE :notif_q2)'
                    : '(titel LIKE :notif_q1 OR nachricht LIKE :notif_q2)';
            }

            if (!empty($whereExtra)) {
                $sql .= ' AND ' . implode(' AND ', $whereExtra);
            }

            if ($sort === 'relevanz') {
                $sql .= ' ORDER BY FIELD(COALESCE(' . $p . "relevanz,'niedrig'), 'kritisch', 'hoch', 'normal', 'niedrig') " . $sortDirSql . ', ' . $p . 'erstellt_datum DESC';
            } else {
                $sql .= ' ORDER BY ' . $p . 'erstellt_datum ' . $sortDirSql;
            }
            $sql .= ' LIMIT :limit OFFSET :offset';
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($bindSearch) {
                $stmt->bindValue(':notif_q1', $likePattern, PDO::PARAM_STR);
                $stmt->bindValue(':notif_q2', $likePattern, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatierung der Daten
            $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
            $defaultAvatarPath = 'assets/images/default-avatar.png';
            
            foreach ($notifications as &$notification) {
                $notification['ist_gelesen'] = (bool)$notification['ist_gelesen'];
                $notification['referenz_id'] = $notification['referenz_id'] ? (int)$notification['referenz_id'] : null;
                
                // Profilbild-URL für den Ersteller erstellen
                $avatarPath = null;
                $isPresetAvatar = false;
                
                // Prüfen ob Benutzer ein Profilbild hat (nicht leer und nicht das Standard-Profilbild)
                if (isset($notification['creator_logopfad']) && !empty($notification['creator_logopfad'])) {
                    $logopfad = $notification['creator_logopfad'];
                    // Preset-Avatar (Format: preset:color:initials) unverändert durchreichen
                    if (strpos($logopfad, 'preset:') === 0) {
                        $avatarPath = $logopfad;
                        $isPresetAvatar = true;
                    } else {
                    // Prüfen ob es nicht das Standard-Profilbild ist
                    if (strpos($logopfad, $defaultAvatarPath) === false) {
                        $avatarPath = $logopfad;
                    }
                    }
                }
                
                // Wenn kein benutzerdefiniertes Profilbild, Firmenlogo verwenden
                if (!$avatarPath && isset($notification['creator_company_logo']) && !empty($notification['creator_company_logo'])) {
                    $avatarPath = $notification['creator_company_logo'];
                }
                
                // Avatar-URL erstellen
                if ($avatarPath) {
                    if ($isPresetAvatar || strpos($avatarPath, 'preset:') === 0) {
                        $notification['creator_avatar'] = $avatarPath;
                    } elseif (strpos($avatarPath, 'http') === 0) {
                    if (strpos($avatarPath, 'http') === 0) {
                        $notification['creator_avatar'] = $avatarPath;
                    } else {
                        $notification['creator_avatar'] = $baseUrl . ltrim($avatarPath, '/');
                    }
                    }
                } else {
                    // Fallback auf Standard-Profilbild
                    $notification['creator_avatar'] = $baseUrl . $defaultAvatarPath;
                }
                
                // Creator-Name erstellen
                $creatorName = 'Unbekannt';
                if (isset($notification['created_by_user_id']) && $notification['created_by_user_id'] && $notification['created_by_user_id'] > 0) {
                    $vorname = isset($notification['creator_vorname']) ? trim($notification['creator_vorname']) : '';
                    $nachname = isset($notification['creator_nachname']) ? trim($notification['creator_nachname']) : '';
                    if (!empty($vorname) || !empty($nachname)) {
                        $creatorName = trim($vorname . ' ' . $nachname);
                        if (empty($creatorName)) {
                            $creatorName = isset($notification['creator_email']) ? $notification['creator_email'] : 'Unbekannt';
                        }
                    } elseif (isset($notification['creator_email']) && !empty($notification['creator_email'])) {
                        $creatorName = $notification['creator_email'];
                    }
                }
                $notification['creator_name'] = $creatorName;
                
                // Sicherstellen, dass creator_name immer gesetzt ist
                if (!isset($notification['creator_name']) || empty($notification['creator_name'])) {
                    $notification['creator_name'] = 'Unbekannt';
                }
            }
            unset($notification); // Referenz entfernen
            
            // Anzahl ungelesener Benachrichtigungen abrufen
            $unreadCount = getUnreadNotificationCount($userId);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            exit;
            
        case 'POST':
            // Benachrichtigung als gelesen markieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $notificationId = (int)$data['id'];
            
            // Prüfen ob Benachrichtigung dem Benutzer gehört
            $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE id = :id AND user_id = :user_id");
            $checkStmt->execute([
                ':id' => $notificationId,
                ':user_id' => $userId
            ]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Benachrichtigung nicht gefunden']);
                exit;
            }
            
            if (markNotificationAsRead($notificationId, $userId)) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Markieren als gelesen']);
            }
            exit;
            
        case 'PUT':
            // Alle Benachrichtigungen als gelesen markieren
            if (markAllNotificationsAsRead($userId)) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Markieren aller als gelesen']);
            }
            exit;
            
        case 'DELETE':
            // Benachrichtigung(en) löschen
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Prüfen ob alle Benachrichtigungen gelöscht werden sollen
            if (isset($data['delete_all']) && $data['delete_all'] === true) {
                // Alle Benachrichtigungen des Benutzers löschen
                $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :user_id");
                
                if ($deleteStmt->execute([':user_id' => $userId])) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen aller Benachrichtigungen']);
                }
                exit;
            }
            
            // Einzelne Benachrichtigung löschen
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $notificationId = (int)$data['id'];
            
            // Prüfen ob Benachrichtigung dem Benutzer gehört
            $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE id = :id AND user_id = :user_id");
            $checkStmt->execute([
                ':id' => $notificationId,
                ':user_id' => $userId
            ]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Benachrichtigung nicht gefunden']);
                exit;
            }
            
            $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
            
            if ($deleteStmt->execute([':id' => $notificationId, ':user_id' => $userId])) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen']);
            }
            exit;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
    error_log("Notifications API Fehler: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unerwarteter Fehler']);
    error_log("Notifications API Fehler: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    exit;
}
