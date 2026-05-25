<?php
/**
 * Globale Suche – durchsucht das gesamte System (Tickets, Aufgaben, Geräte, Bestellungen, Firmen, Kunden, Wissensdatenbank, Projekte, Lager/Verbrauchsmaterialien; Benutzer nur Admin)
 */
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/customers/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/assets/ticket_search_plaintext_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$q = preg_replace('/[\x{00A0}\x{2000}-\x{200D}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $q);
$q = preg_replace('/\s+/u', ' ', $q); // Mehrere Leerzeichen zu einem zusammenfassen
$limit = min(max((int) ($_GET['limit'] ?? 8), 1), 20);
$searchScopeRaw = isset($_GET['search_scope']) ? (string) $_GET['search_scope'] : '';
$allowedScopeKeys = ['ticket', 'aufgabe', 'geraet', 'bestellung', 'firma', 'kunde', 'artikel', 'projekt', 'inventar', 'benutzer'];
$searchScope = $searchScopeRaw !== '' ? array_intersect(array_map('trim', explode(',', $searchScopeRaw)), $allowedScopeKeys) : [];

if ($q === '') {
    echo json_encode(['success' => true, 'results' => [], 'suggestions' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    $userRoleRaw = trim((string) ($user['rolle'] ?? ''));
    $userRoleKey = mb_strtolower($userRoleRaw, 'UTF-8');
    $roleAliases = [
        'admin' => 'Admin',
        'techniker' => 'Techniker',
        'firmen-admin' => 'Firmen-Admin',
        'firmen admin' => 'Firmen-Admin',
        'firmen_user' => 'Firmen-User',
        'firmen-user' => 'Firmen-User',
        'firmen user' => 'Firmen-User',
        'kunde' => 'Kunde',
    ];
    $userRole = $roleAliases[$userRoleKey] ?? $userRoleRaw;
    $userCompanyId = (int) ($user['company_id'] ?? 0);
    $userCustomerId = (int) ($user['customer_id'] ?? 0);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$results = [];
$baseUrl = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') . '/' : '/';
/**
 * Normalisiert Text für robuste Suchvergleiche (Whitespace/Zero-Width/Case).
 */
$normalizeSearchText = static function (string $text): string {
    $t = preg_replace('/[\x{00A0}\x{2000}-\x{200D}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $text) ?? $text;
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return mb_strtolower(trim($t), 'UTF-8');
};
/**
 * Kompaktform ohne Trennzeichen (für "kern-1370" vs "kern 1370").
 */
$compactSearchText = static function (string $text) use ($normalizeSearchText): string {
    $n = $normalizeSearchText($text);
    return preg_replace('/[\p{Z}\s\p{P}\p{S}]+/u', '', $n) ?? $n;
};
/**
 * Baut bei 0 Treffern sinnvolle Fuzzy-Vorschläge aus realen Datensätzen.
 */
$buildNoResultSuggestions = static function () use ($pdo, $q, $compactSearchText, $userRole, $userCompanyId, $userCustomerId, $userId, $searchScope): array {
    $suggestions = [];
    $qCompact = $compactSearchText($q);
    if ($qCompact === '' || mb_strlen($qCompact, 'UTF-8') < 4) {
        return $suggestions;
    }
    $shouldSuggestType = static function (string $type) use ($searchScope): bool {
        if ($type === 'ticket' || $type === 'geraet') {
            return true;
        }
        return empty($searchScope) || in_array($type, $searchScope, true);
    };
    $addCandidatesFromRows = static function (array &$target, array $rows, int $priority) use ($compactSearchText, $qCompact): void {
        $qLen = max(1, mb_strlen($qCompact, 'UTF-8'));
        // Vorschläge strenger halten, damit weniger irrelevante "Ähnlichkeiten" auftauchen.
        $maxDist = max(1, (int) floor($qLen * 0.24));
        foreach ($rows as $plainRaw) {
            $plain = trim((string) $plainRaw);
            if ($plain === '') {
                continue;
            }
            $nameCompact = $compactSearchText($plain);
            if ($nameCompact === '') {
                continue;
            }
            $distance = levenshtein($qCompact, $nameCompact);
            $contains = mb_strpos($nameCompact, $qCompact, 0, 'UTF-8') !== false;
            $starts = mb_strpos($nameCompact, $qCompact, 0, 'UTF-8') === 0;
            if (!$contains && $distance > $maxDist) {
                continue;
            }
            $score = $priority * 100 + $distance + abs(mb_strlen($nameCompact, 'UTF-8') - $qLen);
            if ($contains) {
                $score -= 25;
            }
            if ($starts) {
                $score -= 10;
            }
            $target[] = [
                'title' => $plain,
                'score' => $score,
                'distance' => $distance,
                'len_delta' => abs(mb_strlen($nameCompact, 'UTF-8') - $qLen),
            ];
        }
    };

    try {
        $candidates = [];

        // Firmen
        if ($shouldSuggestType('firma') && in_array($userRole, ['Admin', 'Techniker', 'Firmen-Admin'], true)) {
            $params = [];
            $where = [];
            if ($userRole === 'Firmen-Admin') {
                if ($userCompanyId <= 0) {
                    return $suggestions;
                }
                $where[] = 'c.id = :sid';
                $params[':sid'] = (int) $userCompanyId;
            }
            $wc = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
            $sql = "SELECT c.name FROM companies c $wc ORDER BY c.id DESC LIMIT 400";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = decrypt_from_db($row['name'] ?? null);
            }
            $addCandidatesFromRows($candidates, $rows, 1);
        }

        // Kunden
        if ($shouldSuggestType('kunde') && in_array($userRole, ['Admin', 'Techniker', 'Firmen-Admin'], true)) {
            $params = [];
            $where = [];
            if ($userRole === 'Firmen-Admin') {
                if ($userCompanyId <= 0) {
                    return $suggestions;
                }
                $where[] = 'cust.company_id = :cid';
                $params[':cid'] = (int) $userCompanyId;
            }
            $wc = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
            $sql = "SELECT cust.name FROM customers cust $wc ORDER BY cust.id DESC LIMIT 400";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = decrypt_from_db($row['name'] ?? null);
            }
            $addCandidatesFromRows($candidates, $rows, 2);
        }

        // Geräte
        if ($shouldSuggestType('geraet')) {
            $params = [];
            $where = [];
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // alle Geräte
            } elseif (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $userCompanyId) {
                $where[] = "(d.company_id = :dc1 OR d.customer_id IN (SELECT id FROM customers WHERE company_id = :dc2 OR company_id IS NULL))";
                $params[':dc1'] = (int) $userCompanyId;
                $params[':dc2'] = (int) $userCompanyId;
            } elseif ($userRole === 'Kunde' && $userCustomerId) {
                $where[] = "d.customer_id = :du_customer_id";
                $params[':du_customer_id'] = (int) $userCustomerId;
            } else {
                $where[] = '1=0';
            }
            $wc = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
            $sql = "SELECT d.name FROM devices d $wc ORDER BY d.geaendert_datum DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
            $addCandidatesFromRows($candidates, $rows, 3);
        }

        // Tickets (Titel + Ticketnummer)
        if ($shouldSuggestType('ticket')) {
            $params = [];
            $where = ["t.titel NOT LIKE '[Gelöscht] %'"];
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // alle Tickets
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                $where[] = "(t.erstellt_von = :tu1 OR t.zugewiesen_an = :tu2 OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :tu3) OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :tu4))";
                $params[':tu1'] = (int) $userId;
                $params[':tu2'] = (int) $userId;
                $params[':tu3'] = (int) $userId;
                $params[':tu4'] = (int) $userCompanyId;
            } elseif ($userRole === 'Firmen-User' && $userCompanyId) {
                $where[] = "(t.erstellt_von = :tu1 OR t.zugewiesen_an = :tu2 OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :tu3) OR t.company_id = :tu4 OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :tu5))";
                $params[':tu1'] = (int) $userId;
                $params[':tu2'] = (int) $userId;
                $params[':tu3'] = (int) $userId;
                $params[':tu4'] = (int) $userCompanyId;
                $params[':tu5'] = (int) $userCompanyId;
            } elseif ($userRole === 'Firmen-User' || $userRole === 'Kunde') {
                $where[] = "(t.erstellt_von = :tu1 OR t.zugewiesen_an = :tu2 OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :tu3))";
                $params[':tu1'] = (int) $userId;
                $params[':tu2'] = (int) $userId;
                $params[':tu3'] = (int) $userId;
            } else {
                $where[] = '1=0';
            }
            $wc = implode(' AND ', $where);
            $sql = "SELECT t.titel, t.ticket_nummer FROM tickets t WHERE $wc ORDER BY t.geaendert_datum DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $title = trim((string) ($row['titel'] ?? ''));
                $num = trim((string) ($row['ticket_nummer'] ?? ''));
                if ($title !== '') {
                    $rows[] = $title;
                }
                if ($num !== '') {
                    $rows[] = $num;
                }
            }
            $addCandidatesFromRows($candidates, $rows, 4);
        }

        // Wissensdatenbank
        if ($shouldSuggestType('artikel') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
            $sql = "SELECT p.title FROM kb_pages p WHERE p.deleted_at IS NULL ORDER BY p.updated_at DESC LIMIT 400";
            $stmt = $pdo->query($sql);
            $rows = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'title');
            $addCandidatesFromRows($candidates, $rows, 5);
        }

        // Lager/Verbrauchsmaterialien
        if ($shouldSuggestType('inventar') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
            $sql = "SELECT c.bezeichnung, c.artikelnummer FROM consumables c ORDER BY c.id DESC LIMIT 500";
            $stmt = $pdo->query($sql);
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $bez = trim((string) ($row['bezeichnung'] ?? ''));
                $art = trim((string) ($row['artikelnummer'] ?? ''));
                if ($bez !== '') {
                    $rows[] = $bez;
                }
                if ($art !== '') {
                    $rows[] = $art;
                }
            }
            $addCandidatesFromRows($candidates, $rows, 6);
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $a['score'] <=> $b['score'];
            }
            if ($a['distance'] !== $b['distance']) return $a['distance'] <=> $b['distance'];
            if ($a['len_delta'] !== $b['len_delta']) {
                return $a['len_delta'] <=> $b['len_delta'];
            }
            return strcasecmp($a['title'], $b['title']);
        });

        $seen = [];
        foreach ($candidates as $c) {
            $key = mb_strtolower((string) ($c['title'] ?? ''), 'UTF-8');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $suggestions[] = (string) $c['title'];
            if (count($suggestions) >= 8) {
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('search.php suggestions failed: ' . $e->getMessage());
    }

    return $suggestions;
};
$searchTermsRaw = preg_split('/\s+/u', $q) ?: [];
$searchTermsAll = [];
foreach ($searchTermsRaw as $termRaw) {
    $term = trim((string) $termRaw);
    if ($term === '') {
        continue;
    }
    // Führende/abschließende Satzzeichen und Anführungszeichen entfernen (z.B. "1370 kern")
    $term = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $term) ?? '';
    $term = trim($term);
    if ($term === '') {
        continue;
    }
    $searchTermsAll[] = $term;
}
$searchTermsAll = array_values(array_unique($searchTermsAll));
// 1-Zeichen-Tokens beim Tippen ignorieren ("foo n"), sonst verschwinden Treffer sofort.
$searchTerms = array_values(array_filter($searchTermsAll, static function (string $t): bool {
    return mb_strlen($t, 'UTF-8') >= 2;
}));
// Falls nur kurze Tokens vorhanden sind, auf die originalen Tokens zurückfallen.
if (empty($searchTerms) && !empty($searchTermsAll)) {
    $searchTerms = $searchTermsAll;
}
$normalizedQuery = !empty($searchTerms) ? implode(' ', $searchTerms) : $q;
$searchLike = '%' . str_replace(' ', '%', $normalizedQuery) . '%';

/** Relevanz-Stufe für Tickets in der globalen Suche (niedrig = höhere Priorität). */
$globalSearchTicketTier = static function (array $row, string $needle): int {
    $nq = mb_strtolower(trim($needle));
    if ($nq === '') {
        return 4;
    }
    $num = mb_strtolower(trim((string) ($row['ticket_nummer'] ?? '')));
    $tit = mb_strtolower(trim((string) ($row['title'] ?? '')));
    if ($num !== '' && $num === $nq) {
        return 0;
    }
    if ($tit !== '' && $tit === $nq) {
        return 1;
    }
    if ($num !== '' && mb_strpos($num, $nq) !== false) {
        return 2;
    }
    if ($tit !== '' && mb_strpos($tit, $nq) !== false) {
        return 2;
    }
    $sub = mb_strtolower(trim((string) ($row['ticket_subtitle_line1'] ?? '') . ' ' . (string) ($row['ticket_subtitle_line2'] ?? '') . ' ' . (string) ($row['ticket_anforderer'] ?? '')));
    if ($sub !== '' && mb_strpos($sub, $nq) !== false) {
        return 3;
    }
    $tst = mb_strtolower(trim((string) ($row['ticket_status'] ?? '')));
    if ($tst !== '' && mb_strpos($tst, $nq) !== false) {
        return 3;
    }
    $besch = mb_strtolower(trim(strip_tags((string) ($row['_beschreibung_plain'] ?? ''))));
    if ($besch !== '' && mb_strpos($besch, $nq) !== false) {
        return 3;
    }

    return 4;
};

// Leerzeichen im Wort tolerieren: "ha llo" soll "hallo" finden
$qNoSpaces = str_replace(' ', '', $normalizedQuery);
$searchLikeNoSpaces = (mb_strlen($qNoSpaces) >= 2 && $qNoSpaces !== $normalizedQuery) ? ('%' . mb_strtolower($qNoSpaces) . '%') : null;

// Tippfehlertoleranz: reduziert (nur 1-2 Fehler, weniger Varianten), um Rauschen zu verringern.
// Fuzzy aus Version OHNE Leerzeichen, Kleinbuchstaben, Abgleich mit LOWER(REPLACE(...))
$fuzzyLikes = [];
$fuzzyLikes2 = [];
$fuzzyLikes3 = [];
$qForFuzzy = mb_strtolower($qNoSpaces);
if (mb_strlen($qForFuzzy) >= 4 && strpos($qForFuzzy, '%') === false && strpos($qForFuzzy, '_') === false) {
    $len = mb_strlen($qForFuzzy);
    // 1 Fehler: weniger Positionen als zuvor
    $max1 = min(12, $len);
    for ($i = 0; $i < $max1; $i++) {
        $before = mb_substr($qForFuzzy, 0, $i);
        $after = mb_substr($qForFuzzy, $i + 1, $len - $i - 1);
        $fuzzyLikes[] = '%' . $before . '_' . $after . '%';
    }
    // 2 Fehler: stark begrenzt und erst ab längeren Suchbegriffen.
    $numPairs = 0;
    if ($len >= 6) {
        $numPairs = (int) min(pow(2, min(3, max(0, (int)(($len - 2) / 2)))), $len * ($len - 1) / 2, 12);
    }
    $count = 0;
    for ($i = 0; $i < $len - 1 && $count < $numPairs; $i++) {
        for ($j = $i + 1; $j < $len && $count < $numPairs; $j++, $count++) {
            $before = mb_substr($qForFuzzy, 0, $i);
            $mid = mb_substr($qForFuzzy, $i + 1, $j - $i - 1);
            $after = mb_substr($qForFuzzy, $j + 1, $len - $j - 1);
            $fuzzyLikes2[] = '%' . $before . '_' . $mid . '_' . $after . '%';
        }
    }
    // 3-Fehler-Fuzzy deaktiviert: verursacht zu viele unpräzise Treffer.
}

// Helper function to check if a type should be searched
// Tickets/Geräte immer durchsuchen: vermeidet leere Treffer bei veraltetem/zu engem global_search_scope
$shouldSearchType = function ($type) use ($searchScope) {
    if ($type === 'ticket' || $type === 'geraet') {
        return true;
    }

    return empty($searchScope) || in_array($type, $searchScope, true);
};

// === TICKETS / Tickets ===
if ($shouldSearchType('ticket')) {
$ticketWhere = ["t.titel NOT LIKE '[Gelöscht] %'", "(t.company_id IS NULL OR c.status = 'aktiv')"];
$ticketParams = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Admin/Techniker: alle Tickets
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    $ticketWhere[] = "(t.erstellt_von = :user_id_t OR t.zugewiesen_an = :user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_id) OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :user_company_id))";
    $ticketParams[':user_id_t'] = $userId;
    $ticketParams[':user_id_z'] = $userId;
    $ticketParams[':observer_id'] = $userId;
    $ticketParams[':user_company_id'] = $userCompanyId;
} elseif ($userRole === 'Firmen-User' && $userCompanyId) {
    // Firmen-User: eigene Tickets + Firmenkunden (wie in Ticket-Berechtigungen der App)
    $ticketWhere[] = "(t.erstellt_von = :user_id_t OR t.zugewiesen_an = :user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_id) OR t.company_id = :user_company_id OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :user_company_id2))";
    $ticketParams[':user_id_t'] = $userId;
    $ticketParams[':user_id_z'] = $userId;
    $ticketParams[':observer_id'] = $userId;
    $ticketParams[':user_company_id'] = $userCompanyId;
    $ticketParams[':user_company_id2'] = $userCompanyId;
} elseif ($userRole === 'Firmen-User') {
    $ticketWhere[] = "(t.erstellt_von = :user_id_t OR t.zugewiesen_an = :user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_id))";
    $ticketParams[':user_id_t'] = $userId;
    $ticketParams[':user_id_z'] = $userId;
    $ticketParams[':observer_id'] = $userId;
} elseif ($userRole === 'Kunde') {
    $ticketWhere[] = "(t.erstellt_von = :user_id_t OR t.zugewiesen_an = :user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :observer_id))";
    $ticketParams[':user_id_t'] = $userId;
    $ticketParams[':user_id_z'] = $userId;
    $ticketParams[':observer_id'] = $userId;
} else {
    $ticketWhere[] = "1=0";
}

// Erweiterte Suche nach allen Feldern wie in tickets/api/tickets.php
$searchParts = [];
$searchParamIndex = 0;
$nextParam = function () use (&$searchParamIndex) {
    return ':ticket_search_' . (++$searchParamIndex);
};
foreach ($fuzzyLikes as $i => $val) $ticketParams[':ticket_f' . $i] = $val;
foreach ($fuzzyLikes2 as $i => $val) $ticketParams[':ticket_g' . $i] = $val;
foreach ($fuzzyLikes3 as $i => $val) $ticketParams[':ticket_h' . $i] = $val;
if ($searchLikeNoSpaces !== null) $ticketParams[':ticket_nospaces'] = $searchLikeNoSpaces;

// Ticketnummer
$p = $nextParam();
$searchParts[] = "t.ticket_nummer LIKE $p";
$ticketParams[$p] = $searchLike;

// Titel (mit Tippfehlertoleranz: exakt, ohne Leerzeichen, 1 Fehler, 2 Fehler)
// LOWER(REPLACE(…,' ','')) damit Großschreibung egal ist und "Seiten die in Index" zu "seitendieinindex" wird
$p = $nextParam();
$titelOr = ['t.titel LIKE ' . $p];
if ($searchLikeNoSpaces !== null) $titelOr[] = "LOWER(REPLACE(t.titel, ' ', '')) LIKE :ticket_nospaces";
for ($i = 0; $i < count($fuzzyLikes); $i++) $titelOr[] = "LOWER(REPLACE(t.titel, ' ', '')) LIKE :ticket_f" . $i;
for ($i = 0; $i < count($fuzzyLikes2); $i++) $titelOr[] = "LOWER(REPLACE(t.titel, ' ', '')) LIKE :ticket_g" . $i;
for ($i = 0; $i < count($fuzzyLikes3); $i++) $titelOr[] = "LOWER(REPLACE(t.titel, ' ', '')) LIKE :ticket_h" . $i;
$searchParts[] = count($titelOr) > 1 ? '(' . implode(' OR ', $titelOr) . ')' : $titelOr[0];
$ticketParams[$p] = $searchLike;

// Beschreibung
$p = $nextParam();
$searchParts[] = "t.beschreibung LIKE $p";
$ticketParams[$p] = $searchLike;

// Firma / Kunde: verschlüsselte Spalten — Klartext-Match über ID-Listen (siehe ticket_search_plaintext_helpers.php)
$globalTicketCompanyIds = ticket_search_company_ids_matching_plaintext($pdo, $normalizedQuery);
if (!empty($globalTicketCompanyIds)) {
    $searchParts[] = 't.company_id IN (' . implode(',', array_map('intval', $globalTicketCompanyIds)) . ')';
} else {
    $searchParts[] = '1=0';
}
$globalTicketCustomerIds = ticket_search_customer_ids_matching_plaintext($pdo, $normalizedQuery);
if (!empty($globalTicketCustomerIds)) {
    $searchParts[] = 't.customer_id IN (' . implode(',', array_map('intval', $globalTicketCustomerIds)) . ')';
} else {
    $searchParts[] = '1=0';
}

// Anforderer
$p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
$searchParts[] = "(u_erstellt.vorname LIKE $p1 OR u_erstellt.nachname LIKE $p2 OR CONCAT(IFNULL(u_erstellt.vorname,''), ' ', IFNULL(u_erstellt.nachname,'')) LIKE $p3)";
$ticketParams[$p1] = $ticketParams[$p2] = $ticketParams[$p3] = $searchLike;

// Bearbeiter
$p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
$searchParts[] = "(u_zugewiesen.vorname LIKE $p1 OR u_zugewiesen.nachname LIKE $p2 OR CONCAT(IFNULL(u_zugewiesen.vorname,''), ' ', IFNULL(u_zugewiesen.nachname,'')) LIKE $p3)";
$ticketParams[$p1] = $ticketParams[$p2] = $ticketParams[$p3] = $searchLike;

// Beobachter
$p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam();
$searchParts[] = "EXISTS (SELECT 1 FROM ticket_observers to_b JOIN users u_b ON to_b.user_id = u_b.id WHERE to_b.ticket_id = t.id AND (u_b.vorname LIKE $p1 OR u_b.nachname LIKE $p2 OR CONCAT(IFNULL(u_b.vorname,''), ' ', IFNULL(u_b.nachname,'')) LIKE $p3))";
$ticketParams[$p1] = $ticketParams[$p2] = $ticketParams[$p3] = $searchLike;

// Gerät
$p1 = $nextParam(); $p2 = $nextParam(); $p3 = $nextParam(); $p4 = $nextParam();
$p5 = $nextParam(); $p6 = $nextParam(); $p7 = $nextParam(); $p8 = $nextParam();
$searchParts[] = "(d.name LIKE $p1 OR d.typ LIKE $p2 OR d.hersteller LIKE $p3 OR d.modell LIKE $p4 OR d.seriennummer LIKE $p5 OR d.mac_adresse LIKE $p6 OR d.ip_adresse LIKE $p7 OR d.betriebssystem LIKE $p8)";
$ticketParams[$p1] = $ticketParams[$p2] = $ticketParams[$p3] = $ticketParams[$p4] = $ticketParams[$p5] = $ticketParams[$p6] = $ticketParams[$p7] = $ticketParams[$p8] = $searchLike;

// Gerätestandort
$p = $nextParam();
$searchParts[] = "d.beschreibung LIKE $p";
$ticketParams[$p] = $searchLike;

// Nachrichten (Kommentare)
$p = $nextParam();
$searchParts[] = "EXISTS (SELECT 1 FROM ticket_comments tc WHERE tc.ticket_id = t.id AND tc.kommentar LIKE $p)";
$ticketParams[$p] = $searchLike;

// Anhänge (Dateinamen)
$p = $nextParam();
$searchParts[] = "EXISTS (SELECT 1 FROM ticket_attachments ta WHERE ta.ticket_id = t.id AND ta.dateiname LIKE $p)";
$ticketParams[$p] = $searchLike;

// Status
$p = $nextParam();
$searchParts[] = "t.status LIKE $p";
$ticketParams[$p] = $searchLike;

if (!empty($searchParts) && count($searchTerms) <= 1) {
    $ticketWhere[] = "(" . implode(" OR ", $searchParts) . ")";
}
if (count($searchTerms) > 1) {
    $ticketTermGroups = [];
    foreach ($searchTerms as $term) {
        $tp = '%' . mb_strtolower((string) $term, 'UTF-8') . '%';
        $addTermParam = static function () use (&$ticketParams, &$searchParamIndex, $tp): string {
            $name = ':ticket_term_' . (++$searchParamIndex);
            $ticketParams[$name] = $tp;
            return $name;
        };
        $pTicketNummer = $addTermParam();
        $pTitel = $addTermParam();
        $pBeschreibung = $addTermParam();
        $pStatus = $addTermParam();
        $pAnfVor = $addTermParam();
        $pAnfNach = $addTermParam();
        $pAnfFull = $addTermParam();
        $pBearbVor = $addTermParam();
        $pBearbNach = $addTermParam();
        $pBearbFull = $addTermParam();
        $pDeviceName = $addTermParam();
        $pDeviceTyp = $addTermParam();
        $pDeviceHer = $addTermParam();
        $pDeviceModell = $addTermParam();
        $pDeviceSn = $addTermParam();
        $pDeviceMac = $addTermParam();
        $pDeviceIp = $addTermParam();
        $pDeviceOs = $addTermParam();
        $pDeviceBeschreibung = $addTermParam();
        $pComment = $addTermParam();
        $pAttachment = $addTermParam();
        $termCompanyIds = ticket_search_company_ids_matching_plaintext($pdo, (string) $term);
        $termCustomerIds = ticket_search_customer_ids_matching_plaintext($pdo, (string) $term);
        $termCompanySql = !empty($termCompanyIds)
            ? 't.company_id IN (' . implode(',', array_map('intval', $termCompanyIds)) . ')'
            : '1=0';
        $termCustomerSql = !empty($termCustomerIds)
            ? 't.customer_id IN (' . implode(',', array_map('intval', $termCustomerIds)) . ')'
            : '1=0';

        $ticketTermGroups[] = "(
            LOWER(IFNULL(t.ticket_nummer, '')) LIKE $pTicketNummer
            OR LOWER(IFNULL(t.titel, '')) LIKE $pTitel
            OR LOWER(IFNULL(t.beschreibung, '')) LIKE $pBeschreibung
            OR LOWER(IFNULL(t.status, '')) LIKE $pStatus
            OR LOWER(IFNULL(u_erstellt.vorname, '')) LIKE $pAnfVor
            OR LOWER(IFNULL(u_erstellt.nachname, '')) LIKE $pAnfNach
            OR LOWER(CONCAT(IFNULL(u_erstellt.vorname,''), ' ', IFNULL(u_erstellt.nachname,''))) LIKE $pAnfFull
            OR LOWER(IFNULL(u_zugewiesen.vorname, '')) LIKE $pBearbVor
            OR LOWER(IFNULL(u_zugewiesen.nachname, '')) LIKE $pBearbNach
            OR LOWER(CONCAT(IFNULL(u_zugewiesen.vorname,''), ' ', IFNULL(u_zugewiesen.nachname,''))) LIKE $pBearbFull
            OR LOWER(IFNULL(d.name, '')) LIKE $pDeviceName
            OR LOWER(IFNULL(d.typ, '')) LIKE $pDeviceTyp
            OR LOWER(IFNULL(d.hersteller, '')) LIKE $pDeviceHer
            OR LOWER(IFNULL(d.modell, '')) LIKE $pDeviceModell
            OR LOWER(IFNULL(d.seriennummer, '')) LIKE $pDeviceSn
            OR LOWER(IFNULL(d.mac_adresse, '')) LIKE $pDeviceMac
            OR LOWER(IFNULL(d.ip_adresse, '')) LIKE $pDeviceIp
            OR LOWER(IFNULL(d.betriebssystem, '')) LIKE $pDeviceOs
            OR LOWER(IFNULL(d.beschreibung, '')) LIKE $pDeviceBeschreibung
            OR EXISTS (SELECT 1 FROM ticket_comments tc WHERE tc.ticket_id = t.id AND LOWER(IFNULL(tc.kommentar, '')) LIKE $pComment)
            OR EXISTS (SELECT 1 FROM ticket_attachments ta WHERE ta.ticket_id = t.id AND LOWER(IFNULL(ta.dateiname, '')) LIKE $pAttachment)
            OR $termCompanySql
            OR $termCustomerSql
        )";
    }
    if (!empty($ticketTermGroups)) {
        // Mehrwort-Suche: strikt (alle Begriffe) ODER phrase-basiert (LIKE mit Wortfolge).
        // So verschwinden Ticket-Treffer nicht komplett bei Zwischenständen/Tokenisierung.
        $strictTermsSql = '(' . implode(' AND ', $ticketTermGroups) . ')';
        if (!empty($searchParts)) {
            $ticketWhere[] = '((' . implode(' OR ', $searchParts) . ') OR ' . $strictTermsSql . ')';
        } else {
            $ticketWhere[] = $strictTermsSql;
        }
    }
}

$wc = implode(' AND ', $ticketWhere);
try {
    $ticketFetchLimit = count($searchTerms) > 1
        ? min(800, max(120, $limit * 30))
        : min(200, max(40, $limit * 10));
    $sql = "SELECT 
        t.id, 
        t.ticket_nummer, 
        t.titel, 
        t.status,
        t.beschreibung,
        t.geaendert_datum,
        c.name as company_name,
        cust.name as customer_name,
        d.name as device_name,
        d.typ as device_typ,
        d.beschreibung as device_beschreibung,
        d.hersteller as device_hersteller,
        d.modell as device_modell,
        u_erstellt.vorname as anforderer_vorname,
        u_erstellt.nachname as anforderer_nachname
        FROM tickets t
        LEFT JOIN companies c ON t.company_id = c.id
        LEFT JOIN customers cust ON t.customer_id = cust.id
        LEFT JOIN devices d ON t.device_id = d.id
        LEFT JOIN users u_erstellt ON t.erstellt_von = u_erstellt.id
        LEFT JOIN users u_zugewiesen ON t.zugewiesen_an = u_zugewiesen.id
        WHERE $wc
        ORDER BY t.geaendert_datum DESC
        LIMIT $ticketFetchLimit";
    $stmt = $pdo->prepare($sql);
    foreach ($ticketParams as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $ticketRowsOut = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['company_name'] = decrypt_from_db($r['company_name'] ?? null);
        $r['customer_name'] = decrypt_from_db($r['customer_name'] ?? null);
        $custN = trim((string) ($r['customer_name'] ?? ''));
        $compN = trim((string) ($r['company_name'] ?? ''));
        $line1Parts = array_values(array_filter([$custN, $compN], static fn ($s) => $s !== ''));
        $ticketSubtitleLine1 = $line1Parts !== [] ? implode(' | ', $line1Parts) : '';

        $typRaw = isset($r['device_typ']) && $r['device_typ'] !== null && $r['device_typ'] !== ''
            ? (string) $r['device_typ'] : '';
        $deviceTypLabels = [
            'drucker' => 'Drucker',
            'computer' => 'Computer',
            'netzwerk' => 'Netzwerk',
            'smartphone' => 'Smartphone',
            'monitor' => 'Monitor',
            'divers' => 'Divers',
        ];
        $typLabel = $typRaw !== '' ? ($deviceTypLabels[$typRaw] ?? ucfirst($typRaw)) : '';
        $hm = trim(trim((string) ($r['device_hersteller'] ?? '')) . ' ' . trim((string) ($r['device_modell'] ?? '')));
        $standort = trim((string) ($r['device_beschreibung'] ?? ''));
        $ticketSubtitleLine2 = '';
        if ($typLabel !== '' || $hm !== '' || $standort !== '') {
            $left = '';
            if ($typLabel !== '' && $hm !== '') {
                $left = $typLabel . ': ' . $hm;
            } elseif ($typLabel !== '') {
                $left = $typLabel;
            } else {
                $left = $hm;
            }
            if ($standort !== '') {
                $ticketSubtitleLine2 = $left !== '' ? ($left . '; ' . $standort) : $standort;
            } else {
                $ticketSubtitleLine2 = $left;
            }
        }

        $anforderer = trim(trim((string) ($r['anforderer_vorname'] ?? '')) . ' ' . trim((string) ($r['anforderer_nachname'] ?? '')));
        $beschreibungPlain = (string) ($r['beschreibung'] ?? '');

        if (count($searchTerms) > 1) {
            $haystackParts = [
                (string) ($r['ticket_nummer'] ?? ''),
                (string) ($r['titel'] ?? ''),
                $beschreibungPlain,
                (string) ($r['status'] ?? ''),
                $ticketSubtitleLine1,
                $ticketSubtitleLine2,
                $anforderer,
                (string) ($r['device_name'] ?? ''),
                (string) ($r['device_typ'] ?? ''),
                (string) ($r['device_hersteller'] ?? ''),
                (string) ($r['device_modell'] ?? ''),
            ];
            $haystack = $normalizeSearchText(implode(' ', array_filter($haystackParts, static fn ($v) => trim((string) $v) !== '')));
            $haystackCompact = $compactSearchText($haystack);
            $allTermsMatch = true;
            foreach ($searchTerms as $termNeedle) {
                $needle = $normalizeSearchText((string) $termNeedle);
                $needleCompact = $compactSearchText($needle);
                $termMatches = mb_strpos($haystack, $needle) !== false;
                if (!$termMatches && $needleCompact !== '') {
                    $termMatches = mb_strpos($haystackCompact, $needleCompact) !== false;
                }
                if (!$termMatches) {
                    $allTermsMatch = false;
                    break;
                }
            }
            $phraseNeedle = $normalizeSearchText((string) $normalizedQuery);
            $phraseCompact = $compactSearchText($phraseNeedle);
            $phraseMatch = $phraseNeedle !== '' && mb_strpos($haystack, $phraseNeedle) !== false;
            if (!$phraseMatch && $phraseCompact !== '') {
                $phraseMatch = mb_strpos($haystackCompact, $phraseCompact) !== false;
            }
            if (!$allTermsMatch && !$phraseMatch) {
                continue;
            }
        }

        $subtitleRankParts = array_filter([$ticketSubtitleLine1, $ticketSubtitleLine2, $anforderer], static fn ($s) => $s !== '');
        $subtitleForRank = $subtitleRankParts !== [] ? implode("\n", $subtitleRankParts) : '';

        $geaendertTs = 0;
        if (!empty($r['geaendert_datum'])) {
            $geaendertTs = strtotime((string) $r['geaendert_datum']) ?: 0;
        }

        $rowForTier = [
            'title' => $r['titel'],
            'ticket_nummer' => (string) ($r['ticket_nummer'] ?? ''),
            'ticket_subtitle_line1' => $ticketSubtitleLine1,
            'ticket_subtitle_line2' => $ticketSubtitleLine2,
            'ticket_anforderer' => $anforderer,
            'ticket_status' => (string) ($r['status'] ?? ''),
            '_beschreibung_plain' => $beschreibungPlain,
        ];
        $ticketTierVal = $globalSearchTicketTier($rowForTier, $q);
        $ticketClosedArchiv = in_array((string) ($r['status'] ?? ''), ['Geschlossen', 'Archiv'], true);
        $ticketRowsOut[] = [
            'payload' => [
                'type' => 'ticket',
                'type_label' => 'Tickets',
                'id' => (int) $r['id'],
                'title' => $r['titel'],
                'subtitle' => $subtitleForRank,
                'ticket_subtitle_line1' => $ticketSubtitleLine1,
                'ticket_subtitle_line2' => $ticketSubtitleLine2,
                'ticket_anforderer' => $anforderer,
                'ticket_nummer' => (string) ($r['ticket_nummer'] ?? ''),
                'ticket_status' => (string) ($r['status'] ?? ''),
                'url' => $baseUrl . 'tickets/view.php?id=' . (int) $r['id'],
                'ticket_sort_ts' => $geaendertTs,
                '_ticket_tier' => $ticketTierVal,
                '_ticket_closed_archiv' => $ticketClosedArchiv,
            ],
            'closed_or_archiv' => $ticketClosedArchiv,
            'tier' => $ticketTierVal,
        ];
    }
    usort($ticketRowsOut, static function (array $a, array $b): int {
        if ($a['closed_or_archiv'] !== $b['closed_or_archiv']) {
            return $a['closed_or_archiv'] ? 1 : -1;
        }
        if ($a['tier'] !== $b['tier']) {
            return $a['tier'] <=> $b['tier'];
        }
        $ta = (int) ($a['payload']['ticket_sort_ts'] ?? 0);
        $tb = (int) ($b['payload']['ticket_sort_ts'] ?? 0);
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }

        return ($a['payload']['id'] ?? 0) <=> ($b['payload']['id'] ?? 0);
    });
    $ticketRowsOut = array_slice($ticketRowsOut, 0, $limit);
    foreach ($ticketRowsOut as $tro) {
        $results[] = $tro['payload'];
    }
} catch (PDOException $e) {
    error_log('search.php Ticket-Suche: ' . $e->getMessage());
    // Robuster Fallback fuer alle Rollen, falls die komplexe Join-Abfrage fehlschlaegt.
    try {
        $fallbackWhere = ["t.titel NOT LIKE '[Gelöscht] %'"];
        $fallbackParams = [];
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            // alle Tickets
        } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
            $fallbackWhere[] = "(t.erstellt_von = :fb_user_id_t OR t.zugewiesen_an = :fb_user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :fb_observer_id) OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :fb_user_company_id))";
            $fallbackParams[':fb_user_id_t'] = $userId;
            $fallbackParams[':fb_user_id_z'] = $userId;
            $fallbackParams[':fb_observer_id'] = $userId;
            $fallbackParams[':fb_user_company_id'] = $userCompanyId;
        } elseif ($userRole === 'Firmen-User' && $userCompanyId) {
            $fallbackWhere[] = "(t.erstellt_von = :fb_user_id_t OR t.zugewiesen_an = :fb_user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :fb_observer_id) OR t.company_id = :fb_user_company_id OR EXISTS (SELECT 1 FROM customers cust2 WHERE cust2.id = t.customer_id AND cust2.company_id = :fb_user_company_id2))";
            $fallbackParams[':fb_user_id_t'] = $userId;
            $fallbackParams[':fb_user_id_z'] = $userId;
            $fallbackParams[':fb_observer_id'] = $userId;
            $fallbackParams[':fb_user_company_id'] = $userCompanyId;
            $fallbackParams[':fb_user_company_id2'] = $userCompanyId;
        } elseif ($userRole === 'Firmen-User' || $userRole === 'Kunde') {
            $fallbackWhere[] = "(t.erstellt_von = :fb_user_id_t OR t.zugewiesen_an = :fb_user_id_z OR EXISTS (SELECT 1 FROM ticket_observers to2 WHERE to2.ticket_id = t.id AND to2.user_id = :fb_observer_id))";
            $fallbackParams[':fb_user_id_t'] = $userId;
            $fallbackParams[':fb_user_id_z'] = $userId;
            $fallbackParams[':fb_observer_id'] = $userId;
        } else {
            $fallbackWhere[] = "1=0";
        }

        $fallbackTerms = !empty($searchTerms) ? $searchTerms : [$normalizedQuery];
        $fallbackTermGroups = [];
        foreach ($fallbackTerms as $idx => $term) {
            $tp = ':fb_term_' . $idx;
            $fallbackParams[$tp] = '%' . mb_strtolower(trim((string) $term), 'UTF-8') . '%';
            $fallbackTermGroups[] = "(LOWER(IFNULL(t.ticket_nummer, '')) LIKE $tp OR LOWER(IFNULL(t.titel, '')) LIKE $tp OR LOWER(IFNULL(t.beschreibung, '')) LIKE $tp)";
        }
        if (!empty($fallbackTermGroups)) {
            $fallbackWhere[] = '(' . implode(' AND ', $fallbackTermGroups) . ')';
        }

        $fallbackSql = "SELECT t.id, t.ticket_nummer, t.titel, t.status
            FROM tickets t
            WHERE " . implode(' AND ', $fallbackWhere) . "
            ORDER BY t.id DESC
            LIMIT :fb_limit";
        $fallbackStmt = $pdo->prepare($fallbackSql);
        foreach ($fallbackParams as $k => $v) {
            $fallbackStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $fallbackStmt->bindValue(':fb_limit', (int) $limit, PDO::PARAM_INT);
        $fallbackStmt->execute();
        foreach ($fallbackStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'type' => 'ticket',
                'type_label' => 'Tickets',
                'id' => (int) $r['id'],
                'title' => (string) ($r['titel'] ?? ''),
                'subtitle' => '',
                'ticket_subtitle_line1' => '',
                'ticket_subtitle_line2' => '',
                'ticket_anforderer' => '',
                'ticket_nummer' => (string) ($r['ticket_nummer'] ?? ''),
                'ticket_status' => (string) ($r['status'] ?? ''),
                'url' => $baseUrl . 'tickets/view.php?id=' . (int) $r['id'],
            ];
        }
    } catch (PDOException $fallbackEx) {
        error_log('search.php Ticket-Fallback: ' . $fallbackEx->getMessage());
    }
}
}

// === AUFGABEN / Todos (nur Admin/Techniker) – Suche nach Entschlüsselung, damit verschlüsselte Titel gefunden werden ===
if ($shouldSearchType('aufgabe') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
    try {
        $hasProjectNummer = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'project_nummer'");
            $hasProjectNummer = $col && $col->rowCount() > 0;
        } catch (PDOException $e) {
        }
        $projectNummerSel = $hasProjectNummer ? ', p.project_nummer AS project_nummer' : ', NULL AS project_nummer';
        $sql = "SELECT todos.id, todos.titel, todos.beschreibung, todos.status, todos.faellig_am, todos.ticket_id, todos.project_id,
                t.ticket_nummer,
                c.name AS company_name,
                p.bezeichnung AS project_name
                $projectNummerSel,
                COALESCE(att.attachment_count, 0) AS attachment_count
            FROM todos
            LEFT JOIN tickets t ON todos.ticket_id = t.id
            LEFT JOIN companies c ON todos.company_id = c.id
            LEFT JOIN projects p ON todos.project_id = p.id
            LEFT JOIN (
                SELECT todo_id, COUNT(*) AS attachment_count
                FROM todo_attachments
                GROUP BY todo_id
            ) att ON todos.id = att.todo_id
            ORDER BY CASE WHEN todos.status = 'erledigt' THEN 1 ELSE 0 END ASC,
                COALESCE(todos.geaendert_datum, todos.erstellt_datum) DESC
            LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $qLower = mb_strtolower($normalizedQuery);
        $found = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($found >= $limit) {
                break;
            }
            $titelPlain = decrypt_from_db($r['titel']);
            $beschreibungPlain = isset($r['beschreibung']) ? decrypt_from_db($r['beschreibung']) : '';
            $titelNoSpaces = str_replace(' ', '', $titelPlain);
            $matchTitel = $qLower === '' || mb_stripos($titelPlain, $normalizedQuery) !== false || (mb_strlen($qLower) >= 2 && mb_stripos($titelNoSpaces, $qNoSpaces) !== false);
            $beschNoSpaces = str_replace(' ', '', $beschreibungPlain);
            $matchBeschreibung = $beschreibungPlain !== '' && (mb_stripos($beschreibungPlain, $normalizedQuery) !== false || (mb_strlen($qLower) >= 2 && mb_stripos($beschNoSpaces, $qNoSpaces) !== false));
            if (!$matchTitel && !$matchBeschreibung) {
                continue;
            }
            $companyPlain = isset($r['company_name']) ? decrypt_from_db($r['company_name']) : '';
            $statusRaw = (string) ($r['status'] ?? 'offen');
            if ($statusRaw === 'erledigt') {
                $statusLabel = 'Erledigt';
            } elseif ($statusRaw === 'in_bearbeitung') {
                $statusLabel = 'In Bearbeitung';
            } else {
                $statusLabel = 'Offen';
            }
            $faelligIso = null;
            if (!empty($r['faellig_am'])) {
                $faelligIso = $r['faellig_am'];
            }
            $projNum = isset($r['project_nummer']) && $r['project_nummer'] !== null && $r['project_nummer'] !== ''
                ? (string) $r['project_nummer'] : '';
            $results[] = [
                'type' => 'aufgabe',
                'type_label' => 'Aufgabe',
                'id' => (int) $r['id'],
                'title' => $titelPlain,
                'subtitle' => '',
                'url' => $baseUrl . 'todos/?id=' . (int) $r['id'],
                'todo_status' => $statusRaw,
                'todo_status_label' => $statusLabel,
                'todo_has_description' => trim($beschreibungPlain) !== '',
                'todo_attachment_count' => (int) ($r['attachment_count'] ?? 0),
                'todo_company_name' => trim((string) $companyPlain),
                'todo_ticket_nummer' => isset($r['ticket_nummer']) && $r['ticket_nummer'] !== null && $r['ticket_nummer'] !== ''
                    ? (string) $r['ticket_nummer'] : '',
                'todo_faellig_am' => $faelligIso,
                'todo_project_id' => isset($r['project_id']) ? (int) $r['project_id'] : 0,
                'todo_project_nummer' => $projNum,
            ];
            $found++;
        }
    } catch (PDOException $e) {
    }
}

// === GERÄTE ===
if ($shouldSearchType('geraet')) {
// Native PDO (EMULATE_PREPARES=false): jeder benannte Platzhalter nur einmal pro Statement — sonst HY093, leere Treffer
$deviceParams = [':dq1' => $searchLike, ':dq2_typ' => $searchLike, ':dq2_her' => $searchLike, ':dq3' => $searchLike, ':dq4' => $searchLike, ':dq5' => $searchLike, ':dq6' => $searchLike, ':dq7' => $searchLike, ':dq8' => $searchLike];
foreach ($fuzzyLikes as $i => $val) {
    $deviceParams[':devname_f' . $i] = $val;
    $deviceParams[':devbesch_f' . $i] = $val;
}
foreach ($fuzzyLikes2 as $i => $val) {
    $deviceParams[':devname_g' . $i] = $val;
    $deviceParams[':devbesch_g' . $i] = $val;
}
foreach ($fuzzyLikes3 as $i => $val) {
    $deviceParams[':devname_h' . $i] = $val;
    $deviceParams[':devbesch_h' . $i] = $val;
}
if ($searchLikeNoSpaces !== null) {
    $deviceParams[':devname_nospaces'] = $searchLikeNoSpaces;
    $deviceParams[':devsn_nospaces'] = $searchLikeNoSpaces;
    $deviceParams[':devbesch_nospaces'] = $searchLikeNoSpaces;
}
$dNameOr = ['d.name LIKE :dq1'];
if ($searchLikeNoSpaces !== null) {
    $dNameOr[] = "LOWER(REPLACE(d.name, ' ', '')) LIKE :devname_nospaces";
}
for ($i = 0; $i < count($fuzzyLikes); $i++) {
    $dNameOr[] = "LOWER(REPLACE(d.name, ' ', '')) LIKE :devname_f" . $i;
}
for ($i = 0; $i < count($fuzzyLikes2); $i++) {
    $dNameOr[] = "LOWER(REPLACE(d.name, ' ', '')) LIKE :devname_g" . $i;
}
for ($i = 0; $i < count($fuzzyLikes3); $i++) {
    $dNameOr[] = "LOWER(REPLACE(d.name, ' ', '')) LIKE :devname_h" . $i;
}
$seriennummerOr = ['d.seriennummer LIKE :dq4'];
if ($searchLikeNoSpaces !== null) {
    $seriennummerOr[] = "LOWER(REPLACE(IFNULL(d.seriennummer, ''), ' ', '')) LIKE :devsn_nospaces";
}
// Standort/Beschreibung: wie Gerätename (Leerzeichen, Fuzzy) — reines LIKE reichte oft nicht
$dBeschOr = ['d.beschreibung LIKE :dq5'];
if ($searchLikeNoSpaces !== null) {
    $dBeschOr[] = "LOWER(REPLACE(COALESCE(d.beschreibung, ''), ' ', '')) LIKE :devbesch_nospaces";
}
for ($i = 0; $i < count($fuzzyLikes); $i++) {
    $dBeschOr[] = "LOWER(REPLACE(COALESCE(d.beschreibung, ''), ' ', '')) LIKE :devbesch_f" . $i;
}
for ($i = 0; $i < count($fuzzyLikes2); $i++) {
    $dBeschOr[] = "LOWER(REPLACE(COALESCE(d.beschreibung, ''), ' ', '')) LIKE :devbesch_g" . $i;
}
for ($i = 0; $i < count($fuzzyLikes3); $i++) {
    $dBeschOr[] = "LOWER(REPLACE(COALESCE(d.beschreibung, ''), ' ', '')) LIKE :devbesch_h" . $i;
}
// Firma/Kunde verschlüsselt — LIKE auf JOIN-Spalten liefert keine Treffer; Klartext-IDs wie bei Tickets
$globalDeviceCompanyIds = ticket_search_company_ids_matching_plaintext($pdo, $normalizedQuery);
$globalDeviceCustomerIds = ticket_search_customer_ids_matching_plaintext($pdo, $normalizedQuery);
$devCompanySql = !empty($globalDeviceCompanyIds)
    ? 'd.company_id IN (' . implode(',', array_map('intval', $globalDeviceCompanyIds)) . ')'
    : '1=0';
$devCustomerSql = !empty($globalDeviceCustomerIds)
    ? 'd.customer_id IN (' . implode(',', array_map('intval', $globalDeviceCustomerIds)) . ')'
    : '1=0';
$deviceWhere = ["((" . implode(' OR ', $dNameOr) . ") OR d.typ LIKE :dq2_typ OR d.hersteller LIKE :dq2_her OR d.modell LIKE :dq3 OR (" . implode(' OR ', $seriennummerOr) . ")
    OR (" . implode(' OR ', $dBeschOr) . ")
    OR d.mac_adresse LIKE :dq6 OR d.ip_adresse LIKE :dq7 OR d.betriebssystem LIKE :dq8
    OR " . $devCompanySql . ' OR ' . $devCustomerSql . ')'];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle Geräte
} elseif (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $userCompanyId) {
    // Wie devices/api/devices.php: eigene Firma + Kunden dieser Firma (+ firmenlose Kunden in Standard-Ansicht)
    $deviceWhere[] = "(d.company_id = :company_id OR d.customer_id IN (SELECT id FROM customers WHERE company_id = :company_id2 OR company_id IS NULL))";
    $deviceParams[':company_id'] = $userCompanyId;
    $deviceParams[':company_id2'] = $userCompanyId;
} elseif ($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') {
    $deviceWhere[] = '1=0';
} elseif ($userRole === 'Kunde' && $userCustomerId) {
    $deviceWhere[] = "d.customer_id = :customer_id";
    $deviceParams[':customer_id'] = $userCustomerId;
} else {
    $deviceWhere[] = "1=0";
}
$deviceWc = implode(' AND ', $deviceWhere);
$deviceSqlLimit = min(50, max(20, $limit * 2));
try {
    $sql = "SELECT d.id, d.name, d.hersteller, d.modell, d.seriennummer, d.beschreibung
        FROM devices d
        WHERE $deviceWc
        ORDER BY d.geaendert_datum DESC
        LIMIT $deviceSqlLimit";
    $stmt = $pdo->prepare($sql);
    foreach ($deviceParams as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sub = trim(($r['hersteller'] ?? '') . ' ' . ($r['modell'] ?? ''));
        if (!empty($r['seriennummer'])) {
            $sub .= ($sub !== '' ? ' · ' : '') . $r['seriennummer'];
        }
        $besch = isset($r['beschreibung']) ? trim((string) $r['beschreibung']) : '';
        if ($besch !== '') {
            $standortLabel = mb_strlen($besch, 'UTF-8') > 140 ? (mb_substr($besch, 0, 137, 'UTF-8') . '…') : $besch;
            $sub .= ($sub !== '' ? ' · ' : '') . 'Standort: ' . $standortLabel;
        }
        $results[] = [
            'type' => 'geraet',
            'type_label' => 'Gerät',
            'id' => (int) $r['id'],
            'title' => $r['name'],
            'subtitle' => $sub,
            'url' => $baseUrl . 'devices/detail.php?id=' . (int) $r['id'],
        ];
    }
} catch (PDOException $e) {
        error_log('search.php Geräte-Suche: ' . $e->getMessage());
        // Robuster Fallback fuer Admin/Techniker.
        if ($userRole === 'Admin' || $userRole === 'Techniker') {
            try {
                $fallbackStmt = $pdo->prepare("
                    SELECT d.id, d.name, d.hersteller, d.modell, d.seriennummer
                    FROM devices d
                    WHERE d.name LIKE :q
                       OR d.hersteller LIKE :q
                       OR d.modell LIKE :q
                       OR d.seriennummer LIKE :q
                    ORDER BY d.id DESC
                    LIMIT :limit
                ");
                $fallbackStmt->bindValue(':q', $searchLike, PDO::PARAM_STR);
                $fallbackStmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
                $fallbackStmt->execute();
                foreach ($fallbackStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $sub = trim((string) (($r['hersteller'] ?? '') . ' ' . ($r['modell'] ?? '')));
                    if (!empty($r['seriennummer'])) {
                        $sub .= ($sub !== '' ? ' · ' : '') . (string) $r['seriennummer'];
                    }
                    $results[] = [
                        'type' => 'geraet',
                        'type_label' => 'Gerät',
                        'id' => (int) $r['id'],
                        'title' => (string) ($r['name'] ?? ''),
                        'subtitle' => $sub,
                        'url' => $baseUrl . 'devices/detail.php?id=' . (int) $r['id'],
                    ];
                }
            } catch (PDOException $fallbackEx) {
                error_log('search.php Geräte-Fallback: ' . $fallbackEx->getMessage());
            }
        }
    }
}

// === BESTELLUNGEN ===
if ($shouldSearchType('bestellung')) {
$orderParams = [':q1' => $searchLike, ':q2' => $searchLike];
foreach ($fuzzyLikes as $i => $val) $orderParams[':order_f' . $i] = $val;
foreach ($fuzzyLikes2 as $i => $val) $orderParams[':order_g' . $i] = $val;
foreach ($fuzzyLikes3 as $i => $val) $orderParams[':order_h' . $i] = $val;
if ($searchLikeNoSpaces !== null) $orderParams[':order_nospaces'] = $searchLikeNoSpaces;
$orderDescOr = ['o.beschreibung LIKE :q2'];
if ($searchLikeNoSpaces !== null) $orderDescOr[] = "LOWER(REPLACE(o.beschreibung, ' ', '')) LIKE :order_nospaces";
for ($i = 0; $i < count($fuzzyLikes); $i++) $orderDescOr[] = "LOWER(REPLACE(o.beschreibung, ' ', '')) LIKE :order_f" . $i;
for ($i = 0; $i < count($fuzzyLikes2); $i++) $orderDescOr[] = "LOWER(REPLACE(o.beschreibung, ' ', '')) LIKE :order_g" . $i;
for ($i = 0; $i < count($fuzzyLikes3); $i++) $orderDescOr[] = "LOWER(REPLACE(o.beschreibung, ' ', '')) LIKE :order_h" . $i;
$orderWhere = ["(o.bestellnummer LIKE :q1 OR " . implode(' OR ', $orderDescOr) . ")"];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    // Alle Bestellungen
} elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
    $orderWhere[] = "o.company_id = :company_id";
    $orderParams[':company_id'] = $userCompanyId;
} elseif ($userRole === 'Firmen-User') {
    $orderWhere[] = "o.erstellt_von = :user_id";
    $orderParams[':user_id'] = $userId;
} elseif ($userRole === 'Kunde') {
    $orderWhere[] = "1=0"; // Kunde: Bestellungsseite gesperrt
} else {
    $orderWhere[] = "1=0";
}
$orderWc = implode(' AND ', $orderWhere);
try {
    $sql = "SELECT o.id, o.bestellnummer, o.beschreibung, o.status
        FROM orders o
        WHERE $orderWc
        ORDER BY o.erstellt_datum DESC
        LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    foreach ($orderParams as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $desc = trim($r['beschreibung'] ?? '');
        $results[] = [
            'type' => 'bestellung',
            'type_label' => 'Bestellung',
            'id' => (int) $r['id'],
            'title' => $desc !== '' ? mb_substr($desc, 0, 80) : $r['bestellnummer'],
            'subtitle' => $r['bestellnummer'] . (isset($r['status']) && $r['status'] !== '' ? ' · ' . $r['status'] : ''),
            'url' => $baseUrl . 'orders/detail.php?id=' . (int) $r['id'],
        ];
    }
} catch (PDOException $e) {}
}

// === FIRMEN === (Klartext nach Entschlüsselung — keine SQL-LIKE auf ENC:-Spalten)
if ($shouldSearchType('firma') && ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin')) {
    $firmaMatchIds = ticket_search_company_ids_matching_plaintext($pdo, $normalizedQuery);
    if ($userRole === 'Firmen-Admin' && $userCompanyId) {
        $firmaMatchIds = array_values(array_intersect($firmaMatchIds, [$userCompanyId]));
    }
    if (!empty($firmaMatchIds)) {
        $firmaMatchIds = array_slice(array_values(array_unique(array_map('intval', $firmaMatchIds))), 0, $limit);
        $in = implode(',', $firmaMatchIds);
        try {
            $sql = "SELECT c.id, c.name, c.status FROM companies c WHERE c.id IN ($in) ORDER BY c.name ASC LIMIT $limit";
            $stmt = $pdo->query($sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                decrypt_company_row($r);
                $results[] = [
                    'type' => 'firma',
                    'type_label' => 'Firma',
                    'id' => (int) $r['id'],
                    'title' => $r['name'],
                    'subtitle' => $r['status'] ?? '',
                    'url' => $baseUrl . 'companies/detail.php?id=' . (int) $r['id'],
                ];
            }
        } catch (PDOException $e) {}
    }
}

// === KUNDEN === (Klartext nach Entschlüsselung)
if ($shouldSearchType('kunde') && ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin')) {
    $kundeMatchIds = ticket_search_customer_ids_matching_plaintext($pdo, $normalizedQuery);
    if ($userRole === 'Firmen-Admin' && $userCompanyId && !empty($kundeMatchIds)) {
        $ph = implode(',', array_fill(0, count($kundeMatchIds), '?'));
        try {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE id IN ($ph) AND company_id = ?");
            $stmt->execute(array_merge(array_map('intval', $kundeMatchIds), [$userCompanyId]));
            $kundeMatchIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
        } catch (PDOException $e) {
            $kundeMatchIds = [];
        }
    }
    if (!empty($kundeMatchIds)) {
        $kundeMatchIds = array_slice(array_values(array_unique(array_map('intval', $kundeMatchIds))), 0, $limit);
        $in = implode(',', $kundeMatchIds);
        try {
            $sql = "SELECT cust.id, cust.name FROM customers cust WHERE cust.id IN ($in) ORDER BY cust.name ASC LIMIT $limit";
            $stmt = $pdo->query($sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                decrypt_customer_row($r);
                $results[] = [
                    'type' => 'kunde',
                    'type_label' => 'Kunde',
                    'id' => (int) $r['id'],
                    'title' => $r['name'],
                    'subtitle' => '',
                    'url' => $baseUrl . 'customers/detail.php?id=' . (int) $r['id'],
                ];
            }
        } catch (PDOException $e) {}
    }
}

// === WISSENSDATENBANK (Titel, Slug und Inhalt; optional Firmenfilter) ===
if ($shouldSearchType('artikel') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
    try {
        $kbParams = [':kbq1' => $searchLike, ':kbq2' => $searchLike, ':kbq3' => $searchLike];
        foreach ($fuzzyLikes as $i => $val) $kbParams[':kb_f' . $i] = $val;
        foreach ($fuzzyLikes2 as $i => $val) $kbParams[':kb_g' . $i] = $val;
        foreach ($fuzzyLikes3 as $i => $val) $kbParams[':kb_h' . $i] = $val;
        if ($searchLikeNoSpaces !== null) $kbParams[':kb_nospaces'] = $searchLikeNoSpaces;
        $kbTitleOr = ['p.title LIKE :kbq1'];
        if ($searchLikeNoSpaces !== null) $kbTitleOr[] = "LOWER(REPLACE(p.title, ' ', '')) LIKE :kb_nospaces";
        for ($i = 0; $i < count($fuzzyLikes); $i++) $kbTitleOr[] = "LOWER(REPLACE(p.title, ' ', '')) LIKE :kb_f" . $i;
        for ($i = 0; $i < count($fuzzyLikes2); $i++) $kbTitleOr[] = "LOWER(REPLACE(p.title, ' ', '')) LIKE :kb_g" . $i;
        for ($i = 0; $i < count($fuzzyLikes3); $i++) $kbTitleOr[] = "LOWER(REPLACE(p.title, ' ', '')) LIKE :kb_h" . $i;
        $kbWhere = ["p.deleted_at IS NULL", "((" . implode(' OR ', $kbTitleOr) . ") OR p.slug LIKE :kbq2 OR p.content LIKE :kbq3)"];
        // Firmenfilter: expliziter Parameter kb_company_id ODER Session (nur wenn Firma aktiv gewählt, id > 0)
        $kbCompanyParam = isset($_GET['kb_company_id']) && $_GET['kb_company_id'] !== '' ? (int) $_GET['kb_company_id'] : null;
        if ($kbCompanyParam <= 0) {
            $sid = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;
            $kbCompanyParam = ($sid !== null && $sid !== '' && $sid !== '0') ? (int) $sid : null;
        }
        if ($kbCompanyParam > 0) {
            $kbWhere[] = "(p.company_id IS NULL OR p.company_id = :kb_company_id)";
            $kbParams[':kb_company_id'] = $kbCompanyParam;
        }
        $kbWc = implode(' AND ', $kbWhere);
        $sql = "SELECT p.id, p.title, p.slug, p.company_id, c.name AS company_name
            FROM kb_pages p
            LEFT JOIN companies c ON c.id = p.company_id
            WHERE $kbWc
            ORDER BY p.updated_at DESC
            LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        foreach ($kbParams as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['company_name'] = decrypt_from_db($r['company_name'] ?? null);
            if (!empty($r['company_name'])) {
                $subtitle = 'Firma: ' . $r['company_name'];
            } else {
                $subtitle = 'Globale Seite';
            }
            $results[] = [
                'type' => 'artikel',
                'type_label' => 'Wissensdatenbank',
                'id' => $r['id'],
                'title' => $r['title'],
                'subtitle' => $subtitle,
                'url' => $baseUrl . 'knowledge/?id=' . rawurlencode($r['id']),
            ];
        }
    } catch (PDOException $e) {}
}

// === PROJEKTE ===
if ($shouldSearchType('projekt') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
    try {
        $projParams = [':q1' => $searchLike];
        foreach ($fuzzyLikes as $i => $val) $projParams[':proj_f' . $i] = $val;
        foreach ($fuzzyLikes2 as $i => $val) $projParams[':proj_g' . $i] = $val;
        foreach ($fuzzyLikes3 as $i => $val) $projParams[':proj_h' . $i] = $val;
        if ($searchLikeNoSpaces !== null) $projParams[':proj_nospaces'] = $searchLikeNoSpaces;
        $projOr = ['p.bezeichnung LIKE :q1'];
        if ($searchLikeNoSpaces !== null) $projOr[] = "LOWER(REPLACE(p.bezeichnung, ' ', '')) LIKE :proj_nospaces";
        for ($i = 0; $i < count($fuzzyLikes); $i++) $projOr[] = "LOWER(REPLACE(p.bezeichnung, ' ', '')) LIKE :proj_f" . $i;
        for ($i = 0; $i < count($fuzzyLikes2); $i++) $projOr[] = "LOWER(REPLACE(p.bezeichnung, ' ', '')) LIKE :proj_g" . $i;
        for ($i = 0; $i < count($fuzzyLikes3); $i++) $projOr[] = "LOWER(REPLACE(p.bezeichnung, ' ', '')) LIKE :proj_h" . $i;
        $projWhere = ["(" . implode(' OR ', $projOr) . ")"];
        $projWc = implode(' AND ', $projWhere);
        $sql = "SELECT p.id, p.bezeichnung, p.status
            FROM projects p
            WHERE $projWc
            ORDER BY p.geaendert_datum DESC
            LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        foreach ($projParams as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'type' => 'projekt',
                'type_label' => 'Projekt',
                'id' => (int) $r['id'],
                'title' => $r['bezeichnung'],
                'subtitle' => $r['status'] ?? '',
                'url' => $baseUrl . 'projects/view.php?id=' . (int) $r['id'],
            ];
        }
    } catch (PDOException $e) {}
}

// === Lager / Verbrauchsmaterialien ===
// Nur Admin und Techniker haben Zugriff auf das Lager
if ($shouldSearchType('inventar') && ($userRole === 'Admin' || $userRole === 'Techniker')) {
    try {
        $consParams = [':ivq1' => $searchLike, ':ivq2' => $searchLike, ':ivq3' => $searchLike, ':ivq4' => $searchLike];
        foreach ($fuzzyLikes as $i => $val) $consParams[':cons_f' . $i] = $val;
        foreach ($fuzzyLikes2 as $i => $val) $consParams[':cons_g' . $i] = $val;
        foreach ($fuzzyLikes3 as $i => $val) $consParams[':cons_h' . $i] = $val;
        if ($searchLikeNoSpaces !== null) $consParams[':cons_nospaces'] = $searchLikeNoSpaces;
        $consBezOr = ['c.bezeichnung LIKE :ivq1'];
        if ($searchLikeNoSpaces !== null) $consBezOr[] = "LOWER(REPLACE(c.bezeichnung, ' ', '')) LIKE :cons_nospaces";
        for ($i = 0; $i < count($fuzzyLikes); $i++) $consBezOr[] = "LOWER(REPLACE(c.bezeichnung, ' ', '')) LIKE :cons_f" . $i;
        for ($i = 0; $i < count($fuzzyLikes2); $i++) $consBezOr[] = "LOWER(REPLACE(c.bezeichnung, ' ', '')) LIKE :cons_g" . $i;
        for ($i = 0; $i < count($fuzzyLikes3); $i++) $consBezOr[] = "LOWER(REPLACE(c.bezeichnung, ' ', '')) LIKE :cons_h" . $i;
        $consWhere = ["((" . implode(' OR ', $consBezOr) . ") OR c.artikelnummer LIKE :ivq2 OR c.beschreibung LIKE :ivq3 OR c.ean LIKE :ivq4)"];
        $consWc = implode(' AND ', $consWhere);
        $sql = "SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.lagerbestand
            FROM consumables c
            WHERE $consWc
            ORDER BY c.bezeichnung ASC
            LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        foreach ($consParams as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $parts = [];
            if (trim($r['artikelnummer'] ?? '') !== '') {
                $parts[] = 'Art. ' . trim($r['artikelnummer']);
            }
            if (trim($r['ean'] ?? '') !== '') {
                $parts[] = 'EAN ' . trim($r['ean']);
            }
            if (isset($r['lagerbestand']) && $r['lagerbestand'] !== '') {
                $parts[] = 'Auf Lager: ' . $r['lagerbestand'];
            }
            $sub = implode(' · ', $parts);
            $lagerbestand = isset($r['lagerbestand']) ? (int) $r['lagerbestand'] : 0;
            $results[] = [
                'type' => 'inventar',
                'type_label' => 'Lager',
                'id' => (int) $r['id'],
                'title' => $r['bezeichnung'],
                'subtitle' => $sub,
                'url' => $baseUrl . 'inventory/detail.php?id=' . (int) $r['id'],
                'lagerbestand' => $lagerbestand,
                'ean' => trim($r['ean'] ?? ''),
            ];
        }
    } catch (PDOException $e) {
        // consumables-Tabelle evtl. nicht vorhanden – ignorieren
    }
}

// === BENUTZER (nur Admin) ===
if ($shouldSearchType('benutzer') && $userRole === 'Admin') {
    try {
        $sql = "SELECT u.id, u.email, u.vorname, u.nachname, u.rolle, u.status, u.telefonnummer,
                c.name AS company_name, cust.name AS customer_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN customers cust ON u.customer_id = cust.id
            WHERE (
                u.email LIKE :buq1 OR u.vorname LIKE :buq2 OR u.nachname LIKE :buq3 OR u.telefonnummer LIKE :buq4
                OR CONCAT(COALESCE(u.vorname, ''), ' ', COALESCE(u.nachname, '')) LIKE :buq5
                OR CONCAT(COALESCE(u.nachname, ''), ' ', COALESCE(u.vorname, '')) LIKE :buq6
            )
            ORDER BY u.nachname ASC, u.vorname ASC, u.email ASC
            LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        foreach ([':buq1', ':buq2', ':buq3', ':buq4', ':buq5', ':buq6'] as $p) {
            $stmt->bindValue($p, $searchLike, PDO::PARAM_STR);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['company_name'] = decrypt_from_db($r['company_name'] ?? null);
            $r['customer_name'] = decrypt_from_db($r['customer_name'] ?? null);
            $displayName = trim((string) ($r['vorname'] ?? '') . ' ' . (string) ($r['nachname'] ?? ''));
            if ($displayName === '') {
                $displayName = (string) ($r['email'] ?? '');
            }
            $subParts = [(string) ($r['rolle'] ?? ''), (string) ($r['status'] ?? '')];
            if ($displayName !== (string) ($r['email'] ?? '')) {
                array_unshift($subParts, (string) ($r['email'] ?? ''));
            }
            $cn = trim((string) ($r['company_name'] ?? ''));
            if ($cn !== '') {
                $subParts[] = $cn;
            }
            $custN = trim((string) ($r['customer_name'] ?? ''));
            if ($custN !== '') {
                $subParts[] = $custN;
            }
            $results[] = [
                'type' => 'benutzer',
                'type_label' => 'Benutzer',
                'id' => (int) $r['id'],
                'title' => $displayName,
                'subtitle' => implode(' · ', array_filter($subParts, static fn ($x) => $x !== '')),
                'url' => $baseUrl . 'admin/users.php?expand=' . (int) $r['id'],
            ];
        }
    } catch (PDOException $e) {
        // users-Abfrage evtl. fehlgeschlagen – ignorieren
    }
}

// Treffer mit exakter Vorkommensweise des Suchbegriffs (in Titel oder Untertitel) nach oben;
// zwei Tickets: gleiche Reihenfolge wie oben (exakt zuerst, Geschlossen/Archiv zuletzt).
if ($q !== '' && count($results) > 1) {
    $isStrictExactMatch = static function (array $r, string $needle): bool {
        $n = mb_strtolower(trim($needle), 'UTF-8');
        if ($n === '') {
            return false;
        }
        $fields = [
            (string) ($r['title'] ?? ''),
            (string) ($r['subtitle'] ?? ''),
            (string) ($r['ticket_nummer'] ?? ''),
            (string) ($r['ticket_status'] ?? ''),
            (string) ($r['ticket_subtitle_line1'] ?? ''),
            (string) ($r['ticket_subtitle_line2'] ?? ''),
            (string) ($r['ticket_anforderer'] ?? ''),
            (string) ($r['todo_ticket_nummer'] ?? ''),
            (string) ($r['todo_company_name'] ?? ''),
            (string) ($r['todo_project_nummer'] ?? ''),
        ];
        foreach ($fields as $value) {
            $v = mb_strtolower(trim($value), 'UTF-8');
            if ($v !== '' && mb_strpos($v, $n, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    };
    $exactRank = static function (array $r, string $needle): int {
        $t = (string) ($r['title'] ?? '');
        $s = (string) ($r['subtitle'] ?? '');
        $tn = (string) ($r['ticket_nummer'] ?? '');
        if ($needle === '') {
            return 5;
        }
        if (mb_stripos($t, $needle) !== false) {
            return 0;
        }
        if ($t !== '' && ticket_search_plaintext_matches($t, $needle)) {
            return 1;
        }
        if (mb_stripos($s, $needle) !== false) {
            return 2;
        }
        if ($s !== '' && ticket_search_plaintext_matches($s, $needle)) {
            return 3;
        }
        if ($tn !== '' && mb_stripos($tn, $needle) !== false) {
            return 2;
        }
        $tst = (string) ($r['ticket_status'] ?? '');
        if ($tst !== '' && mb_stripos($tst, $needle) !== false) {
            return 2;
        }

        return 4;
    };
    $n = count($results);
    $order = range(0, $n - 1);
    usort($order, static function (int $ia, int $ib) use ($results, $q, $exactRank, $isStrictExactMatch): int {
        $a = $results[$ia];
        $b = $results[$ib];
        $aStrictExact = $isStrictExactMatch($a, $q);
        $bStrictExact = $isStrictExactMatch($b, $q);
        if ($aStrictExact !== $bStrictExact) {
            // Echte Direkt-Treffer immer zuerst, vor Fuzzy/ähnlichen Treffern.
            return $aStrictExact ? -1 : 1;
        }
        $aClosedTicket = (($a['type'] ?? '') === 'ticket') && !empty($a['_ticket_closed_archiv']);
        $bClosedTicket = (($b['type'] ?? '') === 'ticket') && !empty($b['_ticket_closed_archiv']);
        if ($aClosedTicket !== $bClosedTicket) {
            // Geschlossene/archivierte Tickets global immer ans Ende.
            return $aClosedTicket ? 1 : -1;
        }
        $ta = ($a['type'] ?? '') === 'ticket';
        $tb = ($b['type'] ?? '') === 'ticket';
        if ($ta && $tb) {
            $ca = !empty($a['_ticket_closed_archiv']);
            $cb = !empty($b['_ticket_closed_archiv']);
            if ($ca !== $cb) {
                return $ca ? 1 : -1;
            }
            $tia = (int) ($a['_ticket_tier'] ?? 4);
            $tib = (int) ($b['_ticket_tier'] ?? 4);
            if ($tia !== $tib) {
                return $tia <=> $tib;
            }
            $da = (int) ($a['ticket_sort_ts'] ?? 0);
            $db = (int) ($b['ticket_sort_ts'] ?? 0);
            if ($da !== $db) {
                return $db <=> $da;
            }

            return $ia <=> $ib;
        }
        if (($a['type'] ?? '') === 'aufgabe' && ($b['type'] ?? '') === 'aufgabe') {
            $ea = (($a['todo_status'] ?? '') === 'erledigt') ? 1 : 0;
            $eb = (($b['todo_status'] ?? '') === 'erledigt') ? 1 : 0;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }
        }
        $ra = $exactRank($a, $q);
        $rb = $exactRank($b, $q);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return $ia <=> $ib;
    });
    $sorted = [];
    foreach ($order as $i) {
        $sorted[] = $results[$i];
    }
    $results = $sorted;
    $hasStrictExactMatches = false;
    foreach ($results as $row) {
        if ($isStrictExactMatch($row, $q)) {
            $hasStrictExactMatches = true;
            break;
        }
    }
    if ($hasStrictExactMatches) {
        // Wenn es echte Direkt-Treffer gibt, keine reinen Fuzzy-Ausreißer mehr anzeigen.
        $results = array_values(array_filter($results, static function (array $row) use ($isStrictExactMatch, $q): bool {
            return $isStrictExactMatch($row, $q);
        }));
    }
}

// Letzter Notfall-Fallback: wenn die komplexe Suche nichts liefert, fuer Admin/Techniker
// noch einmal robust und wortbasiert in Tickets/Geraeten suchen (Reihenfolge-unabhaengig).
if (empty($results) && ($userRole === 'Admin' || $userRole === 'Techniker') && !empty($searchTerms)) {
    try {
        $fallbackTicketWhere = [];
        $fallbackTicketParams = [];
        foreach ($searchTerms as $idx => $term) {
            $p = ':ft_term_' . $idx;
            $fallbackTicketWhere[] = "(t.ticket_nummer LIKE $p OR t.titel LIKE $p OR t.beschreibung LIKE $p)";
            $fallbackTicketParams[$p] = '%' . $term . '%';
        }
        if (!empty($fallbackTicketWhere)) {
            $sql = "SELECT t.id, t.ticket_nummer, t.titel, t.status
                    FROM tickets t
                    WHERE " . implode(' AND ', $fallbackTicketWhere) . "
                    ORDER BY t.id DESC
                    LIMIT :ft_limit";
            $stmt = $pdo->prepare($sql);
            foreach ($fallbackTicketParams as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmt->bindValue(':ft_limit', (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $results[] = [
                    'type' => 'ticket',
                    'type_label' => 'Tickets',
                    'id' => (int) $r['id'],
                    'title' => (string) ($r['titel'] ?? ''),
                    'subtitle' => '',
                    'ticket_subtitle_line1' => '',
                    'ticket_subtitle_line2' => '',
                    'ticket_anforderer' => '',
                    'ticket_nummer' => (string) ($r['ticket_nummer'] ?? ''),
                    'ticket_status' => (string) ($r['status'] ?? ''),
                    'url' => $baseUrl . 'tickets/view.php?id=' . (int) $r['id'],
                ];
            }
        }
    } catch (PDOException $e) {
        error_log('search.php final ticket fallback: ' . $e->getMessage());
    }

    if (count($results) < $limit) {
        try {
            $fallbackDeviceWhere = [];
            $fallbackDeviceParams = [];
            foreach ($searchTerms as $idx => $term) {
                $p = ':fd_term_' . $idx;
                $fallbackDeviceWhere[] = "(d.name LIKE $p OR d.hersteller LIKE $p OR d.modell LIKE $p OR d.seriennummer LIKE $p OR d.beschreibung LIKE $p)";
                $fallbackDeviceParams[$p] = '%' . $term . '%';
            }
            if (!empty($fallbackDeviceWhere)) {
                $remaining = max(1, $limit - count($results));
                $sql = "SELECT d.id, d.name, d.hersteller, d.modell, d.seriennummer
                        FROM devices d
                        WHERE " . implode(' AND ', $fallbackDeviceWhere) . "
                        ORDER BY d.id DESC
                        LIMIT :fd_limit";
                $stmt = $pdo->prepare($sql);
                foreach ($fallbackDeviceParams as $k => $v) {
                    $stmt->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stmt->bindValue(':fd_limit', (int) $remaining, PDO::PARAM_INT);
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $sub = trim((string) (($r['hersteller'] ?? '') . ' ' . ($r['modell'] ?? '')));
                    if (!empty($r['seriennummer'])) {
                        $sub .= ($sub !== '' ? ' · ' : '') . (string) $r['seriennummer'];
                    }
                    $results[] = [
                        'type' => 'geraet',
                        'type_label' => 'Gerät',
                        'id' => (int) $r['id'],
                        'title' => (string) ($r['name'] ?? ''),
                        'subtitle' => $sub,
                        'url' => $baseUrl . 'devices/detail.php?id=' . (int) $r['id'],
                    ];
                }
            }
        } catch (PDOException $e) {
            error_log('search.php final device fallback: ' . $e->getMessage());
        }
    }
}

foreach ($results as &$r) {
    unset($r['ticket_sort_ts'], $r['_ticket_tier'], $r['_ticket_closed_archiv']);
}
unset($r);

$suggestions = [];
if (empty($results)) {
    $suggestions = $buildNoResultSuggestions();
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'suggestions' => $suggestions,
]);
