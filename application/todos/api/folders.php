<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
require_once dirname(__DIR__) . '/helper/ticket_folder.php';
require_once dirname(__DIR__) . '/helper/project_folder.php';
require_once dirname(__DIR__) . '/helper/encryption.php';

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
    $userCompanyId = $user['company_id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Prüfen ob is_private und todo_folder_members existieren (Migration ausgeführt)
$hasPrivateSupport = false;
try {
    $pdo->query("SELECT is_private FROM todo_folders LIMIT 1");
    $pdo->query("SELECT 1 FROM todo_folder_members LIMIT 1");
    $hasPrivateSupport = true;
} catch (PDOException $e) {
    // Migration noch nicht ausgeführt
}
// Prüfen ob is_ticket_system_folder existiert (Migration add_ticket_system_folder.sql)
$hasTicketSystemFolderSupport = false;
try {
    $pdo->query("SELECT is_ticket_system_folder FROM todo_folders LIMIT 1");
    $hasTicketSystemFolderSupport = true;
} catch (PDOException $e) {
    // Spalte fehlt noch
}
// Prüfen ob is_project_system_folder existiert (Migration 101_project_tasks_system_folder.sql)
$hasProjectSystemFolderSupport = false;
try {
    $pdo->query("SELECT is_project_system_folder FROM todo_folders LIMIT 1");
    $hasProjectSystemFolderSupport = true;
} catch (PDOException $e) {
    // Spalte fehlt noch
}

/**
 * company_id aus JSON-Body für öffentliche Ordner (null = keine Zuordnung).
 * Firmen-Admin: immer eigene Firma (Body wird ignoriert).
 */
function todos_folders_company_id_from_request(?array $data, bool $isPrivate, string $userRole, $userCompanyId): ?int
{
    if ($isPrivate) {
        return null;
    }
    if (!($userRole === 'Admin' || $userRole === 'Techniker' || ($userRole === 'Firmen-Admin' && $userCompanyId))) {
        return null;
    }
    if ($userRole === 'Firmen-Admin' && $userCompanyId) {
        return (int) $userCompanyId;
    }
    if (!is_array($data) || !array_key_exists('company_id', $data)) {
        return null;
    }
    $raw = $data['company_id'];
    if ($raw === null || $raw === '' || (int) $raw === 0) {
        return null;
    }
    return (int) $raw;
}

function todos_folders_validate_company_id(PDO $pdo, ?int $companyId): bool
{
    if ($companyId === null || $companyId <= 0) {
        return true;
    }
    $cStmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND status = 'aktiv' LIMIT 1");
    $cStmt->execute([$companyId]);
    return (bool) $cStmt->fetch();
}

try {
    switch ($method) {
        case 'GET':
            // Kollegen-Liste für Einladung (nur Techniker/Admin, ohne aktuellen User)
            if (!empty($_GET['candidates'])) {
                $stmt = $pdo->prepare("
                    SELECT id, vorname, nachname, email, rolle
                    FROM users
                    WHERE id != ? AND status = 'aktiv' AND rolle IN ('Techniker', 'Admin')
                    ORDER BY nachname, vorname
                ");
                $stmt->execute([$userId]);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'candidates' => $candidates]);
                break;
            }

            // Ordner abrufen (Sichtbarkeit: Eigene + eingeladene private Ordner + nicht-private nach alter Logik)
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            // Zähler wie die Aufgabenliste: bei Nav-Firmenauswahl nur Todos dieser Firma (Admin/Techniker)
            $filterTodoCountsByCompany = $companyFilter && ($userRole === 'Admin' || $userRole === 'Techniker');
            $todoJoinOnCounts = 'LEFT JOIN todos ON todos.folder_id = tf.id';
            if ($filterTodoCountsByCompany) {
                $todoJoinOnCounts = 'LEFT JOIN todos ON todos.folder_id = tf.id AND todos.company_id = :todo_company_filter';
            }
            $todoSubqCompany = $filterTodoCountsByCompany ? (' AND company_id = ' . (int) $companyFilter) : '';

            // Einstellung "Aufgaben aus Tickets einem Ordner zuordnen" (robust: aktiv wenn Wert nicht '0')
            $ticketTasksRequireFolderEnabled = false;
            $companyIdsForTicketFolder = [];
            if ($hasTicketSystemFolderSupport) {
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'ticket_tasks_require_folder' ORDER BY id DESC LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && isset($reqRow['setting_value']) && $reqRow['setting_value'] !== '' && $reqRow['setting_value'] !== '0') {
                    $ticketTasksRequireFolderEnabled = true;
                    // Ein globaler Ticket-Ordner (company_id = NULL) für alle Aufgaben mit ticket_id
                    $folderId = getOrCreateTicketTasksFolder($pdo, null, $userId);
                    if ($folderId) {
                        $upd = $pdo->prepare("
                            UPDATE todos SET folder_id = ?
                            WHERE ticket_id IS NOT NULL AND folder_id IS NULL
                        ");
                        $upd->execute([$folderId]);
                    }
                    $companyIdsForTicketFolder = [null];
                }
            }

            // Einstellung "Aufgaben aus Projekten einem Ordner zuordnen"
            $projectTasksRequireFolderEnabled = false;
            $companyIdsForProjectFolder = [];
            if ($hasProjectSystemFolderSupport) {
                $reqStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'project_tasks_require_folder' ORDER BY id DESC LIMIT 1");
                $reqStmt->execute([$userId]);
                $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
                if ($reqRow && isset($reqRow['setting_value']) && $reqRow['setting_value'] !== '' && $reqRow['setting_value'] !== '0') {
                    $projectTasksRequireFolderEnabled = true;
                    $folderId = getOrCreateProjectTasksFolder($pdo, null, $userId);
                    if ($folderId) {
                        $hasProjectId = false;
                        try { $c = $pdo->query("SHOW COLUMNS FROM todos LIKE 'project_id'"); $hasProjectId = $c && $c->rowCount() > 0; } catch (PDOException $e) {}
                        if ($hasProjectId) {
                            $upd = $pdo->prepare("UPDATE todos SET folder_id = ? WHERE project_id IS NOT NULL AND folder_id IS NULL");
                            $upd->execute([$folderId]);
                        }
                        $companyIdsForProjectFolder = [null];
                    }
                }
            }

            if ($hasPrivateSupport) {
                $nonPrivateCond = "COALESCE(tf.is_private, 0) = 0";
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $nonPrivateCond .= ($companyFilter ? " AND tf.company_id = :company_filter" : "");
                } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                    $nonPrivateCond .= " AND (tf.company_id = :user_company_id OR tf.company_id IS NULL)";
                } else {
                    $nonPrivateCond .= " AND tf.company_id IS NULL";
                }
                $ticketSysCol = $hasTicketSystemFolderSupport ? ", COALESCE(tf.is_ticket_system_folder, 0) as is_ticket_system_folder" : "";
                $ticketSysGroup = $hasTicketSystemFolderSupport ? ", tf.is_ticket_system_folder" : "";
                $projectSysCol = $hasProjectSystemFolderSupport ? ", COALESCE(tf.is_project_system_folder, 0) as is_project_system_folder" : "";
                $projectSysGroup = $hasProjectSystemFolderSupport ? ", tf.is_project_system_folder" : "";
                $sql = "
                    SELECT 
                        tf.id,
                        tf.name,
                        tf.company_id,
                        COALESCE(tf.is_private, 0) as is_private
                        $ticketSysCol
                        $projectSysCol,
                        tf.erstellt_von,
                        c.name as company_name,
                        COUNT(todos.id) as todo_count,
                        COUNT(CASE WHEN todos.status = 'offen' THEN 1 END) as open_todo_count
                    FROM todo_folders tf
                    LEFT JOIN companies c ON tf.company_id = c.id
                    $todoJoinOnCounts
                    WHERE (
                        (COALESCE(tf.is_private, 0) = 1 AND (tf.erstellt_von = :user_id OR EXISTS (SELECT 1 FROM todo_folder_members m WHERE m.folder_id = tf.id AND m.user_id = :user_id2)))
                        OR ($nonPrivateCond)
                    )
                    GROUP BY tf.id, tf.name, tf.company_id, tf.is_private$ticketSysGroup$projectSysGroup, tf.erstellt_von, c.name
                    ORDER BY COALESCE(tf.is_ticket_system_folder, 0) DESC, COALESCE(tf.is_project_system_folder, 0) DESC, tf.name ASC
                ";
                $params = [':user_id' => $userId, ':user_id2' => $userId];
                if ($companyFilter && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                    $params[':company_filter'] = $companyFilter;
                }
                if ($filterTodoCountsByCompany) {
                    $params[':todo_company_filter'] = $companyFilter;
                }
                if ($userRole === 'Firmen-Admin' && $userCompanyId) {
                    $params[':user_company_id'] = $userCompanyId;
                }
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, PDO::PARAM_INT);
                }
                $stmt->execute();
                $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Member-IDs pro Ordner nachladen
                foreach ($folders as &$f) {
                    decrypt_folder_row($f);
                    if (isset($f['company_name'])) $f['company_name'] = decrypt_from_db($f['company_name']);
                    $f['is_private'] = (int)($f['is_private'] ?? 0);
                    $f['is_ticket_system_folder'] = $hasTicketSystemFolderSupport ? (int)($f['is_ticket_system_folder'] ?? 0) : 0;
                    $f['is_project_system_folder'] = $hasProjectSystemFolderSupport ? (int)($f['is_project_system_folder'] ?? 0) : 0;
                    $f['member_ids'] = [];
                    if ($f['is_private']) {
                        $mStmt = $pdo->prepare("SELECT user_id FROM todo_folder_members WHERE folder_id = ?");
                        $mStmt->execute([$f['id']]);
                        $f['member_ids'] = array_map('intval', array_column($mStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id'));
                    }
                }
                unset($f);
            } else {
                $whereConditions = [];
                $params = [];
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    if ($companyFilter) {
                        $whereConditions[] = "tf.company_id = :company_filter";
                        $params[':company_filter'] = $companyFilter;
                    }
                } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                    $whereConditions[] = "(tf.company_id = :user_company_id OR tf.company_id IS NULL)";
                    $params[':user_company_id'] = $userCompanyId;
                } else {
                    $whereConditions[] = "tf.company_id IS NULL";
                }
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                $sql = "
                    SELECT tf.id, tf.name, tf.company_id, c.name as company_name,
                    COUNT(todos.id) as todo_count, COUNT(CASE WHEN todos.status = 'offen' THEN 1 END) as open_todo_count
                    FROM todo_folders tf
                    LEFT JOIN companies c ON tf.company_id = c.id
                    $todoJoinOnCounts
                    $whereClause
                    GROUP BY tf.id, tf.name, tf.company_id, c.name
                    ORDER BY tf.name ASC
                ";
                $stmt = $pdo->prepare($sql);
                if ($filterTodoCountsByCompany) {
                    $params[':todo_company_filter'] = $companyFilter;
                }
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, PDO::PARAM_INT);
                }
                $stmt->execute();
                $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($folders as &$f) {
                    decrypt_folder_row($f);
                    if (isset($f['company_name'])) $f['company_name'] = decrypt_from_db($f['company_name']);
                    $f['is_private'] = 0;
                    $f['is_ticket_system_folder'] = 0;
                    $f['is_project_system_folder'] = 0;
                    $f['member_ids'] = [];
                }
                unset($f);
            }

            // Ticket-Systemordner garantiert anzeigen: fehlende nachträglich anhängen (falls durch WHERE ausgefiltert)
            if ($ticketTasksRequireFolderEnabled && !empty($companyIdsForTicketFolder)) {
                $existingIds = array_flip(array_column($folders, 'id'));
                $ticketSysCol = $hasTicketSystemFolderSupport ? ", COALESCE(tf.is_ticket_system_folder, 0) as is_ticket_system_folder" : "";
                foreach ($companyIdsForTicketFolder as $cid) {
                    $fid = getOrCreateTicketTasksFolder($pdo, $cid, $userId);
                    if ($fid && !isset($existingIds[$fid])) {
                        $rowStmt = $pdo->prepare("
                            SELECT tf.id, tf.name, tf.company_id, COALESCE(tf.is_private, 0) as is_private
                            $ticketSysCol, tf.erstellt_von,
                            c.name as company_name,
                            (SELECT COUNT(*) FROM todos WHERE folder_id = tf.id{$todoSubqCompany}) as todo_count,
                            (SELECT COUNT(*) FROM todos WHERE folder_id = tf.id AND status = 'offen'{$todoSubqCompany}) as open_todo_count
                            FROM todo_folders tf
                            LEFT JOIN companies c ON tf.company_id = c.id
                            WHERE tf.id = ?
                        ");
                        $rowStmt->execute([$fid]);
                        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            decrypt_folder_row($row);
                            if (isset($row['company_name'])) $row['company_name'] = decrypt_from_db($row['company_name']);
                            $row['is_private'] = (int)($row['is_private'] ?? 0);
                            $row['is_ticket_system_folder'] = $hasTicketSystemFolderSupport ? 1 : 0;
                            $row['member_ids'] = [];
                            $row['todo_count'] = (int)($row['todo_count'] ?? 0);
                            $row['open_todo_count'] = (int)($row['open_todo_count'] ?? 0);
                            $folders[] = $row;
                            $existingIds[$fid] = true;
                        }
                    }
                }
                usort($folders, function ($a, $b) {
                    $sysA = !empty($a['is_ticket_system_folder']);
                    $sysB = !empty($b['is_ticket_system_folder']);
                    if ($sysA !== $sysB) return $sysB - $sysA;
                    $projA = !empty($a['is_project_system_folder']);
                    $projB = !empty($b['is_project_system_folder']);
                    if ($projA !== $projB) return $projB - $projA;
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
            }

            // Projekt-Systemordner garantiert anzeigen (wie bei Ticket)
            if ($projectTasksRequireFolderEnabled && !empty($companyIdsForProjectFolder)) {
                $existingIds = array_flip(array_column($folders, 'id'));
                $ticketSysCol = $hasTicketSystemFolderSupport ? ", COALESCE(tf.is_ticket_system_folder, 0) as is_ticket_system_folder" : "";
                $projectSysCol = $hasProjectSystemFolderSupport ? ", COALESCE(tf.is_project_system_folder, 0) as is_project_system_folder" : "";
                foreach ($companyIdsForProjectFolder as $cid) {
                    $fid = getOrCreateProjectTasksFolder($pdo, $cid, $userId);
                    if ($fid && !isset($existingIds[$fid])) {
                        $rowStmt = $pdo->prepare("
                            SELECT tf.id, tf.name, tf.company_id, COALESCE(tf.is_private, 0) as is_private
                            $ticketSysCol $projectSysCol, tf.erstellt_von,
                            c.name as company_name,
                            (SELECT COUNT(*) FROM todos WHERE folder_id = tf.id{$todoSubqCompany}) as todo_count,
                            (SELECT COUNT(*) FROM todos WHERE folder_id = tf.id AND status = 'offen'{$todoSubqCompany}) as open_todo_count
                            FROM todo_folders tf
                            LEFT JOIN companies c ON tf.company_id = c.id
                            WHERE tf.id = ?
                        ");
                        $rowStmt->execute([$fid]);
                        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            decrypt_folder_row($row);
                            if (isset($row['company_name'])) $row['company_name'] = decrypt_from_db($row['company_name']);
                            $row['is_private'] = (int)($row['is_private'] ?? 0);
                            $row['is_ticket_system_folder'] = $hasTicketSystemFolderSupport ? (int)($row['is_ticket_system_folder'] ?? 0) : 0;
                            $row['is_project_system_folder'] = $hasProjectSystemFolderSupport ? 1 : 0;
                            $row['member_ids'] = [];
                            $row['todo_count'] = (int)($row['todo_count'] ?? 0);
                            $row['open_todo_count'] = (int)($row['open_todo_count'] ?? 0);
                            $folders[] = $row;
                            $existingIds[$fid] = true;
                        }
                    }
                }
                usort($folders, function ($a, $b) {
                    $sysA = !empty($a['is_ticket_system_folder']);
                    $sysB = !empty($b['is_ticket_system_folder']);
                    if ($sysA !== $sysB) return $sysB - $sysA;
                    $projA = !empty($a['is_project_system_folder']);
                    $projB = !empty($b['is_project_system_folder']);
                    if ($projA !== $projB) return $projB - $projA;
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
            }

            // Wenn Einstellung deaktiviert: alle Ticket-Systemordner aus der Liste entfernen
            // Wenn Einstellung aktiv: nur den einen globalen Ticket-Ordner (company_id = NULL) anzeigen, andere Ticket-Systemordner ausblenden
            if ($hasTicketSystemFolderSupport) {
                if (!$ticketTasksRequireFolderEnabled) {
                    $folders = array_values(array_filter($folders, function ($f) {
                        return empty($f['is_ticket_system_folder']);
                    }));
                } else {
                    $folders = array_values(array_filter($folders, function ($f) {
                        if (empty($f['is_ticket_system_folder'])) return true;
                        return (string)($f['company_id'] ?? '') === '';
                    }));
                }
            }

            // Wenn Einstellung deaktiviert: alle Projekt-Systemordner ausblenden; wenn aktiv: nur globalen anzeigen
            if ($hasProjectSystemFolderSupport) {
                if (!$projectTasksRequireFolderEnabled) {
                    $folders = array_values(array_filter($folders, function ($f) {
                        return empty($f['is_project_system_folder']);
                    }));
                } else {
                    $folders = array_values(array_filter($folders, function ($f) {
                        if (empty($f['is_project_system_folder'])) return true;
                        return (string)($f['company_id'] ?? '') === '';
                    }));
                }
            }

            // Ticket-Systemordner und „Projektaufgaben“ ausblenden, wenn die angezeigte Anzahl 0 ist
            $folders = array_values(array_filter($folders, function ($f) {
                $count = (int) ($f['open_todo_count'] ?? 0);
                if (!empty($f['is_ticket_system_folder'])) {
                    return $count > 0;
                }
                $name = trim($f['name'] ?? '');
                if ($name === 'Projektaufgaben') {
                    return $count > 0;
                }
                return true;
            }));

            // Systemordner immer ganz vorne (Ticket, dann Projekt, dann Rest)
            usort($folders, function ($a, $b) {
                $sysA = !empty($a['is_ticket_system_folder']);
                $sysB = !empty($b['is_ticket_system_folder']);
                if ($sysA !== $sysB) return $sysB - $sysA;
                $projA = !empty($a['is_project_system_folder']);
                $projB = !empty($b['is_project_system_folder']);
                if ($projA !== $projB) return $projB - $projA;
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });

            echo json_encode(['success' => true, 'folders' => $folders]);
            break;
            
        case 'POST':
            // Neuen Ordner erstellen (privat nur ich, oder mit eingeladenen Kollegen)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            
            $name = encrypt_for_db(trim($data['name']));
            $isPrivate = $hasPrivateSupport && !empty($data['is_private']);
            $memberIds = [];
            if ($isPrivate && !empty($data['member_ids']) && is_array($data['member_ids'])) {
                $memberIds = array_map('intval', array_filter($data['member_ids']));
                $memberIds = array_unique($memberIds);
                $memberIds = array_values(array_diff($memberIds, [$userId])); // Erstellenden nicht als Mitglied
            }
            $companyId = todos_folders_company_id_from_request($data, $isPrivate, $userRole, $userCompanyId);
            if (!todos_folders_validate_company_id($pdo, $companyId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige oder inaktive Firma']);
                exit;
            }

            if ($hasPrivateSupport) {
                $stmt = $pdo->prepare("
                    INSERT INTO todo_folders (name, company_id, is_private, erstellt_von, erstellt_datum)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $companyId, $isPrivate ? 1 : 0, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO todo_folders (name, company_id, erstellt_von, erstellt_datum)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $companyId, $userId]);
            }
            
            $folderId = (int) $pdo->lastInsertId();

            if ($hasPrivateSupport && $isPrivate && !empty($memberIds)) {
                $ins = $pdo->prepare("INSERT INTO todo_folder_members (folder_id, user_id) VALUES (?, ?)");
                foreach ($memberIds as $uid) {
                    $ins->execute([$folderId, $uid]);
                }
            }

            $namePlain = decrypt_from_db($name);
            if ($isPrivate && !empty($memberIds)) {
                createNotificationsForUsers(
                    $memberIds,
                    'todo_folder_created',
                    'Ordner erstellt: ' . $namePlain,
                    'Der Ordner "' . $namePlain . '" wurde von ' . $userName . ' erstellt. Du hast Zugriff.',
                    'normal',
                    'todos/index.php',
                    'todo_folder',
                    $folderId,
                    $userId
                );
            } else {
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'todo_folder_created',
                    'Ordner erstellt: ' . $namePlain,
                    'Der Ordner "' . $namePlain . '" wurde von ' . $userName . ' erstellt.',
                    'normal',
                    'todos/index.php',
                    'todo_folder',
                    $folderId
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Ordner erfolgreich erstellt',
                'folder_id' => $folderId
            ]);
            break;
            
        case 'PUT':
            // Ordner aktualisieren (nur Erstellender darf bei privaten Ordnern; sonst wie bisher)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['folder_id']) || !isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'folder_id und Name sind erforderlich']);
                exit;
            }
            
            $folderId = (int)$data['folder_id'];
            $name = encrypt_for_db(trim($data['name']));
            $namePlain = decrypt_from_db($name);
            $isPrivate = $hasPrivateSupport && !empty($data['is_private']);
            $memberIds = [];
            if ($isPrivate && isset($data['member_ids']) && is_array($data['member_ids'])) {
                $memberIds = array_map('intval', array_filter($data['member_ids']));
                $memberIds = array_unique($memberIds);
                $memberIds = array_values(array_diff($memberIds, [$userId]));
            }
            
            $checkStmt = $pdo->prepare("SELECT company_id, erstellt_von, COALESCE(is_private, 0) as is_private FROM todo_folders WHERE id = ?");
            $checkStmt->execute([$folderId]);
            $folder = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$folder) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ordner nicht gefunden']);
                exit;
            }

            $folderIsTicketSys = false;
            $folderIsProjectSys = false;
            if ($hasTicketSystemFolderSupport) {
                try {
                    $sysStmt = $pdo->prepare("SELECT COALESCE(is_ticket_system_folder, 0) as is_ticket_system_folder, COALESCE(is_project_system_folder, 0) as is_project_system_folder FROM todo_folders WHERE id = ?");
                    $sysStmt->execute([$folderId]);
                    $sysRow = $sysStmt->fetch(PDO::FETCH_ASSOC);
                    if ($sysRow) {
                        $folderIsTicketSys = !empty($sysRow['is_ticket_system_folder']);
                        $folderIsProjectSys = !empty($sysRow['is_project_system_folder']);
                    }
                } catch (PDOException $e) {
                    // Spalten fehlen
                }
            }
            
            $isOwner = ($folder['erstellt_von'] == $userId);
            $hasPermission = $isOwner;
            if (!$hasPermission && ($folder['is_private'] ?? 0) == 0) {
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $folder['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                }
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            $newCompanyId = (int) ($folder['company_id'] ?? 0) ?: null;
            if (!$folderIsTicketSys && !$folderIsProjectSys) {
                $newCompanyId = todos_folders_company_id_from_request($data, $isPrivate, $userRole, $userCompanyId);
                if (!todos_folders_validate_company_id($pdo, $newCompanyId)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige oder inaktive Firma']);
                    exit;
                }
            }
            
            if ($hasPrivateSupport) {
                $stmt = $pdo->prepare("UPDATE todo_folders SET name = ?, is_private = ?, company_id = ? WHERE id = ?");
                $stmt->execute([$name, $isPrivate ? 1 : 0, $newCompanyId, $folderId]);
                $pdo->prepare("DELETE FROM todo_folder_members WHERE folder_id = ?")->execute([$folderId]);
                if ($isPrivate && !empty($memberIds)) {
                    $ins = $pdo->prepare("INSERT INTO todo_folder_members (folder_id, user_id) VALUES (?, ?)");
                    foreach ($memberIds as $uid) {
                        $ins->execute([$folderId, $uid]);
                    }
                }
            } else {
                $stmt = $pdo->prepare("UPDATE todo_folders SET name = ?, company_id = ? WHERE id = ?");
                $stmt->execute([$name, $newCompanyId, $folderId]);
            }
            
            if ($isPrivate && !empty($memberIds)) {
                createNotificationsForUsers(
                    $memberIds,
                    'todo_folder_updated',
                    'Ordner aktualisiert: ' . $namePlain,
                    'Der Ordner "' . $namePlain . '" wurde von ' . $userName . ' aktualisiert.',
                    'niedrig',
                    'todos/index.php',
                    'todo_folder',
                    $folderId,
                    $userId
                );
            } else {
                createNotificationsForAction(
                    $userId,
                    $newCompanyId,
                    'todo_folder_updated',
                    'Ordner aktualisiert: ' . $namePlain,
                    'Der Ordner "' . $namePlain . '" wurde von ' . $userName . ' aktualisiert.',
                    'niedrig',
                    'todos/index.php',
                    'todo_folder',
                    $folderId
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Ordner aktualisiert']);
            break;
            
        case 'DELETE':
            // Ordner löschen
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $folderId = (int)$_GET['id'];
            
            // Prüfen ob Ordner existiert und Berechtigung
            $checkStmt = $pdo->prepare("SELECT company_id, erstellt_von FROM todo_folders WHERE id = ?");
            $checkStmt->execute([$folderId]);
            $folder = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$folder) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ordner nicht gefunden']);
                exit;
            }
            
            // Systemordner für Ticketaufgaben und Projektaufgaben dürfen nicht gelöscht werden
            try {
                $sysStmt = $pdo->prepare("SELECT COALESCE(is_ticket_system_folder, 0) as is_ticket_system_folder, COALESCE(is_project_system_folder, 0) as is_project_system_folder FROM todo_folders WHERE id = ?");
                $sysStmt->execute([$folderId]);
                $sysRow = $sysStmt->fetch(PDO::FETCH_ASSOC);
                if ($sysRow && !empty($sysRow['is_ticket_system_folder'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Dieser Ordner ist der Systemordner für Ticketaufgaben und kann nicht gelöscht werden.']);
                    exit;
                }
                if ($sysRow && !empty($sysRow['is_project_system_folder'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Dieser Ordner ist der Systemordner für Projektaufgaben und kann nicht gelöscht werden.']);
                    exit;
                }
            } catch (PDOException $e) {
                // Spalten existieren ggf. noch nicht
            }
            
            // Berechtigung prüfen
            $hasPermission = false;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $hasPermission = true;
            } elseif ($userRole === 'Firmen-Admin' && $folder['company_id'] == $userCompanyId) {
                $hasPermission = true;
            } elseif ($folder['erstellt_von'] == $userId) {
                $hasPermission = true;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Ordnernamen für Benachrichtigung abrufen (vor dem Löschen)
            $folderNameStmt = $pdo->prepare("SELECT name FROM todo_folders WHERE id = ?");
            $folderNameStmt->execute([$folderId]);
            $folderName = decrypt_from_db($folderNameStmt->fetchColumn() ?: '') ?: 'Unbekannt';
            
            $stmt = $pdo->prepare("DELETE FROM todo_folders WHERE id = ?");
            $stmt->execute([$folderId]);
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $folder['company_id'],
                'todo_folder_deleted',
                'Ordner gelöscht: ' . $folderName,
                'Der Ordner "' . $folderName . '" wurde von ' . $userName . ' gelöscht.',
                'hoch',
                'todos/index.php',
                'todo_folder',
                $folderId
            );
            
            echo json_encode(['success' => true, 'message' => 'Ordner gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Todo Folders API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
