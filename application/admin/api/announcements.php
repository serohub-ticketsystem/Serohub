<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
    
    // Nur Admin und Techniker können Ankündigungen verwalten
    if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Alle Ankündigungen abrufen oder einzelne
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT id, titel, nachricht, link_text, link_url, show_banner, aktiv, anonym, company_id, erstellt_von, erstellt_datum, geaendert_datum
                    FROM announcements
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($announcement) {
                    echo json_encode(['success' => true, 'announcement' => $announcement]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Ankündigung nicht gefunden']);
                }
            } else {
                // Aktive Ankündigung für alle Benutzer abrufen (ohne Berechtigungsprüfung)
                $isPublic = isset($_GET['public']) && $_GET['public'] === 'true';
                
                if ($isPublic) {
                    // Aktuelle Firma des Benutzers aus Session ermitteln
                    $userCompanyId = null;
                    if (isset($_SESSION['user_id'])) {
                        $userStmt = $pdo->prepare("SELECT company_id FROM users WHERE id = :user_id");
                        $userStmt->execute([':user_id' => $_SESSION['user_id']]);
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        $userCompanyId = $user ? $user['company_id'] : null;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT a.id, a.titel, a.nachricht, a.link_text, a.link_url, a.show_banner, a.anonym,
                               CASE 
                                   WHEN a.anonym = 1 THEN 'Serohub'
                                   ELSE CONCAT(u.vorname, ' ', u.nachname)
                               END as ersteller_name
                        FROM announcements a
                        LEFT JOIN users u ON a.erstellt_von = u.id
                        WHERE a.aktiv = 1 AND a.show_banner = 1
                        AND (a.company_id IS NULL OR a.company_id = :user_company_id)
                        ORDER BY a.erstellt_datum DESC
                        LIMIT 1
                    ");
                    $stmt->execute([':user_company_id' => $userCompanyId]);
                    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'announcement' => $announcement]);
                } else {
                    // Alle Ankündigungen für Admin-Seite
                $stmt = $pdo->prepare("
                    SELECT a.id, a.titel, a.nachricht, a.link_text, a.link_url, a.show_banner, a.aktiv, a.anonym, a.company_id,
                           c.name as company_name,
                           a.erstellt_von, a.erstellt_datum, a.geaendert_datum,
                           CASE 
                               WHEN a.anonym = 1 THEN 'Serohub'
                               ELSE CONCAT(u.vorname, ' ', u.nachname)
                           END as ersteller_name
                    FROM announcements a
                    LEFT JOIN users u ON a.erstellt_von = u.id
                    LEFT JOIN companies c ON a.company_id = c.id
                    ORDER BY a.erstellt_datum DESC
                ");
                    $stmt->execute();
                    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'announcements' => $announcements]);
                }
            }
            exit;
            
        case 'POST':
            // Neue Ankündigung erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['titel']) || !isset($data['nachricht'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel und Nachricht sind erforderlich']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO announcements (titel, nachricht, link_text, link_url, show_banner, aktiv, anonym, company_id, erstellt_von, erstellt_datum)
                VALUES (:titel, :nachricht, :link_text, :link_url, :show_banner, :aktiv, :anonym, :company_id, :erstellt_von, NOW())
            ");
            
            $success = $stmt->execute([
                ':titel' => $data['titel'],
                ':nachricht' => $data['nachricht'],
                ':link_text' => $data['link_text'] ?? null,
                ':link_url' => $data['link_url'] ?? null,
                ':show_banner' => isset($data['show_banner']) ? (int)$data['show_banner'] : 1,
                ':aktiv' => isset($data['aktiv']) ? (int)$data['aktiv'] : 1,
                ':anonym' => isset($data['anonym']) ? (int)$data['anonym'] : 0,
                ':company_id' => isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null,
                ':erstellt_von' => $userId
            ]);
            
            if ($success) {
                $announcementId = $pdo->lastInsertId();
                
                // company_id aus den Daten holen
                $announcementCompanyId = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null;
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $userId,
                    $announcementCompanyId,
                    'announcement_created',
                    'Neue Ankündigung: ' . $data['titel'],
                    'Eine neue Ankündigung "' . $data['titel'] . '" wurde von ' . $userName . ' erstellt.',
                    'normal',
                    'admin/announcements.php',
                    'announcement',
                    $announcementId
                );
                
                echo json_encode(['success' => true, 'id' => $announcementId]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Erstellen']);
            }
            exit;
            
        case 'PUT':
            // Ankündigung aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $id = (int)$data['id'];
            
            // Prüfen ob Ankündigung existiert
            $checkStmt = $pdo->prepare("SELECT id FROM announcements WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ankündigung nicht gefunden']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE announcements
                SET titel = :titel,
                    nachricht = :nachricht,
                    link_text = :link_text,
                    link_url = :link_url,
                    show_banner = :show_banner,
                    aktiv = :aktiv,
                    anonym = :anonym,
                    company_id = :company_id,
                    geaendert_datum = NOW()
                WHERE id = :id
            ");
            
            $success = $stmt->execute([
                ':id' => $id,
                ':titel' => $data['titel'],
                ':nachricht' => $data['nachricht'],
                ':link_text' => $data['link_text'] ?? null,
                ':link_url' => $data['link_url'] ?? null,
                ':show_banner' => isset($data['show_banner']) ? (int)$data['show_banner'] : 1,
                ':aktiv' => isset($data['aktiv']) ? (int)$data['aktiv'] : 1,
                ':anonym' => isset($data['anonym']) ? (int)$data['anonym'] : 0,
                ':company_id' => isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null
            ]);
            
            if ($success) {
                // Ankündigungs-Informationen für Benachrichtigung abrufen
                $announcementStmt = $pdo->prepare("SELECT titel, company_id FROM announcements WHERE id = ?");
                $announcementStmt->execute([$id]);
                $announcementData = $announcementStmt->fetch(PDO::FETCH_ASSOC);
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                if ($announcementData) {
                    createNotificationsForAction(
                        $userId,
                        $announcementData['company_id'],
                        'announcement_updated',
                        'Ankündigung aktualisiert: ' . ($data['titel'] ?? $announcementData['titel']),
                        'Die Ankündigung "' . ($data['titel'] ?? $announcementData['titel']) . '" wurde von ' . $userName . ' aktualisiert.',
                        'normal',
                        'admin/announcements.php',
                        'announcement',
                        $id
                    );
                }
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Fehler beim Aktualisieren']);
            }
            exit;
            
        case 'DELETE':
            // Ankündigung löschen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            
            $id = (int)$data['id'];
            
            // Ankündigungs-Informationen für Benachrichtigung abrufen (vor dem Löschen)
            $announcementStmt = $pdo->prepare("SELECT titel, company_id FROM announcements WHERE id = ?");
            $announcementStmt->execute([$id]);
            $announcementData = $announcementStmt->fetch(PDO::FETCH_ASSOC);
            $announcementTitel = $announcementData['titel'] ?? 'Unbekannt';
            $announcementCompanyId = $announcementData['company_id'] ?? null;
            
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
            
            if ($stmt->execute([':id' => $id])) {
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $userId,
                    $announcementCompanyId,
                    'announcement_deleted',
                    'Ankündigung gelöscht: ' . $announcementTitel,
                    'Die Ankündigung "' . $announcementTitel . '" wurde von ' . $userName . ' gelöscht.',
                    'hoch',
                    'admin/announcements.php',
                    'announcement',
                    $id
                );
                
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
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    error_log("Announcements API Fehler: " . $e->getMessage());
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unerwarteter Fehler']);
    error_log("Announcements API Fehler: " . $e->getMessage());
    exit;
}
?>
