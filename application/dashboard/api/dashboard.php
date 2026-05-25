<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__, 2) . '/assets/email.php';
require_once dirname(__DIR__, 2) . '/customers/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id, vorname, nachname, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // User Settings laden
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userSettings = [];
    foreach ($settingsRows as $row) {
        $userSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    $user['two_factor_enabled'] = isset($userSettings['2fa_enabled']) && $userSettings['2fa_enabled'] === '1';
    $user['daten_bestaetigt'] = isset($userSettings['daten_bestaetigt']) && $userSettings['daten_bestaetigt'] === '1';
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userCustomerId = $user['customer_id'];
    
    // Hilfsfunktion: Fälligkeitsdatum (frage_datum) für Wartungsvertrag-Zahlung nach Rhythmus/Tag
    $computeWartungFrageDatum = function ($rhythm, $tag, $today) {
        $dayOfMonth = (int)date('j', strtotime($today));
        $dayOfWeek = (int)date('N', strtotime($today));
        $month = (int)date('n', strtotime($today));
        $year = (int)date('Y', strtotime($today));
        $tag = (int)$tag;
        if ($rhythm === 'woechentlich') {
            $diff = $dayOfWeek - $tag;
            if ($diff < 0) $diff += 7;
            return date('Y-m-d', strtotime($today . " - $diff days"));
        }
        if ($rhythm === 'monatlich') {
            $lastDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
            $d = min($tag, $lastDay);
            return date('Y-m-d', mktime(0, 0, 0, $month, $d, $year));
        }
        if ($rhythm === 'vierteljaehrlich') {
            $quarterMonth = (int)floor(($month - 1) / 3) * 3 + 1;
            $lastDay = (int)date('t', mktime(0, 0, 0, $quarterMonth, 1, $year));
            $d = min($tag, $lastDay);
            return date('Y-m-d', mktime(0, 0, 0, $quarterMonth, $d, $year));
        }
        if ($rhythm === 'halbjaehrlich') {
            $halfMonth = $month <= 6 ? 1 : 7;
            $lastDay = (int)date('t', mktime(0, 0, 0, $halfMonth, 1, $year));
            $d = min($tag, $lastDay);
            return date('Y-m-d', mktime(0, 0, 0, $halfMonth, $d, $year));
        }
        if ($rhythm === 'jaehrlich') {
            $lastDay = (int)date('t', mktime(0, 0, 0, 1, 1, $year));
            $d = min($tag, $lastDay);
            return date('Y-m-d', mktime(0, 0, 0, 1, $d, $year));
        }
        return null;
    };
    
    // Zusammenfassung für Mobile/API (z. B. iOS-App)
    if ($action === 'get_summary') {
        $stats = [
            'service_tickets_open' => 0,
            'todos_open' => 0,
            'orders_open' => 0,
            'projects_open' => 0,
            'notifications_unread' => 0,
            'devices_count' => 0,
            'customers_count' => 0,
            'companies_count' => 0
        ];
        if (function_exists('getUnreadNotificationCount')) {
            $stats['notifications_unread'] = getUnreadNotificationCount($userId);
        }
        // Tickets
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status NOT IN ('Geschlossen', 'Archiv')");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE (c.company_id = :company_id OR t.company_id = :company_id) AND t.status NOT IN ('Geschlossen', 'Archiv')");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE (erstellt_von = :user_id OR zugewiesen_an = :user_id) AND status NOT IN ('Geschlossen', 'Archiv')");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['service_tickets_open'] = (int)($result['count'] ?? 0);
        }
        // Todos
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM todos WHERE status != 'erledigt'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['todos_open'] = (int)($result['count'] ?? 0);
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE company_id = :company_id AND status != 'erledigt'");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['todos_open'] = (int)($result['count'] ?? 0);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE zugewiesen_an = :user_id AND status != 'erledigt'");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['todos_open'] = (int)($result['count'] ?? 0);
        }
        // Bestellungen
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['orders_open'] = (int)($result['count'] ?? 0);
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE (o.company_id = :company_id OR c.company_id = :company_id) AND o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['orders_open'] = (int)($result['count'] ?? 0);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE erstellt_von = :user_id AND status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['orders_open'] = (int)($result['count'] ?? 0);
        }
        // Projekte (nur Admin/Techniker)
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
            if ($selectedCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE company_id = ? AND status NOT IN ('Abgeschlossen', 'Archiviert')");
                $stmt->execute([$selectedCompanyId]);
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status NOT IN ('Abgeschlossen', 'Archiviert')");
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['projects_open'] = (int)($result['count'] ?? 0);
        } else {
            $stats['projects_open'] = 0;
        }
        // Geräte/Kunden/Firmen (Firmen-Admin)
        if ($userRole === 'Firmen-Admin' && $userCompanyId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices d LEFT JOIN customers c ON d.customer_id = c.id WHERE (d.company_id = :company_id OR c.company_id = :company_id) AND d.status = 'aktiv'");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['devices_count'] = (int)($result['count'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = :company_id AND status = 'aktiv'");
            $stmt->bindValue(':company_id', $userCompanyId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['customers_count'] = (int)($result['count'] ?? 0);
        }
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers WHERE status = 'aktiv'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['customers_count'] = (int)($result['count'] ?? 0);
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies WHERE status = 'aktiv'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['companies_count'] = (int)($result['count'] ?? 0);
        }
        echo json_encode(['success' => true, 'user' => $user, 'summary' => $stats]);
        exit;
    }
    
    if ($method === 'POST' && $action === 'dismiss_card') {
        // Card verwerfen
        $cardId = $_POST['card_id'] ?? '';
        if (empty($cardId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Card-ID fehlt']);
            exit;
        }

        // Angeheftete Ticket-Card: "Loslösen" = Pin entfernen (user-bezogen)
        if (strpos((string)$cardId, 'system_service_pinned_') === 0) {
            $pinnedTicketId = (int)substr((string)$cardId, strlen('system_service_pinned_'));
            if ($pinnedTicketId > 0) {
                $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'service_pinned_tickets' LIMIT 1");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ids = [];
                if ($row && isset($row['setting_value'])) {
                    $decoded = json_decode((string)$row['setting_value'], true);
                    if (is_array($decoded)) {
                        $ids = array_values(array_unique(array_filter(array_map('intval', $decoded), fn($v) => $v > 0)));
                    }
                }
                $ids = array_values(array_filter($ids, fn($id) => (int)$id !== $pinnedTicketId));
                $encoded = json_encode($ids);
                $save = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value)
                    VALUES (?, 'service_pinned_tickets', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $save->execute([$userId, $encoded]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
        
        // System-Card "Bestellung angekommen": aktuelle Anzahl speichern (zählerbasiert)
        if ($cardId === 'system_order_arrived') {
            $count = 0;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Angekommen'");
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Angekommen' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Angekommen' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'order_arrived_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // System-Card "Bestellung im Lager": aktuelle Anzahl speichern (zählerbasiert)
        if ($cardId === 'system_order_in_warehouse') {
            $count = 0;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Im Lager'");
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Im Lager' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Im Lager' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'order_in_warehouse_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // System-Card "Bestellung beim Kunden": aktuelle Anzahl speichern (zählerbasiert)
        if ($cardId === 'system_order_at_customer') {
            $count = 0;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Beim Kunden'");
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Beim Kunden' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Beim Kunden' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'order_at_customer_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // System-Card "Tickets nicht abgerechnet": aktuelle Anzahl speichern (zählerbasiert)
        if ($cardId === 'system_tickets_nicht_abgerechnet') {
            $count = 0;
            if ($userRole === 'Admin') {
                $stmt = $pdo->query("
                    SELECT COUNT(*) as cnt FROM tickets 
                    WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                    AND (abgerechnet IS NULL OR abgerechnet != 1)
                ");
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'tickets_nicht_abgerechnet_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }

        // System-Card "Tickets ohne Bearbeitungszeit": aktuelle Anzahl speichern – Card erscheint wieder, wenn neue Tickets ohne Bearbeitungszeit dazukommen
        if ($cardId === 'system_tickets_closed_no_time') {
            $count = 0;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("
                    SELECT COUNT(*) as cnt FROM tickets 
                    WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                    AND (bearbeitungszeit_minuten IS NULL OR bearbeitungszeit_minuten = 0)
                ");
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'tickets_closed_no_time_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }

        // System-Card "Tickets mir zugewiesen": aktuelle Anzahl speichern – Card wieder anzeigen, wenn neues Ticket zugewiesen wird
        if ($cardId === 'system_tickets_assigned') {
            $count = 0;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt FROM tickets
                    WHERE zugewiesen_an = ? AND status NOT IN ('Geschlossen', 'Archiv')
                ");
                $stmt->execute([$userId]);
                $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (?, 'tickets_assigned_card_dismissed_count', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $countStr = (string)$count;
            $stmt->execute([$userId, $countStr, $countStr]);
            echo json_encode(['success' => true]);
            exit;
        }

        // In user_settings speichern
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'dismissed_cards'");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dismissedCards = json_decode($result ? ($result['setting_value'] ?? '[]') : '[]', true);
        
        if (!in_array($cardId, $dismissedCards)) {
            $dismissedCards[] = $cardId;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value) 
            VALUES (?, 'dismissed_cards', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$userId, json_encode($dismissedCards), json_encode($dismissedCards)]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'get_cards') {
        // System-Cards werden immer angezeigt (Einstellung aus system_settings wird ignoriert)
        $isCardEnabled = function($cardId) {
            return true;
        };
        
        // Dashboard-Cards aus DB laden (company_id NULL = alle, sonst nur für diese Firma)
        if ($userCompanyId) {
            $stmt = $pdo->prepare("
                SELECT id, titel, nachricht, bild, bild_dark, button_text, button_link, typ
                FROM dashboard_cards
                WHERE aktiv = 1 AND (company_id IS NULL OR company_id = ?)
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute([$userCompanyId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, titel, nachricht, bild, bild_dark, button_text, button_link, typ
                FROM dashboard_cards
                WHERE aktiv = 1 AND company_id IS NULL
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute();
        }
        $dbCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verworfenen Cards des Users laden
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'dismissed_cards'");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dismissedCards = json_decode($result ? ($result['setting_value'] ?? '[]') : '[]', true);
        
        $cards = [];
        foreach ($dbCards as $row) {
            $cardId = (string)$row['id'];
            if (in_array($cardId, $dismissedCards, true)) {
                continue;
            }
            $link = !empty($row['button_link']) ? $row['button_link'] : '#';
            if ($link !== '#' && !preg_match('#^https?://#', $link) && $link[0] !== '/') {
                $link = BASE_URL . ltrim($link, '/');
            }
            $cards[] = [
                'id' => $cardId,
                'type' => $row['typ'] ?? 'info',
                'title' => $row['titel'],
                'message' => $row['nachricht'],
                'action' => !empty($row['button_text']) ? $row['button_text'] : 'Mehr erfahren',
                'link' => $link,
                'icon' => 'custom',
                'bild' => !empty($row['bild']) ? $row['bild'] : null,
                'bild_dark' => !empty($row['bild_dark']) ? $row['bild_dark'] : null
            ];
        }
        
        // —— System-Cards: Reihenfolge = Erinnerung ganz vorne, dann Bestellungen gebündelt, dann Tickets gebündelt ——
        // Dazu zuerst Ticket-Cards unshiften (erscheinen hinten), dann Bestellungs-Cards (erscheinen Mitte), dann Wartung (erscheint vorne).
        
        // System-Card: Geschlossene Tickets nicht abgerechnet (Zähler: aktuelle Anzahl minus beim letzten Wegklicken, nur Admin)
        $currentNichtAbgerechnet = 0;
        if ($userRole === 'Admin' && $isCardEnabled('system_tickets_nicht_abgerechnet')) {
            $stmt = $pdo->query("
                SELECT COUNT(*) as cnt FROM tickets 
                WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                AND (abgerechnet IS NULL OR abgerechnet != 1)
            ");
            $currentNichtAbgerechnet = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'tickets_nicht_abgerechnet_card_dismissed_count'");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dismissedNichtAbgerechnet = (int)($result['setting_value'] ?? 0);
        $nichtAbgerechnetCount = max(0, $currentNichtAbgerechnet - $dismissedNichtAbgerechnet);
        if ($nichtAbgerechnetCount > 0 && $isCardEnabled('system_tickets_nicht_abgerechnet')) {
            $serviceLink = rtrim(BASE_URL, '/') . '/tickets/#geschlossen';
            $msg = $currentNichtAbgerechnet === 1
                ? '1 geschlossenes Ticket ist noch nicht abgerechnet.'
                : $currentNichtAbgerechnet . ' geschlossene Tickets sind noch nicht abgerechnet.';
            array_unshift($cards, [
                'id' => 'system_tickets_nicht_abgerechnet',
                'type' => 'warning',
                'title' => 'Tickets nicht abgerechnet',
                'message' => $msg,
                'action' => 'Tickets anzeigen',
                'link' => $serviceLink,
                'icon' => 'custom',
                'bild' => null,
                'bild_dark' => null,
                'count' => $currentNichtAbgerechnet
            ]);
        }
        
        // System-Card: Geschlossene Tickets ohne Bearbeitungszeit – anzeigen wenn aktuelle Anzahl > beim Verwerfen gespeicherte Anzahl (dann gibt es wieder „neue“ ohne Bearbeitungszeit)
        $currentClosedNoTime = 0;
        if (($userRole === 'Admin' || $userRole === 'Techniker') && $isCardEnabled('system_tickets_closed_no_time')) {
            $stmt = $pdo->query("
                SELECT COUNT(*) as cnt FROM tickets 
                WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                AND (bearbeitungszeit_minuten IS NULL OR bearbeitungszeit_minuten = 0)
            ");
            $currentClosedNoTime = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'tickets_closed_no_time_card_dismissed_count'");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dismissedClosedNoTime = (int)($result['setting_value'] ?? 0);
            $closedNoTimeCount = max(0, $currentClosedNoTime - $dismissedClosedNoTime);
            if ($closedNoTimeCount > 0) {
                $serviceLink = rtrim(BASE_URL, '/') . '/tickets/#ohne-bearbeitungszeit';
                $msg = $currentClosedNoTime === 1
                    ? '1 geschlossenes Ticket hat noch keine Bearbeitungszeit.'
                    : $currentClosedNoTime . ' geschlossene Tickets haben noch keine Bearbeitungszeit.';
                array_unshift($cards, [
                    'id' => 'system_tickets_closed_no_time',
                    'type' => 'warning',
                    'title' => 'Tickets ohne Bearbeitungszeit',
                    'message' => $msg,
                    'action' => 'Tickets anzeigen',
                    'link' => $serviceLink,
                    'icon' => 'custom',
                    'bild' => null,
                    'bild_dark' => null,
                    'count' => $currentClosedNoTime
                ]);
            }
        }
        
        // System-Card: Tickets mir zugewiesen (nur Admin/Techniker) – anzeigen wenn aktuelle Anzahl > beim letzten Verwerfen gespeicherte Anzahl (neues Ticket zugewiesen)
        $assignedTicketsCount = 0;
        if (($userRole === 'Admin' || $userRole === 'Techniker') && $isCardEnabled('system_tickets_assigned')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM tickets
                WHERE zugewiesen_an = ? AND status NOT IN ('Geschlossen', 'Archiv')
            ");
            $stmt->execute([$userId]);
            $assignedTicketsCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'tickets_assigned_card_dismissed_count'");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ticketsAssignedDismissedCount = (int)($result['setting_value'] ?? 0);
        if ($assignedTicketsCount > 0 && $assignedTicketsCount > $ticketsAssignedDismissedCount && $isCardEnabled('system_tickets_assigned')) {
            $serviceLink = rtrim(BASE_URL, '/') . '/tickets/#zugewiesen';
            $msg = $assignedTicketsCount === 1
                ? '1 Ticket ist dir zugewiesen.'
                : $assignedTicketsCount . ' Tickets sind dir zugewiesen.';
            array_unshift($cards, [
                'id' => 'system_tickets_assigned',
                'type' => 'info',
                'title' => 'Tickets mir zugewiesen',
                'message' => $msg,
                'action' => 'Tickets anzeigen',
                'link' => $serviceLink,
                'icon' => 'custom',
                'bild' => null,
                'bild_dark' => null,
                'count' => $assignedTicketsCount
            ]);
        }

        // System-Cards: Angeheftete Tickets (user-bezogen, eine Card pro Auftrag)
        if ($isCardEnabled('system_service_pinned')) {
            try {
                $pinnedSettingRaw = $userSettings['service_pinned_tickets'] ?? '[]';
                $pinnedIdsDecoded = json_decode((string)$pinnedSettingRaw, true);
                $pinnedIds = is_array($pinnedIdsDecoded)
                    ? array_values(array_unique(array_filter(array_map('intval', $pinnedIdsDecoded), fn($v) => $v > 0)))
                    : [];
                if (!empty($pinnedIds)) {
                $placeholders = implode(',', array_fill(0, count($pinnedIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT id, ticket_nummer, titel, status
                    FROM tickets
                    WHERE id IN ($placeholders) AND titel NOT LIKE '[Gelöscht] %'
                ");
                $stmt->execute($pinnedIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowsById = [];
                foreach ($rows as $row) {
                    $rowsById[(int)$row['id']] = $row;
                }

                // Reihenfolge wie in user_settings (zuletzt angeheftet i.d.R. hinten)
                for ($i = count($pinnedIds) - 1; $i >= 0; $i--) {
                    $pinnedId = (int)$pinnedIds[$i];
                    if (!isset($rowsById[$pinnedId])) {
                        continue;
                    }
                    $t = $rowsById[$pinnedId];
                    $ticketNummer = trim((string)($t['ticket_nummer'] ?? ''));
                    $titel = trim((string)($t['titel'] ?? ''));
                    $status = trim((string)($t['status'] ?? ''));
                    $titleText = $ticketNummer !== '' ? $ticketNummer : ('Ticket #' . $pinnedId);
                    $messageText = $titel !== '' ? $titel : 'Angehefteter Ticket';
                    if ($status !== '') {
                        $messageText .= ' (' . $status . ')';
                    }

                    array_unshift($cards, [
                        'id' => 'system_service_pinned_' . $pinnedId,
                        'type' => 'info',
                        'title' => $titleText,
                        'message' => $messageText,
                        'action' => 'Ticket öffnen',
                        'link' => rtrim(BASE_URL, '/') . '/tickets/view.php?id=' . $pinnedId,
                        'icon' => 'custom',
                        'bild' => null,
                        'bild_dark' => null,
                        'count' => 1
                    ]);
                }
            }
            } catch (\Throwable $e) {
                // Ignorieren
            }
        }
        
        // System-Card: Bestellung angekommen (Zähler: aktuelle Anzahl minus beim letzten Wegklicken gespeicherte Anzahl)
        $currentCount = 0;
        if ($isCardEnabled('system_order_arrived')) {
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Angekommen'");
                $currentCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Angekommen' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $currentCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Angekommen' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $currentCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'order_arrived_card_dismissed_count'");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dismissedCount = (int)($result['setting_value'] ?? 0);
            $orderArrivedCount = max(0, $currentCount - $dismissedCount);
            
            if ($orderArrivedCount > 0) {
                $ordersLink = rtrim(BASE_URL, '/') . '/orders/?neu=' . $orderArrivedCount . '#angekommen';
                $msg = $orderArrivedCount === 1
                    ? '1 Bestellung ist angekommen.'
                    : $orderArrivedCount . ' Bestellungen sind angekommen.';
                array_unshift($cards, [
                    'id' => 'system_order_arrived',
                    'type' => 'info',
                    'title' => 'Bestellung angekommen',
                    'message' => $msg,
                    'action' => 'Zu Bestellungen',
                    'link' => $ordersLink,
                    'icon' => 'custom',
                    'bild' => null,
                    'bild_dark' => null,
                    'count' => $orderArrivedCount
                ]);
            }
        }
        
        // System-Card: Bestellung beim Kunden (Zähler wie oben)
        $currentAtCustomer = 0;
        if ($isCardEnabled('system_order_at_customer')) {
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Beim Kunden'");
                $currentAtCustomer = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Beim Kunden' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $currentAtCustomer = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Beim Kunden' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $currentAtCustomer = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'order_at_customer_card_dismissed_count'");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dismissedAtCustomer = (int)($result['setting_value'] ?? 0);
            $orderAtCustomerCount = max(0, $currentAtCustomer - $dismissedAtCustomer);
            
            if ($orderAtCustomerCount > 0) {
                $ordersLink = rtrim(BASE_URL, '/') . '/orders/?neu=' . $orderAtCustomerCount . '#beim-kunden';
                $msg = $orderAtCustomerCount === 1
                    ? '1 Bestellung ist beim Kunden.'
                    : $orderAtCustomerCount . ' Bestellungen sind beim Kunden.';
                array_unshift($cards, [
                    'id' => 'system_order_at_customer',
                    'type' => 'info',
                    'title' => 'Bestellung beim Kunden',
                    'message' => $msg,
                    'action' => 'Zu Bestellungen',
                    'link' => $ordersLink,
                    'icon' => 'custom',
                    'bild' => null,
                    'bild_dark' => null,
                    'count' => $orderAtCustomerCount
                ]);
            }
        }
        
        // System-Card: Bestellung im Lager (Zähler wie oben) – direkt nach den anderen Bestellungs-Cards
        $currentInWarehouse = 0;
        if ($isCardEnabled('system_order_in_warehouse')) {
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Im Lager'");
                $currentInWarehouse = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.status = 'Im Lager' AND (o.company_id = ? OR c.company_id = ?)");
                $stmt->execute([$userCompanyId, $userCompanyId]);
                $currentInWarehouse = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'Im Lager' AND erstellt_von = ?");
                $stmt->execute([$userId]);
                $currentInWarehouse = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
            $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'order_in_warehouse_card_dismissed_count'");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dismissedInWarehouse = (int)($result['setting_value'] ?? 0);
            $orderInWarehouseCount = max(0, $currentInWarehouse - $dismissedInWarehouse);
            
            if ($orderInWarehouseCount > 0) {
                $ordersLink = rtrim(BASE_URL, '/') . '/orders/?neu=' . $orderInWarehouseCount . '#im-lager';
                $msg = $orderInWarehouseCount === 1
                    ? '1 Bestellung ist im Lager.'
                    : $orderInWarehouseCount . ' Bestellungen sind im Lager.';
                array_unshift($cards, [
                    'id' => 'system_order_in_warehouse',
                    'type' => 'info',
                    'title' => 'Bestellung im Lager',
                    'message' => $msg,
                    'action' => 'Zu Bestellungen',
                    'link' => $ordersLink,
                    'icon' => 'custom',
                    'bild' => null,
                    'bild_dark' => null,
                    'count' => $orderInWarehouseCount
                ]);
            }
        }
        
        // System-Card: Wartungsvertrag – Hat Kunde gezahlt? (nur Admin, am Fälligkeitstag und danach bis zur Beantwortung)
        if ($userRole === 'Admin' && $isCardEnabled('system_wartung_zahlung')) {
            $today = date('Y-m-d');
            $cols = $pdo->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_COLUMN);
            $hasWartungCols = in_array('hat_wartungsvertrag', $cols) && in_array('wartung_zahlungsrhythmus', $cols) && in_array('wartung_zahlungstag', $cols);
            if ($hasWartungCols) {
                $stmt = $pdo->query("
                    SELECT c.id, c.name, c.wartung_zahlungsrhythmus AS rhythm, c.wartung_zahlungstag AS tag
                    FROM companies c
                    WHERE c.hat_wartungsvertrag = 1
                    AND c.wartung_zahlungsrhythmus IS NOT NULL AND c.wartung_zahlungsrhythmus != ''
                    AND c.wartung_zahlungstag IS NOT NULL AND c.wartung_zahlungstag > 0
                    AND c.status IN ('aktiv', 'inaktiv')
                ");
                $companiesWithWartung = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($companiesWithWartung as &$row) {
                    $row['name'] = decrypt_from_db($row['name'] ?? null);
                }
                unset($row);
                foreach ($companiesWithWartung as $row) {
                    $tag = (int)$row['tag'];
                    $r = $row['rhythm'];
                    $frage_datum = $computeWartungFrageDatum($r, $tag, $today);
                    if ($frage_datum === null || $frage_datum > $today) continue;
                    $companyId = (int)$row['id'];
                    $stmt = $pdo->prepare("SELECT status, naechste_frage_datum FROM company_wartung_zahlung_frage WHERE company_id = ? AND frage_datum = ?");
                    $stmt->execute([$companyId, $frage_datum]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing && in_array($existing['status'], ['paid', 'inaktiv', 'gesperrt', 'skipped'], true)) continue;
                    if ($existing && $existing['status'] === 'remind_5' && $existing['naechste_frage_datum'] && $existing['naechste_frage_datum'] > $today) continue;
                    array_unshift($cards, [
                        'id' => 'wartung_zahlung_' . $companyId,
                        'type' => 'wartung_zahlung',
                        'title' => 'Wartungsvertrag Erinnerung',
                        'message' => 'Hat ' . $row['name'] . ' gezahlt?',
                        'company_id' => $companyId,
                        'company_name' => $row['name'],
                        'frage_datum' => $frage_datum,
                        'action' => '',
                        'link' => '#',
                        'icon' => 'custom',
                        'bild' => null,
                        'bild_dark' => null
                    ]);
                }
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, f.naechste_frage_datum AS frage_datum FROM company_wartung_zahlung_frage f
                    INNER JOIN companies c ON c.id = f.company_id
                    LEFT JOIN company_wartung_zahlung_frage f2 ON f2.company_id = c.id AND f2.frage_datum = f.naechste_frage_datum AND f2.status IN ('paid', 'inaktiv', 'gesperrt')
                    WHERE f.status = 'remind_5' AND f.naechste_frage_datum IS NOT NULL AND f.naechste_frage_datum <= ?
                    AND c.status IN ('aktiv', 'inaktiv')
                    AND f2.id IS NULL
                ");
                $stmt->execute([$today]);
                while ($remind = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $remind['name'] = decrypt_from_db($remind['name'] ?? null);
                    $companyId = (int)$remind['id'];
                    $already = false;
                    foreach ($cards as $c) {
                        if (isset($c['company_id']) && (int)$c['company_id'] === $companyId) { $already = true; break; }
                    }
                    if (!$already) {
                        array_unshift($cards, [
                            'id' => 'wartung_zahlung_' . $companyId,
                            'type' => 'wartung_zahlung',
                            'title' => 'Wartungsvertrag Erinnerung',
                            'message' => 'Hat ' . $remind['name'] . ' gezahlt?',
                            'company_id' => $companyId,
                            'company_name' => $remind['name'],
                            'frage_datum' => $remind['frage_datum'],
                            'action' => '',
                            'link' => '#',
                            'icon' => 'custom',
                            'bild' => null,
                            'bild_dark' => null
                        ]);
                    }
                }
                // Mahn-E-Mail-Versand für Erinnerungs-Cards laden (bleibt nach Reload sichtbar)
                $wartungPairs = [];
                foreach ($cards as $c) {
                    if (isset($c['company_id']) && isset($c['frage_datum'])) {
                        $wartungPairs[(int)$c['company_id']] = $c['frage_datum'];
                    }
                }
                if ($wartungPairs !== []) {
                    try {
                        $pairs = [];
                        $params = [];
                        foreach ($wartungPairs as $cid => $fd) {
                            $pairs[] = '(company_id = ? AND frage_datum = ?)';
                            $params[] = $cid;
                            $params[] = $fd;
                        }
                        $stmt = $pdo->prepare("SELECT company_id, frage_datum, gesendet_am FROM company_wartung_mahnung WHERE " . implode(' OR ', $pairs));
                        $stmt->execute($params);
                        $mahnungByCompany = [];
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $mahnungByCompany[(int)$row['company_id'] . '_' . $row['frage_datum']] = $row['gesendet_am'];
                        }
                        foreach ($cards as &$c) {
                            if (isset($c['company_id'], $c['frage_datum']) && isset($mahnungByCompany[(int)$c['company_id'] . '_' . $c['frage_datum']])) {
                                $c['mahnung_gesendet_am'] = $mahnungByCompany[(int)$c['company_id'] . '_' . $c['frage_datum']];
                            }
                        }
                        unset($c);
                    } catch (\Throwable $e) {
                        // Tabelle evtl. noch nicht migriert
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'cards' => $cards]);
        exit;
    }
    
    if ($action === 'wartung_zahlung_response' && $method === 'POST') {
        if ($userRole !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = isset($input['company_id']) ? (int)$input['company_id'] : 0;
        $response = isset($input['response']) ? trim($input['response']) : '';
        $allowed = ['ja', 'nein_inaktiv', 'nein_5tage', 'nein_gesperrt', 'ueberspringen'];
        if (!$companyId || !in_array($response, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
            exit;
        }
        $today = date('Y-m-d');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, name, status, wartung_zahlungsrhythmus AS rhythm, wartung_zahlungstag AS tag FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$company) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            $frage_datum = $today;
            if (!empty($company['rhythm']) && isset($company['tag']) && (int)$company['tag'] > 0) {
                $computed = $computeWartungFrageDatum($company['rhythm'], $company['tag'], $today);
                if ($computed !== null && $computed <= $today) {
                    $frage_datum = $computed;
                }
            }
            $status = $response === 'ja' ? 'paid' : ($response === 'ueberspringen' ? 'skipped' : ($response === 'nein_inaktiv' ? 'inaktiv' : ($response === 'nein_gesperrt' ? 'gesperrt' : 'remind_5')));
            $naechste = ($response === 'nein_5tage') ? date('Y-m-d', strtotime($today . ' +5 days')) : null;
            $stmt = $pdo->prepare("
                INSERT INTO company_wartung_zahlung_frage (company_id, frage_datum, status, naechste_frage_datum)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), naechste_frage_datum = VALUES(naechste_frage_datum)
            ");
            $stmt->execute([$companyId, $frage_datum, $status, $naechste]);
            if ($response === 'nein_inaktiv') {
                $pdo->prepare("UPDATE companies SET status = 'inaktiv', geaendert_von = ?, geaendert_datum = NOW() WHERE id = ?")->execute([$userId, $companyId]);
            } elseif ($response === 'nein_gesperrt') {
                $pdo->prepare("UPDATE companies SET status = 'gesperrt', geaendert_von = ?, geaendert_datum = NOW() WHERE id = ?")->execute([$userId, $companyId]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('wartung_zahlung_response: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Speichern fehlgeschlagen']);
        }
        exit;
    }
    
    // Mahn-E-Mail an Rechnungs-E-Mail senden (Template "Wartungsvertrag Mahnung") – nur Admin
    if ($action === 'wartung_zahlung_mahnung' && $method === 'POST') {
        if ($userRole !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = isset($input['company_id']) ? (int)$input['company_id'] : 0;
        if (!$companyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, name, rechnungs_email, wartung_zahlungsrhythmus AS rhythm, wartung_zahlungstag AS tag FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
            exit;
        }
        $to = trim($company['rechnungs_email'] ?? '');
        if ($to === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Keine Rechnungs-E-Mail bei der Firma hinterlegt']);
            exit;
        }
        $todayYmd = date('Y-m-d');
        $frage_datum = $todayYmd;
        if (!empty($company['rhythm']) && isset($company['tag']) && (int)$company['tag'] > 0) {
            $computed = $computeWartungFrageDatum($company['rhythm'], $company['tag'], $todayYmd);
            if ($computed !== null && $computed <= $todayYmd) {
                $frage_datum = $computed;
            }
        }
        $stmt = $pdo->prepare("SELECT id, subject, body FROM email_templates WHERE name = ? LIMIT 1");
        $stmt->execute(['Wartungsvertrag Mahnung']);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            $defaultSubject = 'Zahlungserinnerung Wartungsvertrag – {{firmenname}}';
            $defaultBody = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">'
                . '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;"><h1 style="color:#1a56db;margin:0;font-size:22px;">Zahlungserinnerung Wartungsvertrag</h1></div>'
                . '<p>Sehr geehrte Damen und Herren,</p><p>für die <strong>{{firmenname}}</strong> erinnern wir an die fällige Zahlung im Rahmen des Wartungsvertrags.</p>'
                . '<p><strong>Fälligkeitsdatum:</strong> {{zahlungstag}}</p><p>Bitte überweisen Sie den offenen Betrag zeitnah. Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>'
                . '<p style="margin-top:24px;">Mit freundlichen Grüßen<br>Ihr Support-Team</p>'
                . '<p style="font-size:12px;color:#666;margin-top:24px;">Datum dieser Erinnerung: {{datum}}</p></body></html>';
            $pdo->prepare("INSERT INTO email_templates (name, subject, body, variables) VALUES ('Wartungsvertrag Mahnung', ?, ?, ?)")
                ->execute([$defaultSubject, $defaultBody, json_encode(['firmenname', 'zahlungstag', 'datum'])]);
            $template = ['id' => $pdo->lastInsertId(), 'subject' => $defaultSubject, 'body' => $defaultBody];
        }
        $todayFormatted = date('d.m.Y');
        $zahlungstagFormatted = date('d.m.Y', strtotime($frage_datum));
        $variables = [
            'firmenname' => $company['name'],
            'zahlungstag' => $zahlungstagFormatted,
            'datum' => $todayFormatted
        ];
        $sent = sendEmailWithTemplate((int)$template['id'], $to, $variables, null, 'Dashboard · Wartungsvertrag Mahnung');
        if ($sent) {
            try {
                $pdo->prepare("INSERT INTO company_wartung_mahnung (company_id, frage_datum, gesendet_am) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE gesendet_am = NOW()")
                    ->execute([$companyId, $frage_datum]);
            } catch (\Throwable $e) {
                // Tabelle evtl. noch nicht migriert
            }
            echo json_encode(['success' => true, 'message' => 'Mahn-E-Mail wurde an ' . $to . ' gesendet']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'E-Mail konnte nicht gesendet werden']);
        }
        exit;
    }
    
    if ($action === 'get_statistics') {
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $compare = isset($_GET['compare']) && $_GET['compare'] == '1';
            
            // Firmenfilter aus Navigation (selected_company_id): wenn gesetzt, alle Statistiken auf diese Firma beschränken
            $statsCompanyId = null;
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null) {
                    $statsCompanyId = (int) $_SESSION['selected_company_id'];
                }
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $statsCompanyId = $userCompanyId;
            }
        
        // Vergleichszeitraum berechnen
        $compareStartDate = null;
        $compareEndDate = null;
        if ($compare) {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $diff = $start->diff($end);
            $compareEnd = clone $start;
            $compareEnd->modify('-1 day');
            $compareStart = clone $compareEnd;
            $compareStart->sub($diff);
            $compareStartDate = $compareStart->format('Y-m-d');
            $compareEndDate = $compareEnd->format('Y-m-d');
        }
        
        $stats = [
            'tickets' => [],
            'devices' => [],
            'customers' => [],
            'companies' => [],
            'compare' => [
                'tickets' => []
            ]
        ];
        
        $singleDay = ($startDate === $endDate);
        $stats['tickets_hourly'] = $singleDay;
        
        // Alle Kalendertage im Zeitraum (für durchgängige X-Achse und „geschlossen am Tag“)
        $begin = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $allDates = [];
        for ($d = clone $begin; $d < $end; $d->modify('+1 day')) {
            $allDates[] = $d->format('Y-m-d');
        }
        
        // Tickets-Statistik: bei einem Tag (Heute/Gestern) stündlich, sonst pro Kalendertag
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            if ($singleDay) {
                $dayStart = $startDate . ' 00:00:00';
                $dayEnd = $startDate . ' 23:59:59';
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT HOUR(t.erstellt_datum) as hour, COUNT(*) as c
                        FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.erstellt_datum BETWEEN ? AND ?
                        AND (t.company_id = ? OR c.company_id = ?)
                        GROUP BY HOUR(t.erstellt_datum)
                    ");
                    $stmt->execute([$dayStart, $dayEnd, $statsCompanyId, $statsCompanyId]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT HOUR(erstellt_datum) as hour, COUNT(*) as c
                        FROM tickets
                        WHERE erstellt_datum BETWEEN ? AND ?
                        GROUP BY HOUR(erstellt_datum)
                    ");
                    $stmt->execute([$dayStart, $dayEnd]);
                }
                $openByHour = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByHour[(int)$row['hour']] = (int) $row['c'];
                }
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT HOUR(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as hour, COUNT(*) as c
                        FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                        AND (t.company_id = ? OR c.company_id = ?)
                        AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                        GROUP BY HOUR(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                    ");
                    $stmt->execute([$statsCompanyId, $statsCompanyId, $dayStart, $dayEnd]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT HOUR(COALESCE(abgeschlossen_datum, geaendert_datum)) as hour, COUNT(*) as c
                        FROM tickets
                        WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                        AND COALESCE(abgeschlossen_datum, geaendert_datum) BETWEEN ? AND ?
                        GROUP BY HOUR(COALESCE(abgeschlossen_datum, geaendert_datum))
                    ");
                    $stmt->execute([$dayStart, $dayEnd]);
                }
                $closedByHour = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByHour[(int)$row['hour']] = (int) $row['c'];
                }
                $stats['tickets'] = [];
                for ($h = 0; $h < 24; $h++) {
                    $stats['tickets'][] = [
                        'date' => $startDate . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00',
                        'open_count' => $openByHour[$h] ?? 0,
                        'closed_count' => $closedByHour[$h] ?? 0
                    ];
                }
            } else {
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT DATE(t.erstellt_datum) as date, COUNT(*) as c
                        FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.erstellt_datum BETWEEN ? AND ?
                        AND (t.company_id = ? OR c.company_id = ?)
                        GROUP BY DATE(t.erstellt_datum)
                    ");
                    $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT DATE(erstellt_datum) as date, COUNT(*) as c
                        FROM tickets
                        WHERE erstellt_datum BETWEEN ? AND ?
                        GROUP BY DATE(erstellt_datum)
                    ");
                    $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                }
                $openByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByDate[$row['date']] = (int) $row['c'];
                }
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as date, COUNT(*) as c
                        FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                        AND (t.company_id = ? OR c.company_id = ?)
                        AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                        GROUP BY DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                    ");
                    $stmt->execute([$statsCompanyId, $statsCompanyId, $startDate, $endDate . ' 23:59:59']);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT DATE(COALESCE(abgeschlossen_datum, geaendert_datum)) as date, COUNT(*) as c
                        FROM tickets
                        WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                        AND COALESCE(abgeschlossen_datum, geaendert_datum) BETWEEN ? AND ?
                        GROUP BY DATE(COALESCE(abgeschlossen_datum, geaendert_datum))
                    ");
                    $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                }
                $closedByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByDate[$row['date']] = (int) $row['c'];
                }
                $stats['tickets'] = [];
                foreach ($allDates as $d) {
                    $stats['tickets'][] = [
                        'date' => $d,
                        'open_count' => $openByDate[$d] ?? 0,
                        'closed_count' => $closedByDate[$d] ?? 0
                    ];
                }
            }
            
            // Vergleichsdaten für Tickets (nur bei Tagesansicht, nicht bei stündlich)
            if ($compare && $compareStartDate && $compareEndDate && !$singleDay) {
                $cmpBegin = new \DateTime($compareStartDate);
                $cmpEnd = new \DateTime($compareEndDate);
                $cmpEnd->modify('+1 day');
                $cmpDates = [];
                for ($d = clone $cmpBegin; $d < $cmpEnd; $d->modify('+1 day')) {
                    $cmpDates[] = $d->format('Y-m-d');
                }
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT DATE(t.erstellt_datum) as date, COUNT(*) as c FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.erstellt_datum BETWEEN ? AND ?
                        AND (t.company_id = ? OR c.company_id = ?)
                        GROUP BY DATE(t.erstellt_datum)
                    ");
                    $stmt->execute([$compareStartDate, $compareEndDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT DATE(erstellt_datum) as date, COUNT(*) as c FROM tickets
                        WHERE erstellt_datum BETWEEN ? AND ?
                        GROUP BY DATE(erstellt_datum)
                    ");
                    $stmt->execute([$compareStartDate, $compareEndDate . ' 23:59:59']);
                }
                $openByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByDate[$row['date']] = (int) $row['c'];
                }
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as date, COUNT(*) as c FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %' AND (t.company_id = ? OR c.company_id = ?)
                        AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                        GROUP BY DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                    ");
                    $stmt->execute([$statsCompanyId, $statsCompanyId, $compareStartDate, $compareEndDate . ' 23:59:59']);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT DATE(COALESCE(abgeschlossen_datum, geaendert_datum)) as date, COUNT(*) as c FROM tickets
                        WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %' AND COALESCE(abgeschlossen_datum, geaendert_datum) BETWEEN ? AND ?
                        GROUP BY DATE(COALESCE(abgeschlossen_datum, geaendert_datum))
                    ");
                    $stmt->execute([$compareStartDate, $compareEndDate . ' 23:59:59']);
                }
                $closedByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByDate[$row['date']] = (int) $row['c'];
                }
                $stats['compare']['tickets'] = [];
                foreach ($cmpDates as $d) {
                    $stats['compare']['tickets'][] = [
                        'date' => $d,
                        'open_count' => $openByDate[$d] ?? 0,
                        'closed_count' => $closedByDate[$d] ?? 0
                    ];
                }
            }
            
            // Geräte-Statistik (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT d.id, d.name, COUNT(t.id) as count
                    FROM devices d
                    LEFT JOIN customers c ON d.customer_id = c.id
                    LEFT JOIN tickets t ON t.device_id = d.id AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE (d.company_id = ? OR c.company_id = ?) AND d.status = 'aktiv'
                    GROUP BY d.id, d.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT d.id, d.name, COUNT(t.id) as count
                    FROM devices d
                    LEFT JOIN tickets t ON t.device_id = d.id AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE d.status = 'aktiv'
                    GROUP BY d.id, d.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $stats['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kunden-Statistik (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, COUNT(t.id) as count
                    FROM customers c
                    LEFT JOIN tickets t ON t.customer_id = c.id AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE c.company_id = ? AND c.status = 'aktiv'
                    GROUP BY c.id, c.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, COUNT(t.id) as count
                    FROM customers c
                    LEFT JOIN tickets t ON t.customer_id = c.id AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE c.status = 'aktiv'
                    GROUP BY c.id, c.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $stats['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stats['customers'] as &$row) { decrypt_customer_row($row); }
            unset($row);
            
            // Firmen-Statistik (mit Firmenfilter: nur diese eine Firma mit ihrer Anzahl)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT comp.id, comp.name, COUNT(t.id) as count
                    FROM companies comp
                    LEFT JOIN tickets t ON (t.company_id = comp.id OR EXISTS (
                        SELECT 1 FROM customers c WHERE c.id = t.customer_id AND c.company_id = comp.id
                    )) AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE comp.id = ? AND comp.status = 'aktiv'
                    GROUP BY comp.id, comp.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT comp.id, comp.name, COUNT(t.id) as count
                    FROM companies comp
                    LEFT JOIN tickets t ON (t.company_id = comp.id OR EXISTS (
                        SELECT 1 FROM customers c WHERE c.id = t.customer_id AND c.company_id = comp.id
                    )) AND t.erstellt_datum BETWEEN ? AND ?
                    WHERE comp.status = 'aktiv'
                    GROUP BY comp.id, comp.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $stats['companies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stats['companies'] as &$row) { $row['name'] = decrypt_from_db($row['name'] ?? null); }
            unset($row);
            
            // Bestellungen-Statistik (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT o.status, COUNT(*) as count
                    FROM orders o
                    LEFT JOIN customers c ON o.customer_id = c.id
                    WHERE (o.company_id = ? OR c.company_id = ?)
                    AND o.erstellt_datum BETWEEN ? AND ?
                    AND o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')
                    GROUP BY o.status
                    ORDER BY CASE o.status WHEN 'Neu' THEN 1 WHEN 'Bestellt' THEN 2 WHEN 'Unterwegs' THEN 3 WHEN 'Beim Kunden' THEN 4 WHEN 'Im Lager' THEN 5 ELSE 6 END
                ");
                $stmt->execute([$statsCompanyId, $statsCompanyId, $startDate, $endDate . ' 23:59:59']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count
                    FROM orders 
                    WHERE erstellt_datum BETWEEN ? AND ?
                    AND status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')
                    GROUP BY status
                    ORDER BY CASE status WHEN 'Neu' THEN 1 WHEN 'Bestellt' THEN 2 WHEN 'Unterwegs' THEN 3 WHEN 'Beim Kunden' THEN 4 WHEN 'Im Lager' THEN 5 ELSE 6 END
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $stats['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ticket-Verteilung (nach Status, mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT t.status, COUNT(*) as count
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?)
                    AND t.erstellt_datum BETWEEN ? AND ?
                    AND t.status IN ('Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen')
                    GROUP BY t.status
                    ORDER BY CASE t.status WHEN 'Neu' THEN 1 WHEN 'In Bearbeitung' THEN 2 WHEN 'Warteschlange' THEN 3 WHEN 'Geplant' THEN 4 WHEN 'Bestellung offen' THEN 5 ELSE 6 END
                ");
                $stmt->execute([$statsCompanyId, $statsCompanyId, $startDate, $endDate . ' 23:59:59']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count
                    FROM tickets 
                    WHERE erstellt_datum BETWEEN ? AND ?
                    AND status IN ('Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen')
                    GROUP BY status
                    ORDER BY CASE status WHEN 'Neu' THEN 1 WHEN 'In Bearbeitung' THEN 2 WHEN 'Warteschlange' THEN 3 WHEN 'Geplant' THEN 4 WHEN 'Bestellung offen' THEN 5 ELSE 6 END
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $ticketDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sicherstellen, dass alle Status vorhanden sind (auch mit count = 0)
            $expectedStatuses = [
                ['status' => 'Neu', 'order' => 1],
                ['status' => 'In Bearbeitung', 'order' => 2],
                ['status' => 'Warteschlange', 'order' => 3],
                ['status' => 'Geplant', 'order' => 4],
                ['status' => 'Bestellung offen', 'order' => 5]
            ];
            
            $statusMap = [];
            foreach ($ticketDistribution as $item) {
                $statusMap[$item['status']] = $item;
            }
            
            $completeDistribution = [];
            foreach ($expectedStatuses as $expected) {
                if (isset($statusMap[$expected['status']])) {
                    $completeDistribution[] = $statusMap[$expected['status']];
                } else {
                    $completeDistribution[] = ['status' => $expected['status'], 'count' => 0];
                }
            }
            
            $stats['ticket_distribution'] = $completeDistribution;
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            if ($singleDay) {
                $dayStart = $startDate . ' 00:00:00';
                $dayEnd = $startDate . ' 23:59:59';
                $stmt = $pdo->prepare("
                    SELECT HOUR(t.erstellt_datum) as hour, COUNT(*) as c
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.erstellt_datum BETWEEN ? AND ?
                    GROUP BY HOUR(t.erstellt_datum)
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $dayStart, $dayEnd]);
                $openByHour = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByHour[(int)$row['hour']] = (int) $row['c'];
                }
                $stmt = $pdo->prepare("
                    SELECT HOUR(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as hour, COUNT(*) as c
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                    GROUP BY HOUR(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $dayStart, $dayEnd]);
                $closedByHour = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByHour[(int)$row['hour']] = (int) $row['c'];
                }
                $stats['tickets'] = [];
                for ($h = 0; $h < 24; $h++) {
                    $stats['tickets'][] = [
                        'date' => $startDate . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00',
                        'open_count' => $openByHour[$h] ?? 0,
                        'closed_count' => $closedByHour[$h] ?? 0
                    ];
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT DATE(t.erstellt_datum) as date, COUNT(*) as c
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.erstellt_datum BETWEEN ? AND ?
                    GROUP BY DATE(t.erstellt_datum)
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $startDate, $endDate . ' 23:59:59']);
                $openByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByDate[$row['date']] = (int) $row['c'];
                }
                $stmt = $pdo->prepare("
                    SELECT DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as date, COUNT(*) as c
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                    GROUP BY DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $startDate, $endDate . ' 23:59:59']);
                $closedByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByDate[$row['date']] = (int) $row['c'];
                }
                $stats['tickets'] = [];
                foreach ($allDates as $d) {
                    $stats['tickets'][] = [
                        'date' => $d,
                        'open_count' => $openByDate[$d] ?? 0,
                        'closed_count' => $closedByDate[$d] ?? 0
                    ];
                }
            }
            
            // Vergleichsdaten für Tickets (nur bei mehreren Tagen)
            if ($compare && $compareStartDate && $compareEndDate && !$singleDay) {
                $cmpBegin = new \DateTime($compareStartDate);
                $cmpEnd = new \DateTime($compareEndDate);
                $cmpEnd->modify('+1 day');
                $cmpDates = [];
                for ($d = clone $cmpBegin; $d < $cmpEnd; $d->modify('+1 day')) {
                    $cmpDates[] = $d->format('Y-m-d');
                }
                $stmt = $pdo->prepare("
                    SELECT DATE(t.erstellt_datum) as date, COUNT(*) as c FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.erstellt_datum BETWEEN ? AND ?
                    GROUP BY DATE(t.erstellt_datum)
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $compareStartDate, $compareEndDate . ' 23:59:59']);
                $openByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByDate[$row['date']] = (int) $row['c'];
                }
                $stmt = $pdo->prepare("
                    SELECT DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum)) as date, COUNT(*) as c FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?) AND t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND COALESCE(t.abgeschlossen_datum, t.geaendert_datum) BETWEEN ? AND ?
                    GROUP BY DATE(COALESCE(t.abgeschlossen_datum, t.geaendert_datum))
                ");
                $stmt->execute([$userCompanyId, $userCompanyId, $compareStartDate, $compareEndDate . ' 23:59:59']);
                $closedByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByDate[$row['date']] = (int) $row['c'];
                }
                $stats['compare']['tickets'] = [];
                foreach ($cmpDates as $d) {
                    $stats['compare']['tickets'][] = [
                        'date' => $d,
                        'open_count' => $openByDate[$d] ?? 0,
                        'closed_count' => $closedByDate[$d] ?? 0
                    ];
                }
            }
            
            // Geräte-Statistik für Firma
            $stmt = $pdo->prepare("
                SELECT d.id, d.name, COUNT(t.id) as count
                FROM devices d
                LEFT JOIN customers c ON d.customer_id = c.id
                LEFT JOIN tickets t ON t.device_id = d.id AND t.erstellt_datum BETWEEN ? AND ?
                WHERE (d.company_id = ? OR c.company_id = ?) AND d.status = 'aktiv'
                GROUP BY d.id, d.name
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute([$startDate, $endDate . ' 23:59:59', $userCompanyId, $userCompanyId]);
            $stats['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kunden-Statistik für Firma
            $stmt = $pdo->prepare("
                SELECT c.id, c.name, COUNT(t.id) as count
                FROM customers c
                LEFT JOIN tickets t ON t.customer_id = c.id AND t.erstellt_datum BETWEEN ? AND ?
                WHERE c.company_id = ? AND c.status = 'aktiv'
                GROUP BY c.id, c.name
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute([$startDate, $endDate . ' 23:59:59', $userCompanyId]);
            $stats['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stats['customers'] as &$row) { decrypt_customer_row($row); }
            unset($row);
            
            // Bestellungen-Statistik (für Firmen-Admin) - nach Status
            $stmt = $pdo->prepare("
                SELECT 
                    o.status,
                    COUNT(*) as count
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE (o.company_id = ? OR c.company_id = ?) 
                AND o.erstellt_datum BETWEEN ? AND ?
                AND o.status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager')
                GROUP BY o.status
                ORDER BY 
                    CASE o.status
                        WHEN 'Neu' THEN 1
                        WHEN 'Bestellt' THEN 2
                        WHEN 'Unterwegs' THEN 3
                        WHEN 'Beim Kunden' THEN 4
                        WHEN 'Im Lager' THEN 5
                        ELSE 6
                    END
            ");
            $stmt->execute([$userCompanyId, $userCompanyId, $startDate, $endDate . ' 23:59:59']);
            $stats['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ticket-Verteilung (nach Status) für Firmen-Admin
            $stmt = $pdo->prepare("
                SELECT 
                    t.status,
                    COUNT(*) as count
                FROM tickets t
                LEFT JOIN customers c ON t.customer_id = c.id
                WHERE (t.company_id = ? OR c.company_id = ?) 
                AND t.erstellt_datum BETWEEN ? AND ?
                AND t.status IN ('Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen')
                GROUP BY t.status
                ORDER BY 
                    CASE t.status
                        WHEN 'Neu' THEN 1
                        WHEN 'In Bearbeitung' THEN 2
                        WHEN 'Warteschlange' THEN 3
                        WHEN 'Geplant' THEN 4
                        WHEN 'Bestellung offen' THEN 5
                        ELSE 6
                    END
            ");
            $stmt->execute([$userCompanyId, $userCompanyId, $startDate, $endDate . ' 23:59:59']);
            $ticketDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sicherstellen, dass alle Status vorhanden sind (auch mit count = 0)
            $expectedStatuses = [
                ['status' => 'Neu', 'order' => 1],
                ['status' => 'In Bearbeitung', 'order' => 2],
                ['status' => 'Warteschlange', 'order' => 3],
                ['status' => 'Geplant', 'order' => 4],
                ['status' => 'Bestellung offen', 'order' => 5]
            ];
            
            $statusMap = [];
            foreach ($ticketDistribution as $item) {
                $statusMap[$item['status']] = $item;
            }
            
            $completeDistribution = [];
            foreach ($expectedStatuses as $expected) {
                if (isset($statusMap[$expected['status']])) {
                    $completeDistribution[] = $statusMap[$expected['status']];
                } else {
                    $completeDistribution[] = ['status' => $expected['status'], 'count' => 0];
                }
            }
            
            $stats['ticket_distribution'] = $completeDistribution;
            $stats['status_distribution'] = $completeDistribution;
        } else {
            // Für normale Benutzer: eigene Tickets – pro Kalendertag, geschlossen = an dem Tag geschlossen
            $stmt = $pdo->prepare("
                SELECT DATE(erstellt_datum) as date, COUNT(*) as c
                FROM tickets
                WHERE (erstellt_von = ? OR zugewiesen_an = ?) AND erstellt_datum BETWEEN ? AND ?
                GROUP BY DATE(erstellt_datum)
            ");
            $stmt->execute([$userId, $userId, $startDate, $endDate . ' 23:59:59']);
            $openByDate = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $openByDate[$row['date']] = (int) $row['c'];
            }
            $stmt = $pdo->prepare("
                SELECT DATE(COALESCE(abgeschlossen_datum, geaendert_datum)) as date, COUNT(*) as c
                FROM tickets
                WHERE (erstellt_von = ? OR zugewiesen_an = ?) AND status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                AND COALESCE(abgeschlossen_datum, geaendert_datum) BETWEEN ? AND ?
                GROUP BY DATE(COALESCE(abgeschlossen_datum, geaendert_datum))
            ");
            $stmt->execute([$userId, $userId, $startDate, $endDate . ' 23:59:59']);
            $closedByDate = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $closedByDate[$row['date']] = (int) $row['c'];
            }
            $stats['tickets'] = [];
            foreach ($allDates as $d) {
                $stats['tickets'][] = [
                    'date' => $d,
                    'open_count' => $openByDate[$d] ?? 0,
                    'closed_count' => $closedByDate[$d] ?? 0
                ];
            }
            
            // Vergleichsdaten für Tickets
            if ($compare && $compareStartDate && $compareEndDate) {
                $cmpBegin = new \DateTime($compareStartDate);
                $cmpEnd = new \DateTime($compareEndDate);
                $cmpEnd->modify('+1 day');
                $cmpDates = [];
                for ($d = clone $cmpBegin; $d < $cmpEnd; $d->modify('+1 day')) {
                    $cmpDates[] = $d->format('Y-m-d');
                }
                $stmt = $pdo->prepare("
                    SELECT DATE(erstellt_datum) as date, COUNT(*) as c FROM tickets
                    WHERE (erstellt_von = ? OR zugewiesen_an = ?) AND erstellt_datum BETWEEN ? AND ?
                    GROUP BY DATE(erstellt_datum)
                ");
                $stmt->execute([$userId, $userId, $compareStartDate, $compareEndDate . ' 23:59:59']);
                $openByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $openByDate[$row['date']] = (int) $row['c'];
                }
                $stmt = $pdo->prepare("
                    SELECT DATE(COALESCE(abgeschlossen_datum, geaendert_datum)) as date, COUNT(*) as c FROM tickets
                    WHERE (erstellt_von = ? OR zugewiesen_an = ?) AND status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                    AND COALESCE(abgeschlossen_datum, geaendert_datum) BETWEEN ? AND ?
                    GROUP BY DATE(COALESCE(abgeschlossen_datum, geaendert_datum))
                ");
                $stmt->execute([$userId, $userId, $compareStartDate, $compareEndDate . ' 23:59:59']);
                $closedByDate = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $closedByDate[$row['date']] = (int) $row['c'];
                }
                $stats['compare']['tickets'] = [];
                foreach ($cmpDates as $d) {
                    $stats['compare']['tickets'][] = [
                        'date' => $d,
                        'open_count' => $openByDate[$d] ?? 0,
                        'closed_count' => $closedByDate[$d] ?? 0
                    ];
                }
            }
            
            // Geräte-Statistik für Kunden (Geräte aus Tickets des Kunden)
            if ($userRole === 'Kunde' && $userCustomerId) {
                $stmt = $pdo->prepare("
                    SELECT d.id, d.name, COUNT(t.id) as count
                    FROM devices d
                    INNER JOIN tickets t ON t.device_id = d.id 
                    WHERE t.customer_id = ? 
                    AND t.erstellt_datum BETWEEN ? AND ?
                    AND d.status = 'aktiv'
                    GROUP BY d.id, d.name
                    ORDER BY count DESC
                    LIMIT 10
                ");
                $stmt->execute([$userCustomerId, $startDate, $endDate . ' 23:59:59']);
                $stats['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Zusätzliche Statistiken nur für Admin/Techniker
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            // 1. Durchschnittliche Zeit von Erstellung bis Schließung (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, t.erstellt_datum, t.abgeschlossen_datum)) as avg_hours,
                        AVG(TIMESTAMPDIFF(DAY, t.erstellt_datum, t.abgeschlossen_datum)) as avg_days,
                        COUNT(*) as closed_count
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND t.abgeschlossen_datum IS NOT NULL
                    AND t.abgeschlossen_datum BETWEEN ? AND ?
                    AND (t.company_id = ? OR c.company_id = ?)
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, erstellt_datum, abgeschlossen_datum)) as avg_hours,
                        AVG(TIMESTAMPDIFF(DAY, erstellt_datum, abgeschlossen_datum)) as avg_days,
                        COUNT(*) as closed_count
                    FROM tickets 
                    WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                    AND abgeschlossen_datum IS NOT NULL
                    AND abgeschlossen_datum BETWEEN ? AND ?
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $avgTime = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['avg_closing_time'] = [
                'hours' => round($avgTime['avg_hours'] ?? 0, 1),
                'days' => round($avgTime['avg_days'] ?? 0, 1),
                'closed_count' => (int)($avgTime['closed_count'] ?? 0)
            ];
            
            // 1b. Durchschnittliche Bearbeitungszeit (bearbeitungszeit_minuten) bei geschlossenen Tickets (mit Firmenfilter)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(t.bearbeitungszeit_minuten) as avg_minutes,
                        COUNT(*) as count_with_time
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND t.abgeschlossen_datum IS NOT NULL
                    AND t.abgeschlossen_datum BETWEEN ? AND ?
                    AND t.bearbeitungszeit_minuten IS NOT NULL
                    AND t.bearbeitungszeit_minuten > 0
                    AND (t.company_id = ? OR c.company_id = ?)
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(bearbeitungszeit_minuten) as avg_minutes,
                        COUNT(*) as count_with_time
                    FROM tickets 
                    WHERE status IN ('Geschlossen', 'Archiv') AND titel NOT LIKE '[Gelöscht] %'
                    AND abgeschlossen_datum IS NOT NULL
                    AND abgeschlossen_datum BETWEEN ? AND ?
                    AND bearbeitungszeit_minuten IS NOT NULL
                    AND bearbeitungszeit_minuten > 0
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $avgBt = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['avg_bearbeitungszeit'] = [
                'minutes' => round($avgBt['avg_minutes'] ?? 0, 1),
                'count_with_time' => (int)($avgBt['count_with_time'] ?? 0)
            ];
            
            // 1c. Durchschnittliche Reaktionszeit: Zeit zwischen Ticket-Erstellung und erster Nachricht von Admin/Techniker (mit Firmenfilter)
            $reaktionSub = "
                SELECT c.ticket_id, MIN(c.erstellt_datum) as erstellt_datum
                FROM ticket_comments c
                INNER JOIN users u ON c.user_id = u.id AND u.rolle IN ('Admin', 'Techniker')
                GROUP BY c.ticket_id
            ";
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, t.erstellt_datum, first_c.erstellt_datum)) as avg_hours,
                        AVG(TIMESTAMPDIFF(DAY, t.erstellt_datum, first_c.erstellt_datum)) as avg_days,
                        COUNT(*) as count_with_reaction
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    INNER JOIN ($reaktionSub) first_c ON first_c.ticket_id = t.id
                    WHERE t.titel NOT LIKE '[Gelöscht] %'
                    AND t.erstellt_datum BETWEEN ? AND ?
                    AND (t.company_id = ? OR c.company_id = ?)
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, t.erstellt_datum, first_c.erstellt_datum)) as avg_hours,
                        AVG(TIMESTAMPDIFF(DAY, t.erstellt_datum, first_c.erstellt_datum)) as avg_days,
                        COUNT(*) as count_with_reaction
                    FROM tickets t
                    INNER JOIN ($reaktionSub) first_c ON first_c.ticket_id = t.id
                    WHERE t.titel NOT LIKE '[Gelöscht] %'
                    AND t.erstellt_datum BETWEEN ? AND ?
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $avgReaktion = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['avg_reaktionszeit'] = [
                'hours' => round($avgReaktion['avg_hours'] ?? 0, 1),
                'days' => round($avgReaktion['avg_days'] ?? 0, 1),
                'count_with_reaction' => (int)($avgReaktion['count_with_reaction'] ?? 0)
            ];
            
            // 2. Welcher Techniker/Admin die meisten Tickets abschließt (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id,
                        u.vorname,
                        u.nachname,
                        COUNT(DISTINCT c.ticket_id) as closed_count
                    FROM ticket_comments c
                    INNER JOIN tickets t ON c.ticket_id = t.id
                    LEFT JOIN customers cust ON t.customer_id = cust.id
                    INNER JOIN users u ON c.user_id = u.id
                    WHERE c.nachrichtentyp = 'loesung'
                    AND t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND t.abgeschlossen_datum IS NOT NULL
                    AND t.abgeschlossen_datum BETWEEN ? AND ?
                    AND (t.company_id = ? OR cust.company_id = ?)
                    AND u.rolle IN ('Admin', 'Techniker')
                    GROUP BY u.id, u.vorname, u.nachname
                    ORDER BY closed_count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id,
                        u.vorname,
                        u.nachname,
                        COUNT(DISTINCT c.ticket_id) as closed_count
                    FROM ticket_comments c
                    INNER JOIN tickets t ON c.ticket_id = t.id
                    INNER JOIN users u ON c.user_id = u.id
                    WHERE c.nachrichtentyp = 'loesung'
                    AND t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                    AND t.abgeschlossen_datum IS NOT NULL
                    AND t.abgeschlossen_datum BETWEEN ? AND ?
                    AND u.rolle IN ('Admin', 'Techniker')
                    GROUP BY u.id, u.vorname, u.nachname
                    ORDER BY closed_count DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $stats['top_closers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fallback: Wenn keine Lösungen gefunden, verwende zugewiesen_an
            if (empty($stats['top_closers'])) {
                if ($statsCompanyId) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.id,
                            u.vorname,
                            u.nachname,
                            COUNT(t.id) as closed_count
                        FROM tickets t
                        LEFT JOIN customers c ON t.customer_id = c.id
                        INNER JOIN users u ON t.zugewiesen_an = u.id
                        WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                        AND t.abgeschlossen_datum IS NOT NULL
                        AND t.abgeschlossen_datum BETWEEN ? AND ?
                        AND (t.company_id = ? OR c.company_id = ?)
                        AND u.rolle IN ('Admin', 'Techniker')
                        GROUP BY u.id, u.vorname, u.nachname
                        ORDER BY closed_count DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$startDate, $endDate . ' 23:59:59', $statsCompanyId, $statsCompanyId]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.id,
                            u.vorname,
                            u.nachname,
                            COUNT(t.id) as closed_count
                        FROM tickets t
                        INNER JOIN users u ON t.zugewiesen_an = u.id
                        WHERE t.status IN ('Geschlossen', 'Archiv') AND t.titel NOT LIKE '[Gelöscht] %'
                        AND t.abgeschlossen_datum IS NOT NULL
                        AND t.abgeschlossen_datum BETWEEN ? AND ?
                        AND u.rolle IN ('Admin', 'Techniker')
                        GROUP BY u.id, u.vorname, u.nachname
                        ORDER BY closed_count DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$startDate, $endDate . ' 23:59:59']);
                }
                $stats['top_closers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // 3. Status-Statistik: Wie viele Tickets welchen Status haben (mit Firmenfilter wenn aktiv)
            if ($statsCompanyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        t.status,
                        COUNT(*) as count
                    FROM tickets t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE (t.company_id = ? OR c.company_id = ?)
                    AND t.erstellt_datum BETWEEN ? AND ?
                    AND t.status IN ('Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen')
                    GROUP BY t.status
                    ORDER BY count DESC
                ");
                $stmt->execute([$statsCompanyId, $statsCompanyId, $startDate, $endDate . ' 23:59:59']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM tickets 
                    WHERE erstellt_datum BETWEEN ? AND ?
                    AND status IN ('Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen')
                    GROUP BY status
                    ORDER BY count DESC
                ");
                $stmt->execute([$startDate, $endDate . ' 23:59:59']);
            }
            $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sicherstellen, dass alle Status vorhanden sind (auch mit count = 0)
            $expectedStatuses = ['Neu', 'In Bearbeitung', 'Warteschlange', 'Geplant', 'Bestellung offen'];
            
            $statusMap = [];
            foreach ($statusDistribution as $item) {
                $statusMap[$item['status']] = $item;
            }
            
            $completeStatusDistribution = [];
            foreach ($expectedStatuses as $status) {
                if (isset($statusMap[$status])) {
                    $completeStatusDistribution[] = $statusMap[$status];
                } else {
                    $completeStatusDistribution[] = ['status' => $status, 'count' => 0];
                }
            }
            
            // Nach count DESC sortieren (aber alle Status behalten)
            usort($completeStatusDistribution, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            
            $stats['status_distribution'] = $completeStatusDistribution;
        }
        
        echo json_encode(['success' => true, 'statistics' => $stats, 'compare_period' => $compare ? [
            'start' => $compareStartDate,
            'end' => $compareEndDate
        ] : null]);
        exit;
        } catch (Exception $e) {
            error_log("Dashboard API get_statistics Fehler: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Laden der Statistiken: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'get_timeline') {
        // Nur für Admin/Techniker
        if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }
        
        try {
            $items = [];
            
            // Fällige Todos
            $stmt = $pdo->prepare("
                SELECT id, titel, faellig_am, status, prioritaet
                FROM todos
                WHERE status != 'erledigt' AND faellig_am IS NOT NULL
                ORDER BY faellig_am ASC
                LIMIT 20
            ");
            $stmt->execute();
            $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($todos as $todo) {
                $items[] = [
                    'id' => 'todo_' . $todo['id'],
                    'type' => 'todo',
                    'title' => $todo['titel'],
                    'date' => $todo['faellig_am'],
                    'status' => $todo['status'],
                    'priority' => $todo['prioritaet'],
                    'link' => BASE_URL . 'todos/?id=' . $todo['id']
                ];
            }
            
            // Geplante Termine (aus Tickets) - nur mit Status "Neu"
            $stmt = $pdo->prepare("
                SELECT id, titel, geplant_datum, status, ticket_nummer
                FROM tickets
                WHERE geplant_datum IS NOT NULL AND status = 'Neu'
                ORDER BY geplant_datum ASC
                LIMIT 20
            ");
            $stmt->execute();
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tickets as $ticket) {
                $items[] = [
                    'id' => 'ticket_' . $ticket['id'],
                    'type' => 'ticket',
                    'title' => $ticket['titel'],
                    'date' => $ticket['geplant_datum'],
                    'status' => $ticket['status'],
                    'ticket_number' => $ticket['ticket_nummer'] ?? '',
                    'link' => BASE_URL . 'tickets/view.php?id=' . $ticket['id']
                ];
            }
            
            // Nach Datum sortieren
            usort($items, function($a, $b) {
                $dateA = strtotime($a['date'] ?? '1970-01-01');
                $dateB = strtotime($b['date'] ?? '1970-01-01');
                return $dateA - $dateB;
            });
            
            echo json_encode(['success' => true, 'items' => array_slice($items, 0, 20)]);
            exit;
        } catch (Exception $e) {
            error_log("Dashboard API get_timeline Fehler: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fehler beim Laden des Zeitstrahls: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'get_notifications') {
        // Wichtigste Benachrichtigungen
        require_once dirname(__DIR__, 2) . '/assets/notifications.php';
        
        $limit = 5;
        $notifications = [];
        
        if (function_exists('getRecentNotifications')) {
            $notifications = getRecentNotifications($userId, $limit);
        } else {
            // Fallback: Direkt aus DB
            $stmt = $pdo->prepare("
                SELECT id, titel, nachricht, typ, erstellt_datum, ist_gelesen as gelesen
                FROM notifications
                WHERE user_id = ?
                ORDER BY erstellt_datum DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
    
} catch (PDOException $e) {
    error_log("Dashboard API Fehler: " . $e->getMessage());
    error_log("Dashboard API Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Dashboard API Allgemeiner Fehler: " . $e->getMessage());
    error_log("Dashboard API Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler: ' . $e->getMessage()]);
}
