<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';

header('Content-Type: application/json');

/**
 * JSON ausgeben. Verhindert leere HTTP-200-Antworten, wenn json_encode() fehlschlägt
 * (z. B. ungültiges UTF-8 in Todo-Texten – dann wäre echo false leer).
 */
function todos_api_json_out($data, $httpCode = null) {
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $payload = json_encode($data, $flags);
    if ($payload === false) {
        error_log('todos.php json_encode failed: ' . json_last_error_msg());
        http_response_code(500);
        echo '{"success":false,"error":"Antwort konnte nicht erstellt werden"}';
        exit;
    }
    if ($httpCode !== null) {
        http_response_code((int) $httpCode);
    }
    echo $payload;
}

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    todos_api_json_out(['success' => false, 'error' => 'Nicht angemeldet'], 401);
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
        todos_api_json_out(['success' => false, 'error' => 'Benutzer nicht gefunden'], 401);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
    
    // Zugriff: Techniker/Admin immer; andere Rollen nur für PUT (z. B. eigenes Todo aus Chat abhaken)
    $isAdminOrTech = ($userRole === 'Techniker' || $userRole === 'Admin');
    if (!$isAdminOrTech && $method !== 'PUT') {
        todos_api_json_out(['success' => false, 'error' => 'Keine Berechtigung. Nur Techniker und Admins haben Zugriff auf Todos.'], 403);
        exit;
    }
} catch (PDOException $e) {
    todos_api_json_out(['success' => false, 'error' => 'Datenbankfehler'], 500);
    exit;
}

require_once dirname(__DIR__) . '/helper/ticket_folder.php';
require_once dirname(__DIR__) . '/helper/project_folder.php';
require_once dirname(__DIR__) . '/helper/encryption.php';

try {
    switch ($method) {
        case 'GET':
            // Zuweisbare Benutzer abrufen
            if (isset($_GET['action']) && $_GET['action'] === 'assignable_users') {
                $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
                $roles = isset($_GET['roles']) ? $_GET['roles'] : null;
                $allUsers = isset($_GET['all_users']) && $_GET['all_users'] === 'true';
                
                $users = [];
                
                // Alle User laden (für Beobachter)
                if ($allUsers) {
                    try {
                        $stmt = $pdo->prepare("
                            SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, u.company_id, u.logopfad, c.name as company_name
                            FROM users u
                            LEFT JOIN companies c ON u.company_id = c.id
                            WHERE u.status = 'aktiv' 
                            ORDER BY u.nachname, u.vorname
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Fehler ignorieren
                    }
                } else {
                    // Firmen-Benutzer laden (falls company_id vorhanden)
                    if ($companyId) {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, u.company_id, u.logopfad, c.name as company_name
                                FROM users u
                                LEFT JOIN companies c ON u.company_id = c.id
                                WHERE u.company_id = ? AND u.status = 'aktiv' 
                                ORDER BY u.nachname, u.vorname
                            ");
                            $stmt->execute([$companyId]);
                            $companyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $users = array_merge($users, $companyUsers);
                        } catch (PDOException $e) {
                            // Fehler ignorieren
                        }
                    }
                    
                    // Bestimmte Rollen laden (falls roles Parameter gesetzt)
                    if ($roles) {
                        $roleList = explode(',', $roles);
                        $roleList = array_map('trim', $roleList);
                        $placeholders = implode(',', array_fill(0, count($roleList), '?'));
                        
                        try {
                            $stmt = $pdo->prepare("
                                SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, u.company_id, u.logopfad, c.name as company_name
                                FROM users u
                                LEFT JOIN companies c ON u.company_id = c.id
                                WHERE u.rolle IN ($placeholders) AND u.status = 'aktiv' 
                                ORDER BY u.nachname, u.vorname
                            ");
                            $stmt->execute($roleList);
                            $roleUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Duplikate entfernen
                            $existingIds = array_column($users, 'id');
                            foreach ($roleUsers as $user) {
                                if (!in_array($user['id'], $existingIds)) {
                                    $users[] = $user;
                                }
                            }
                        } catch (PDOException $e) {
                            // Fehler ignorieren
                        }
                    } else {
                        // Techniker und Admins laden (unabhängig von Firma) - Standard-Verhalten
                        try {
                            $stmt = $pdo->prepare("
                                SELECT u.id, u.vorname, u.nachname, u.email, u.rolle, u.company_id, u.logopfad, c.name as company_name
                                FROM users u
                                LEFT JOIN companies c ON u.company_id = c.id
                                WHERE (u.rolle = 'Techniker' OR u.rolle = 'Admin') AND u.status = 'aktiv' 
                                ORDER BY u.nachname, u.vorname
                            ");
                            $stmt->execute();
                            $adminTechUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Duplikate entfernen (falls ein Admin/Techniker auch zur Firma gehört)
                            $existingIds = array_column($users, 'id');
                            foreach ($adminTechUsers as $user) {
                                if (!in_array($user['id'], $existingIds)) {
                                    $users[] = $user;
                                }
                            }
                        } catch (PDOException $e) {
                            // Fehler ignorieren
                        }
                    }
                }
                
                // Nach Name sortieren
                usort($users, function($a, $b) {
                    $nameA = trim(($a['nachname'] ?? '') . ' ' . ($a['vorname'] ?? ''));
                    $nameB = trim(($b['nachname'] ?? '') . ' ' . ($b['vorname'] ?? ''));
                    return strcmp($nameA, $nameB);
                });
                
                todos_api_json_out([
                    'success' => true,
                    'users' => $users
                ]);
                exit;
            }
            
            // Todos abrufen
            $ticketFilter = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
            $projectFilter = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
            $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $folderFilter = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
            $idFilter = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            // company_id Filter kann aus GET-Parameter kommen (wird vom Frontend gesendet)
            // Die Filterung erfolgt nur wenn explizit eine company_id übergeben wurde
            
            $whereConditions = [];
            $params = [];
            
            // Rollenbasierte Filterung
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Todos, aber können nach Firma filtern
                if ($companyFilter) {
                    $whereConditions[] = "todos.company_id = :company_filter";
                    $params[':company_filter'] = $companyFilter;
                }
            } elseif ($userRole === 'Firmen-Admin') {
                // Firmen-Admin sieht nur Todos der eigenen Firma oder eigene Todos
                if ($userCompanyId) {
                    $whereConditions[] = "(todos.company_id = :user_company_id OR todos.erstellt_von = :user_id OR todos.zugewiesen_an = :user_id2 OR EXISTS (
                        SELECT 1 FROM tickets WHERE tickets.id = todos.ticket_id AND tickets.company_id = :user_company_id2
                    ))";
                    $params[':user_company_id'] = $userCompanyId;
                    $params[':user_company_id2'] = $userCompanyId;
                    $params[':user_id'] = $userId;
                    $params[':user_id2'] = $userId;
                } else {
                    $whereConditions[] = "(todos.erstellt_von = :user_id OR todos.zugewiesen_an = :user_id2)";
                    $params[':user_id'] = $userId;
                    $params[':user_id2'] = $userId;
                }
            } else {
                // Andere sehen nur eigene Todos
                $whereConditions[] = "(todos.erstellt_von = :user_id OR todos.zugewiesen_an = :user_id2)";
                $params[':user_id'] = $userId;
                $params[':user_id2'] = $userId;
            }
            
            if ($ticketFilter) {
                $whereConditions[] = "todos.ticket_id = :ticket_filter";
                $params[':ticket_filter'] = $ticketFilter;
            }
            if ($projectFilter) {
                $col = $pdo->query("SHOW COLUMNS FROM todos LIKE 'project_id'");
                if ($col && $col->rowCount() > 0) {
                    $whereConditions[] = "todos.project_id = :project_filter";
                    $params[':project_filter'] = $projectFilter;
                }
            }
            
            if ($statusFilter) {
                $whereConditions[] = "todos.status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }
            
            if ($folderFilter !== null) {
                if ($folderFilter === 0) {
                    // 0 bedeutet: nur Todos ohne Ordner
                    $whereConditions[] = "todos.folder_id IS NULL";
                } else {
                    $whereConditions[] = "todos.folder_id = :folder_filter";
                    $params[':folder_filter'] = $folderFilter;
                }
            }
            
            if ($idFilter > 0) {
                $whereConditions[] = "todos.id = :id_filter";
                $params[':id_filter'] = $idFilter;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            $hasProjectNummer = false;
            try {
                $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'project_nummer'");
                $hasProjectNummer = $col && $col->rowCount() > 0;
            } catch (PDOException $e) {}
            $projectNummerSel = $hasProjectNummer ? ", p.project_nummer as project_nummer" : "";
            $sql = "
                SELECT 
                    todos.id,
                    todos.titel,
                    todos.beschreibung,
                    todos.status,
                    todos.prioritaet,
                    todos.favorit,
                    todos.ticket_id,
                    todos.project_id,
                    todos.company_id,
                    todos.folder_id,
                    todos.erstellt_von,
                    todos.zugewiesen_an,
                    todos.faellig_am,
                    todos.erstellt_datum,
                    todos.geaendert_datum,
                    todos.erledigt_datum,
                    t.ticket_nummer,
                    t.titel as ticket_titel,
                    c.name as company_name,
                    tf.name as folder_name,
                    p.bezeichnung as project_name
                    $projectNummerSel,
                    u_erstellt.vorname as ersteller_vorname,
                    u_erstellt.nachname as ersteller_nachname,
                    u_zugewiesen.vorname as zugewiesen_vorname,
                    u_zugewiesen.nachname as zugewiesen_nachname,
                    u_zugewiesen.logopfad as zugewiesen_logopfad,
                    COALESCE(tus.sort_order, 999999) as user_sort_order,
                    COALESCE(att.attachment_count, 0) as attachment_count
                FROM todos
                LEFT JOIN tickets t ON todos.ticket_id = t.id
                LEFT JOIN projects p ON todos.project_id = p.id
                LEFT JOIN companies c ON todos.company_id = c.id
                LEFT JOIN todo_folders tf ON todos.folder_id = tf.id
                LEFT JOIN users u_erstellt ON todos.erstellt_von = u_erstellt.id
                LEFT JOIN users u_zugewiesen ON todos.zugewiesen_an = u_zugewiesen.id
                LEFT JOIN todo_user_sorts tus ON todos.id = tus.todo_id 
                    AND tus.user_id = :sort_user_id 
                    AND COALESCE(todos.folder_id, 0) = COALESCE(tus.folder_id, 0)
                LEFT JOIN (
                    SELECT todo_id, COUNT(*) as attachment_count
                    FROM todo_attachments
                    GROUP BY todo_id
                ) att ON todos.id = att.todo_id
                $whereClause
                ORDER BY 
                    todos.favorit DESC,
                    COALESCE(todos.geaendert_datum, todos.erstellt_datum) DESC,
                    user_sort_order ASC
            ";
            $params[':sort_user_id'] = $userId;
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($todos as &$t) {
                decrypt_todo_row($t);
            }
            unset($t);
            
            todos_api_json_out([
                'success' => true,
                'todos' => $todos
            ]);
            break;
            
        case 'POST':
            // Neues Todo erstellen
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['titel'])) {
                todos_api_json_out(['success' => false, 'error' => 'Titel ist erforderlich'], 400);
                exit;
            }
            
            $titel = trim($data['titel']);
            $beschreibung = isset($data['beschreibung']) ? trim($data['beschreibung']) : null;
            $titel = encrypt_for_db($titel);
            $beschreibung = $beschreibung !== null && $beschreibung !== '' ? encrypt_for_db($beschreibung) : null;
            $status = isset($data['status']) ? $data['status'] : 'offen';
            $prioritaet = 'normal'; // Standard-Wert beibehalten für Kompatibilität
            $ticketId = isset($data['ticket_id']) ? (int)$data['ticket_id'] : null;
            $projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : null;
            $folderId = isset($data['folder_id']) ? (int)$data['folder_id'] : null;
            $zugewiesenAn = isset($data['zugewiesen_an']) ? (int)$data['zugewiesen_an'] : null;
            $faelligAm = isset($data['faellig_am']) ? $data['faellig_am'] : null;
            
            // Rollenbasierte Validierung für company_id
            if ($userRole === 'Firmen-Admin' && $userCompanyId) {
                // Firmen-Admin kann nur für eigene Firma erstellen
                if ($companyId && $companyId != $userCompanyId) {
                    todos_api_json_out(['success' => false, 'error' => 'Keine Berechtigung für diese Firma'], 403);
                    exit;
                }
                $companyId = $userCompanyId;
            } elseif ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                // Andere Rollen können keine Firma setzen
                $companyId = null;
            }
            
            // Debug-Logging (kann später entfernt werden)
            error_log("Todo POST - Role: $userRole, CompanyId from Request: " . ($companyId ?: 'null') . ", Final CompanyId: " . ($companyId ?: 'null'));
            
            // Prüfen ob Ordner existiert und zur Firma gehört
            if ($folderId) {
                $folderStmt = $pdo->prepare("SELECT id, company_id FROM todo_folders WHERE id = ?");
                $folderStmt->execute([$folderId]);
                $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
                if (!$folder) {
                    todos_api_json_out(['success' => false, 'error' => 'Ordner nicht gefunden'], 404);
                    exit;
                }
                if ($folder['company_id'] && $folder['company_id'] != $companyId) {
                    todos_api_json_out(['success' => false, 'error' => 'Ordner gehört nicht zur ausgewählten Firma'], 403);
                    exit;
                }
            }
            
            // Validierung - nur "offen" und "erledigt" erlaubt
            $allowedStatus = ['offen', 'erledigt'];
            if (!in_array($status, $allowedStatus)) {
                $status = 'offen';
            }
            
            // Priorität standardmäßig auf 'normal' setzen
            $allowedPrioritaet = ['niedrig', 'normal', 'hoch', 'kritisch'];
            if (!in_array($prioritaet, $allowedPrioritaet)) {
                $prioritaet = 'normal';
            }
            
            // Wenn Ticket zugeordnet, prüfen ob Berechtigung
            if ($ticketId) {
                $checkStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ?");
                $checkStmt->execute([$ticketId]);
                $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ticket) {
                    todos_api_json_out(['success' => false, 'error' => 'Ticket nicht gefunden'], 404);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                } elseif ($ticket['erstellt_von'] == $userId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    todos_api_json_out(['success' => false, 'error' => 'Keine Berechtigung für diesen Ticket'], 403);
                    exit;
                }
                // Einstellung: Aufgaben aus Tickets müssen dem Systemordner "Ticketaufgaben" zugeordnet sein
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'ticket_tasks_require_folder' LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && $reqRow['setting_value'] === '1') {
                    $ticketFolderId = getOrCreateTicketTasksFolder($pdo, null, $userId);
                    if ($ticketFolderId) {
                        $folderId = $ticketFolderId;
                    }
                }
            }

            // Einstellung: Aufgaben aus Projekten dem Systemordner "Projektaufgaben" zuordnen
            if ($projectId) {
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'project_tasks_require_folder' LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && $reqRow['setting_value'] === '1') {
                    $projectFolderId = getOrCreateProjectTasksFolder($pdo, null, $userId);
                    if ($projectFolderId) {
                        $folderId = $projectFolderId;
                    }
                }
            }
            
            $hasProjectId = false;
            try {
                $col = $pdo->query("SHOW COLUMNS FROM todos LIKE 'project_id'");
                $hasProjectId = $col && $col->rowCount() > 0;
            } catch (PDOException $e) {}
            if ($projectId && !$hasProjectId) $projectId = null;

            $insertCols = "titel, beschreibung, status, prioritaet, company_id, folder_id, ticket_id, erstellt_von, zugewiesen_an, faellig_am, erstellt_datum";
            $insertVals = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()";
            $insertParams = [$titel, $beschreibung, $status, $prioritaet, $companyId, $folderId, $ticketId, $userId, $zugewiesenAn, $faelligAm];
            if ($hasProjectId) {
                $insertCols .= ", project_id";
                $insertVals .= ", ?";
                $insertParams[] = $projectId;
            }
            $stmt = $pdo->prepare("INSERT INTO todos ($insertCols) VALUES ($insertVals)");
            $stmt->execute($insertParams);
            
            $todoId = $pdo->lastInsertId();
            
            // Relevanz basierend auf Priorität bestimmen
            $relevanz = 'normal';
            if ($prioritaet === 'kritisch') {
                $relevanz = 'kritisch';
            } elseif ($prioritaet === 'hoch') {
                $relevanz = 'hoch';
            }
            
            $titelPlain = decrypt_from_db($titel);
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $companyId,
                'todo_created',
                'Neue Aufgabe erstellt: ' . $titelPlain,
                'Eine neue Aufgabe "' . $titelPlain . '" wurde von ' . $userName . ' erstellt.',
                $relevanz,
                'todos/index.php',
                'todo',
                $todoId
            );
            
            // Wenn zugewiesen, Person benachrichtigen
            if ($zugewiesenAn && $zugewiesenAn != $userId) {
                createNotification(
                    $zugewiesenAn,
                    'todo_zugewiesen',
                    'Aufgabe zugewiesen: ' . $titelPlain,
                    'Ihnen wurde eine neue Aufgabe "' . $titelPlain . '" von ' . $userName . ' zugewiesen.',
                    'hoch',
                    'todos/index.php',
                    'todo',
                    $todoId,
                    true,
                    $userId
                );
            }
            
            todos_api_json_out([
                'success' => true,
                'message' => 'Todo erfolgreich erstellt',
                'todo_id' => $todoId
            ]);
            break;
            
        case 'PUT':
            // Todo aktualisieren
            $data = json_decode(file_get_contents('php://input'), true);
            $isAutosave = !empty($data['is_autosave']);
            
            if (!isset($data['todo_id'])) {
                todos_api_json_out(['success' => false, 'error' => 'todo_id fehlt'], 400);
                exit;
            }
            
            $todoId = (int)$data['todo_id'];
            
            // Prüfen ob Todo existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von, zugewiesen_an, ticket_id, company_id, titel, prioritaet, project_id FROM todos WHERE id = ?");
            $checkStmt->execute([$todoId]);
            $todo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$todo) {
                todos_api_json_out(['success' => false, 'error' => 'Todo nicht gefunden'], 404);
                exit;
            }
            decrypt_todo_row($todo);
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($todo['erstellt_von'] == $userId || $todo['zugewiesen_an'] == $userId) {
                $hasPermission = true;
            } elseif ($todo['ticket_id']) {
                // Prüfen ob Zugriff auf zugehöriges Ticket
                $ticketStmt = $pdo->prepare("SELECT erstellt_von, company_id FROM tickets WHERE id = ?");
                $ticketStmt->execute([$todo['ticket_id']]);
                $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
                if ($ticket && ($ticket['erstellt_von'] == $userId || ($userRole === 'Firmen-Admin' && $ticket['company_id'] == $userCompanyId))) {
                    $hasPermission = true;
                }
            }
            
            if (!$hasPermission) {
                todos_api_json_out(['success' => false, 'error' => 'Keine Berechtigung'], 403);
                exit;
            }
            
            // Update-Felder zusammenbauen
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['titel'])) {
                $updateFields[] = "titel = ?";
                $updateParams[] = encrypt_for_db(trim($data['titel']));
            }
            // array_key_exists: JSON null leert die Spalte; isset() wäre bei null false und würde nichts updaten
            if (array_key_exists('beschreibung', $data)) {
                $updateFields[] = "beschreibung = ?";
                $b = trim((string)($data['beschreibung'] ?? ''));
                $updateParams[] = $b !== '' ? encrypt_for_db($b) : null;
            }
            if (isset($data['status'])) {
                // Validierung - nur "offen" und "erledigt" erlaubt
                $allowedStatus = ['offen', 'erledigt'];
                $status = in_array($data['status'], $allowedStatus) ? $data['status'] : 'offen';
                
                $updateFields[] = "status = ?";
                $updateParams[] = $status;
                
                // Wenn auf erledigt gesetzt, erledigt_datum setzen
                if ($status === 'erledigt') {
                    $updateFields[] = "erledigt_datum = NOW()";
                } else {
                    $updateFields[] = "erledigt_datum = NULL";
                }
            }
            if (isset($data['prioritaet'])) {
                $updateFields[] = "prioritaet = ?";
                $updateParams[] = $data['prioritaet'];
            }
            if (array_key_exists('zugewiesen_an', $data)) {
                $updateFields[] = "zugewiesen_an = ?";
                // zugewiesen_an behandeln: null, 0 oder 'null' -> NULL, sonst int
                if ($data['zugewiesen_an'] === null || $data['zugewiesen_an'] === 'null' || $data['zugewiesen_an'] === 0 || $data['zugewiesen_an'] === '0' || $data['zugewiesen_an'] === '') {
                    $updateParams[] = null;
                } else {
                    $updateParams[] = (int)$data['zugewiesen_an'];
                }
            }
            // array_key_exists: JSON null leert die Spalte; isset() wäre bei null false und würde nichts updaten
            if (array_key_exists('faellig_am', $data)) {
                $updateFields[] = "faellig_am = ?";
                $updateParams[] = $data['faellig_am'] ? $data['faellig_am'] : null;
            }
            if (isset($data['company_id']) && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                $updateFields[] = "company_id = ?";
                $updateParams[] = $data['company_id'] ? (int)$data['company_id'] : null;
            }
            if (array_key_exists('folder_id', $data)) {
                $updateFields[] = "folder_id = ?";
                // folder_id behandeln: null, 0 oder 'null' -> NULL, sonst int
                if ($data['folder_id'] === null || $data['folder_id'] === 'null' || $data['folder_id'] === 0 || $data['folder_id'] === '0' || $data['folder_id'] === '') {
                    $updateParams[] = null;
                } else {
                    $updateParams[] = (int)$data['folder_id'];
                }
            }
            if (isset($data['favorit'])) {
                $updateFields[] = "favorit = ?";
                $updateParams[] = (int)$data['favorit'];
            }
            
            // Einstellung: Aufgaben aus Tickets müssen dem Systemordner "Ticketaufgaben" zugeordnet sein
            if ($todo['ticket_id']) {
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'ticket_tasks_require_folder' LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && $reqRow['setting_value'] === '1') {
                    $ticketFolderId = getOrCreateTicketTasksFolder($pdo, null, $userId);
                    if ($ticketFolderId) {
                        $folderKey = array_search('folder_id = ?', array_map('trim', $updateFields));
                        if ($folderKey !== false) {
                            $updateParams[$folderKey] = $ticketFolderId;
                        } else {
                            $updateFields[] = "folder_id = ?";
                            $updateParams[] = $ticketFolderId;
                        }
                    }
                }
            }

            // Einstellung: Aufgaben aus Projekten dem Systemordner "Projektaufgaben" zuordnen
            if (!empty($todo['project_id'])) {
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'project_tasks_require_folder' LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && $reqRow['setting_value'] === '1') {
                    $projectFolderId = getOrCreateProjectTasksFolder($pdo, null, $userId);
                    if ($projectFolderId) {
                        $folderKey = array_search('folder_id = ?', array_map('trim', $updateFields));
                        if ($folderKey !== false) {
                            $updateParams[$folderKey] = $projectFolderId;
                        } else {
                            $updateFields[] = "folder_id = ?";
                            $updateParams[] = $projectFolderId;
                        }
                    }
                }
            }
            
            if (empty($updateFields)) {
                todos_api_json_out(['success' => false, 'error' => 'Keine Felder zum Aktualisieren'], 400);
                exit;
            }
            
            $updateFields[] = "geaendert_datum = NOW()";
            $updateParams[] = $todoId;
            
            $sql = "UPDATE todos SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
            
            // Benachrichtigungen für Statusänderungen und Zuweisungen
            $notifyUserIds = [];
            
            // Wenn zugewiesen_an geändert wurde, benachrichtigen
            if (array_key_exists('zugewiesen_an', $data) && $data['zugewiesen_an'] && $data['zugewiesen_an'] != $userId) {
                $newZugewiesenAn = $data['zugewiesen_an'] === 'null' || $data['zugewiesen_an'] === null || $data['zugewiesen_an'] === 0 || $data['zugewiesen_an'] === '0' ? null : (int)$data['zugewiesen_an'];
                if ($newZugewiesenAn) {
                    createNotification(
                        $newZugewiesenAn,
                        'todo_zugewiesen',
                        'Aufgabe zugewiesen: ' . $todo['titel'],
                        'Ihnen wurde eine Aufgabe zugewiesen.',
                        'hoch',
                        'todos/index.php',
                        'todo',
                        $todoId
                    );
                }
            }
            
            // Wenn Status geändert wurde, Ersteller und Zugewiesenen benachrichtigen
            if (isset($data['status']) && $todo) {
                $newStatus = $data['status'];
                $statusText = $newStatus === 'erledigt' ? 'erledigt' : 'geöffnet';
                
                // Relevanz basierend auf Priorität bestimmen
                $relevanz = 'normal';
                if ($todo['prioritaet'] === 'kritisch') {
                    $relevanz = 'kritisch';
                } elseif ($todo['prioritaet'] === 'hoch') {
                    $relevanz = 'hoch';
                }
                
                // Benachrichtigungen für Ersteller und Zugewiesenen
                $notifyUserIds = [];
                if ($todo['erstellt_von'] && $todo['erstellt_von'] != $userId) {
                    $notifyUserIds[] = $todo['erstellt_von'];
                }
                if ($todo['zugewiesen_an'] && $todo['zugewiesen_an'] != $userId) {
                    $notifyUserIds[] = $todo['zugewiesen_an'];
                }
                
                // Benachrichtigungen für Ersteller und Zugewiesenen erstellen
                if (!empty($notifyUserIds)) {
                    createNotificationsForUsers(
                        $notifyUserIds,
                        'todo_status_changed',
                        'Aufgabe-Status geändert: ' . $todo['titel'],
                        'Der Status der Aufgabe "' . $todo['titel'] . '" wurde von ' . $userName . ' auf "' . $statusText . '" gesetzt.',
                        $relevanz,
                        'todos/index.php',
                        'todo',
                        $todoId,
                        $userId
                    );
                }
                
                // Benachrichtigungen für Admin, Techniker und Firmen-Admin erstellen
                createNotificationsForAction(
                    $userId,
                    $todo['company_id'],
                    'todo_status_changed',
                    'Aufgabe-Status geändert: ' . $todo['titel'],
                    'Der Status der Aufgabe "' . $todo['titel'] . '" wurde von ' . $userName . ' auf "' . $statusText . '" gesetzt.',
                    $relevanz,
                    'todos/index.php',
                    'todo',
                    $todoId
                );
            } elseif (!$isAutosave && (isset($data['titel']) || isset($data['beschreibung']) || isset($data['prioritaet']))) {
                // Normale Änderungen (außer Status)
                $relevanz = 'normal';
                if ($todo['prioritaet'] === 'kritisch') {
                    $relevanz = 'kritisch';
                } elseif ($todo['prioritaet'] === 'hoch') {
                    $relevanz = 'hoch';
                }
                
                // Benachrichtigungen für Admin, Techniker und Firmen-Admin erstellen
                createNotificationsForAction(
                    $userId,
                    $todo['company_id'],
                    'todo_updated',
                    'Aufgabe aktualisiert: ' . $todo['titel'],
                    'Die Aufgabe "' . $todo['titel'] . '" wurde von ' . $userName . ' aktualisiert.',
                    $relevanz,
                    'todos/index.php',
                    'todo',
                    $todoId
                );
            }
            
            todos_api_json_out(['success' => true, 'message' => 'Todo aktualisiert']);
            break;
            
        case 'PATCH':
            // Sortierung aktualisieren (für Drag & Drop)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['todos']) || !is_array($data['todos'])) {
                todos_api_json_out(['success' => false, 'error' => 'todos Array ist erforderlich'], 400);
                exit;
            }
            
            // folder_id behandeln: null, 0 oder 'null' -> NULL, sonst int
            $folderId = null;
            if (isset($data['folder_id'])) {
                if ($data['folder_id'] === null || $data['folder_id'] === 'null' || $data['folder_id'] === 0 || $data['folder_id'] === '0') {
                    $folderId = null;
                } else {
                    $folderId = (int)$data['folder_id'];
                }
            }
            
            $pdo->beginTransaction();
            
            try {
                foreach ($data['todos'] as $index => $todoData) {
                    if (!isset($todoData['todo_id'])) {
                        continue;
                    }
                    
                    $todoId = (int)$todoData['todo_id'];
                    $sortOrder = (int)$index;
                    
                    // Prüfen ob Todo existiert und Berechtigung
                    $checkStmt = $pdo->prepare("SELECT erstellt_von, zugewiesen_an FROM todos WHERE id = ?");
                    $checkStmt->execute([$todoId]);
                    $todo = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$todo) {
                        continue;
                    }
                    
                    // Berechtigung prüfen
                    $hasPermission = false;
                    if ($userRole === 'Admin' || $userRole === 'Techniker') {
                        $hasPermission = true;
                    } elseif ($todo['erstellt_von'] == $userId || $todo['zugewiesen_an'] == $userId) {
                        $hasPermission = true;
                    }
                    
                    if (!$hasPermission) {
                        continue;
                    }
                    
                    // Alte Sortierung löschen (um Duplikate zu vermeiden, besonders bei NULL folder_id)
                    $deleteStmt = $pdo->prepare("DELETE FROM todo_user_sorts WHERE user_id = ? AND todo_id = ?");
                    $deleteStmt->execute([$userId, $todoId]);
                    
                    // Neue Sortierung speichern
                    $sortStmt = $pdo->prepare("
                        INSERT INTO todo_user_sorts (user_id, todo_id, folder_id, sort_order, updated_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $sortStmt->execute([$userId, $todoId, $folderId, $sortOrder]);
                }
                
                $pdo->commit();
                
                todos_api_json_out(['success' => true, 'message' => 'Sortierung aktualisiert']);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Todo Sortierung Error: " . $e->getMessage());
                todos_api_json_out(['success' => false, 'error' => 'Fehler beim Speichern der Sortierung: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'DELETE':
            // Todo löschen
            if (!isset($_GET['id'])) {
                todos_api_json_out(['success' => false, 'error' => 'id fehlt'], 400);
                exit;
            }
            
            $todoId = (int)$_GET['id'];
            
            // Prüfen ob Todo existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT erstellt_von FROM todos WHERE id = ?");
            $checkStmt->execute([$todoId]);
            $todo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$todo) {
                todos_api_json_out(['success' => false, 'error' => 'Todo nicht gefunden'], 404);
                exit;
            }
            
            // Nur Ersteller oder Admin/Techniker können löschen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($todo['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                todos_api_json_out(['success' => false, 'error' => 'Keine Berechtigung'], 403);
                exit;
            }
            
            // Todo-Daten für Benachrichtigung abrufen (vor dem Löschen)
            $todoStmt = $pdo->prepare("SELECT titel, company_id FROM todos WHERE id = ?");
            $todoStmt->execute([$todoId]);
            $todoData = $todoStmt->fetch(PDO::FETCH_ASSOC);
            if ($todoData) decrypt_todo_row($todoData);
            $todoTitel = $todoData['titel'] ?? 'Unbekannt';
            $todoCompanyId = $todoData['company_id'] ?? null;
            
            $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ?");
            $stmt->execute([$todoId]);
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $todoCompanyId,
                'todo_deleted',
                'Aufgabe gelöscht: ' . $todoTitel,
                'Die Aufgabe "' . $todoTitel . '" wurde von ' . $userName . ' gelöscht.',
                'hoch',
                'todos/index.php',
                'todo',
                $todoId
            );
            
            todos_api_json_out(['success' => true, 'message' => 'Todo gelöscht']);
            break;
            
        default:
            todos_api_json_out(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
            break;
    }
} catch (PDOException $e) {
    error_log("Todos API Error: " . $e->getMessage());
    todos_api_json_out(['success' => false, 'error' => 'Datenbankfehler'], 500);
}
