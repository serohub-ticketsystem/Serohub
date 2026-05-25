<?php
/**
 * Zähler für die Sidebar (offene Aufgaben etc.).
 * Verwendet von sidebar.php und todos/api/open-count.php
 */

if (!function_exists('getOpenTodosCount')) {
    function getOpenTodosCount() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!isset($_SESSION['user_id'])) {
                return 0;
            }
            global $pdo;
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                return 0;
            }
            $userId = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return 0;
            }
            $userRole = $user['rolle'];
            $userCompanyId = $user['company_id'];
            $whereConditions = ["t.status IN ('offen', 'in_bearbeitung')"];
            $params = [];
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
                if ($selectedCompanyId) {
                    $whereConditions[] = "(t.company_id = :selected_company_id OR (t.ticket_id IS NOT NULL AND EXISTS (SELECT 1 FROM tickets tk WHERE tk.id = t.ticket_id AND tk.company_id = :selected_company_id2)))";
                    $params[':selected_company_id'] = $selectedCompanyId;
                    $params[':selected_company_id2'] = $selectedCompanyId;
                }
            } elseif ($userRole === 'Firmen-Admin') {
                if ($userCompanyId) {
                    $whereConditions[] = "(t.company_id = :user_company_id OR t.erstellt_von = :user_id OR t.zugewiesen_an = :user_id2 OR EXISTS (
                        SELECT 1 FROM tickets WHERE tickets.id = t.ticket_id AND tickets.company_id = :user_company_id2
                    ))";
                    $params[':user_company_id'] = $userCompanyId;
                    $params[':user_company_id2'] = $userCompanyId;
                    $params[':user_id'] = $userId;
                    $params[':user_id2'] = $userId;
                } else {
                    $whereConditions[] = "(t.erstellt_von = :user_id OR t.zugewiesen_an = :user_id2)";
                    $params[':user_id'] = $userId;
                    $params[':user_id2'] = $userId;
                }
            } else {
                $whereConditions[] = "(t.erstellt_von = :user_id OR t.zugewiesen_an = :user_id2)";
                $params[':user_id'] = $userId;
                $params[':user_id2'] = $userId;
            }
            $countMode = 'all';
            try {
                $sStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_todos_count' LIMIT 1");
                $sStmt->execute([$userId]);
                $row = $sStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['setting_value'] === 'no_folder') {
                    $countMode = 'no_folder';
                }
            } catch (Exception $e) { /* all */ } catch (Error $e) { /* all */ }
            if ($countMode === 'no_folder') {
                $whereConditions[] = "t.folder_id IS NULL";
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                $sql = "SELECT COUNT(*) as count FROM todos t " . $whereClause;
            } else {
                $whereConditions[] = "(t.folder_id IS NULL OR COALESCE(tf.is_private, 0) = 0 OR tf.erstellt_von = :count_uid OR EXISTS (SELECT 1 FROM todo_folder_members m WHERE m.folder_id = tf.id AND m.user_id = :count_uid2))";
                $params[':count_uid'] = $userId;
                $params[':count_uid2'] = $userId;
                $whereClause = "WHERE " . implode(" AND ", $whereConditions);
                $sql = "SELECT COUNT(*) as count FROM todos t LEFT JOIN todo_folders tf ON t.folder_id = tf.id " . $whereClause;
            }
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log("Fehler beim Zählen offener Aufgaben: " . $e->getMessage());
            return 0;
        } catch (Error $e) {
            error_log("Fehler beim Zählen offener Aufgaben: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('sidebarTicketsGetCountMode')) {
    function sidebarTicketsGetCountMode(PDO $pdo, int $userId): string
    {
        $allowed = ['all', 'company', 'filters'];
        try {
            $sStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_tickets_count' LIMIT 1");
            $sStmt->execute([$userId]);
            $row = $sStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && in_array($row['setting_value'], $allowed, true)) {
                return $row['setting_value'];
            }
        } catch (Exception $e) {
        } catch (Error $e) {
        }
        return 'company';
    }
}

if (!function_exists('sidebarTicketsGetFiltersSnapshot')) {
    /** @return array{status?:string,customer?:string,assignee?:string,company_id?:int|null} */
    function sidebarTicketsGetFiltersSnapshot(PDO $pdo, int $userId): array
    {
        try {
            $sStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'sidebar_tickets_filters' LIMIT 1");
            $sStmt->execute([$userId]);
            $row = $sStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
                $decoded = json_decode((string) $row['setting_value'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Exception $e) {
        } catch (Error $e) {
        }
        return ['status' => 'offen_combined', 'customer' => '', 'assignee' => '', 'company_id' => null];
    }
}

if (!function_exists('sidebarTicketsSyncCompanyFilterSnapshot')) {
    /** Filter-Snapshot (Modus „Aktive Filter“) an gewählte Nav-Firma anpassen. */
    function sidebarTicketsSyncCompanyFilterSnapshot(PDO $pdo, int $userId, ?int $companyId): void
    {
        if (sidebarTicketsGetCountMode($pdo, $userId) !== 'filters') {
            return;
        }
        $snapshot = sidebarTicketsGetFiltersSnapshot($pdo, $userId);
        $snapshot['company_id'] = $companyId;
        $payload = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
                VALUES (?, 'sidebar_tickets_filters', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
            ");
            $stmt->execute([$userId, $payload]);
        } catch (Exception $e) {
            error_log('sidebarTicketsSyncCompanyFilterSnapshot: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sidebarTicketsGetPinnedIds')) {
    /** @return int[] */
    function sidebarTicketsGetPinnedIds(PDO $pdo, int $userId): array
    {
        try {
            $pinStmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'service_pinned_tickets' LIMIT 1");
            $pinStmt->execute([$userId]);
            $pinRow = $pinStmt->fetch(PDO::FETCH_ASSOC);
            if ($pinRow && isset($pinRow['setting_value'])) {
                $decodedPins = json_decode((string) $pinRow['setting_value'], true);
                if (is_array($decodedPins)) {
                    return array_values(array_unique(array_filter(array_map('intval', $decodedPins), static fn($v) => $v > 0)));
                }
            }
        } catch (Exception $e) {
        } catch (Error $e) {
        }
        return [];
    }
}

if (!function_exists('sidebarTicketsApplyStatusFilter')) {
    function sidebarTicketsApplyStatusFilter(array &$whereConditions, array &$params, string $statusFilter, array $pinnedIds): void
    {
        $statusFilter = trim($statusFilter) !== '' ? trim($statusFilter) : 'offen_combined';
        if ($statusFilter === 'offen_combined') {
            $whereConditions[] = "t.status IN ('Neu', 'In Bearbeitung', 'Bestellung offen', 'Warteschlange', 'Geplant')";
            return;
        }
        if ($statusFilter === 'warteschlange') {
            $whereConditions[] = "t.status IN ('Warteschlange', 'Geplant')";
            return;
        }
        if ($statusFilter === 'ohne_bearbeitungszeit') {
            $whereConditions[] = "t.status = 'Geschlossen'";
            $whereConditions[] = "(t.bearbeitungszeit_minuten IS NULL OR t.bearbeitungszeit_minuten = 0)";
            return;
        }
        if ($statusFilter === 'angeheftet') {
            if (empty($pinnedIds)) {
                $whereConditions[] = '1 = 0';
                return;
            }
            $whereConditions[] = 't.id IN (' . implode(',', $pinnedIds) . ')';
            return;
        }
        $map = [
            'neu' => 'Neu',
            'in_bearbeitung' => 'In Bearbeitung',
            'bestellung_offen' => 'Bestellung offen',
            'geschlossen' => 'Geschlossen',
            'archiv' => 'Archiv',
            'geplant' => 'Geplant',
        ];
        $dbStatus = $map[$statusFilter] ?? null;
        if ($dbStatus !== null) {
            $whereConditions[] = 't.status = :sidebar_status_filter';
            $params[':sidebar_status_filter'] = $dbStatus;
        } else {
            $whereConditions[] = "t.status IN ('Neu', 'In Bearbeitung', 'Bestellung offen', 'Warteschlange', 'Geplant')";
        }
    }
}

if (!function_exists('getNewTicketsCount')) {
    function getNewTicketsCount()
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!isset($_SESSION['user_id'])) {
                return 0;
            }
            global $pdo;
            if (!isset($pdo)) {
                $configPath = dirname(__DIR__) . '/config.php';
                if (file_exists($configPath)) {
                    require_once $configPath;
                } else {
                    return 0;
                }
            }
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                return 0;
            }
            $userId = (int) $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return 0;
            }
            $userRole = $user['rolle'];
            $userCompanyId = $user['company_id'];
            $countMode = sidebarTicketsGetCountMode($pdo, $userId);
            $filterSnapshot = sidebarTicketsGetFiltersSnapshot($pdo, $userId);
            $pinnedIds = sidebarTicketsGetPinnedIds($pdo, $userId);

            $whereConditions = [];
            $whereConditions[] = "(t.company_id IS NULL OR c.status = 'aktiv')";
            $whereConditions[] = "t.titel NOT LIKE '[Gelöscht] %'";
            $params = [];

            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // keine rollenbedingte Einschränkung
            } elseif ($userRole === 'Firmen-Admin') {
                if ($userCompanyId) {
                    $whereConditions[] = "(t.company_id = :user_company_id OR EXISTS (
                        SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_user_id
                    ))";
                    $params[':user_company_id'] = $userCompanyId;
                    $params[':observer_user_id'] = $userId;
                } else {
                    $whereConditions[] = "EXISTS (
                        SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_user_id
                    )";
                    $params[':observer_user_id'] = $userId;
                }
            } else {
                $whereConditions[] = "(t.erstellt_von = :user_id OR EXISTS (
                    SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_user_id
                ))";
                $params[':user_id'] = $userId;
                $params[':observer_user_id'] = $userId;
            }

            if ($countMode === 'filters') {
                $statusFilter = isset($filterSnapshot['status']) ? (string) $filterSnapshot['status'] : 'offen_combined';
                sidebarTicketsApplyStatusFilter($whereConditions, $params, $statusFilter, $pinnedIds);
                $customerId = isset($filterSnapshot['customer']) ? trim((string) $filterSnapshot['customer']) : '';
                if ($customerId !== '' && ctype_digit($customerId)) {
                    $whereConditions[] = 't.customer_id = :sidebar_customer_filter';
                    $params[':sidebar_customer_filter'] = (int) $customerId;
                }
                $assigneeId = isset($filterSnapshot['assignee']) ? trim((string) $filterSnapshot['assignee']) : '';
                if ($assigneeId !== '' && ctype_digit($assigneeId)) {
                    $whereConditions[] = 't.zugewiesen_an = :sidebar_assignee_filter';
                    $params[':sidebar_assignee_filter'] = (int) $assigneeId;
                }
                $filterCompanyId = isset($filterSnapshot['company_id']) && $filterSnapshot['company_id'] !== null && $filterSnapshot['company_id'] !== ''
                    ? (int) $filterSnapshot['company_id']
                    : 0;
                if ($filterCompanyId > 0 && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                    $whereConditions[] = 't.company_id = :sidebar_company_filter';
                    $params[':sidebar_company_filter'] = $filterCompanyId;
                }
            } else {
                sidebarTicketsApplyStatusFilter($whereConditions, $params, 'offen_combined', $pinnedIds);
                if ($countMode === 'company' && ($userRole === 'Admin' || $userRole === 'Techniker')) {
                    $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null
                        ? (int) $_SESSION['selected_company_id']
                        : null;
                    if ($selectedCompanyId) {
                        $whereConditions[] = 't.company_id = :selected_company_id';
                        $params[':selected_company_id'] = $selectedCompanyId;
                    }
                }
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            $sql = 'SELECT COUNT(*) as count FROM tickets t LEFT JOIN companies c ON t.company_id = c.id ' . $whereClause;
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log('Fehler beim Zählen neuer Tickets: ' . $e->getMessage());
            return 0;
        } catch (Error $e) {
            error_log('Fehler beim Zählen neuer Tickets: ' . $e->getMessage());
            return 0;
        }
    }
}
