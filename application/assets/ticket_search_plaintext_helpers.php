<?php
/**
 * Hilfen für die Ticket-Suche: Firmen- und Kundendaten sind oft verschlüsselt (ENC:…),
 * daher liefert SQL-LIKE auf den Spalten keine Treffer. Stattdessen: IDs per Klartext-Match nach Entschlüsselung.
 */
require_once dirname(__DIR__) . '/companies/helper/encryption.php';

if (!function_exists('ticket_search_normalize_ws')) {
    /** Einheitliche Leerzeichen (Unicode) und trim — z. B. "1 zu 1" vs. mehrere Spaces; unsichtbare Zeichen entfernen */
    function ticket_search_normalize_ws(string $s): string
    {
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }
}

if (!function_exists('ticket_search_plaintext_fuzzy_matches_normalized')) {
    /**
     * Reduzierte Tippfehler-Logik analog zur globalen Suche:
     * - ab 4 Zeichen
     * - 1 Fehler begrenzt
     * - 2 Fehler nur bei längeren Begriffen
     * - 3 Fehler deaktiviert (zu viele Zufallstreffer)
     */
    function ticket_search_plaintext_fuzzy_matches_normalized(string $hayNorm, string $qForFuzzy): bool
    {
        $len = mb_strlen($qForFuzzy, 'UTF-8');
        if ($len < 4) {
            return false;
        }
        if (strpos($qForFuzzy, '%') !== false || strpos($qForFuzzy, '_') !== false) {
            return false;
        }
        $hayLen = mb_strlen($hayNorm, 'UTF-8');

        $match1 = static function (string $before, string $after) use ($hayNorm, $hayLen): bool {
            $bl = mb_strlen($before, 'UTF-8');
            $al = mb_strlen($after, 'UTF-8');
            $total = $bl + 1 + $al;
            if ($hayLen < $total) {
                return false;
            }
            $maxStart = $hayLen - $total;
            for ($j = 0; $j <= $maxStart; $j++) {
                $sub = mb_substr($hayNorm, $j, $total, 'UTF-8');
                if (mb_substr($sub, 0, $bl, 'UTF-8') !== $before) {
                    continue;
                }
                if (mb_substr($sub, $bl + 1, $al, 'UTF-8') !== $after) {
                    continue;
                }

                return true;
            }

            return false;
        };

        $max1 = min(12, $len);
        for ($i = 0; $i < $max1; $i++) {
            $before = mb_substr($qForFuzzy, 0, $i, 'UTF-8');
            $after = mb_substr($qForFuzzy, $i + 1, $len - $i - 1, 'UTF-8');
            if ($match1($before, $after)) {
                return true;
            }
        }

        $match2 = static function (string $before, string $mid, string $after) use ($hayNorm, $hayLen): bool {
            $bl = mb_strlen($before, 'UTF-8');
            $ml = mb_strlen($mid, 'UTF-8');
            $al = mb_strlen($after, 'UTF-8');
            $total = $bl + 1 + $ml + 1 + $al;
            if ($hayLen < $total) {
                return false;
            }
            $maxStart = $hayLen - $total;
            for ($j = 0; $j <= $maxStart; $j++) {
                $sub = mb_substr($hayNorm, $j, $total, 'UTF-8');
                if (mb_substr($sub, 0, $bl, 'UTF-8') !== $before) {
                    continue;
                }
                if (mb_substr($sub, $bl + 1, $ml, 'UTF-8') !== $mid) {
                    continue;
                }
                if (mb_substr($sub, $bl + 1 + $ml + 1, $al, 'UTF-8') !== $after) {
                    continue;
                }

                return true;
            }

            return false;
        };

        $numPairs = 0;
        if ($len >= 6) {
            $numPairs = (int) min(pow(2, min(3, max(0, (int) (($len - 2) / 2)))), $len * ($len - 1) / 2, 12);
        }
        $count = 0;
        for ($i = 0; $i < $len - 1 && $count < $numPairs; $i++) {
            for ($j = $i + 1; $j < $len && $count < $numPairs; $j++, $count++) {
                $before = mb_substr($qForFuzzy, 0, $i, 'UTF-8');
                $mid = mb_substr($qForFuzzy, $i + 1, $j - $i - 1, 'UTF-8');
                $after = mb_substr($qForFuzzy, $j + 1, $len - $j - 1, 'UTF-8');
                if ($match2($before, $mid, $after)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('ticket_search_plaintext_matches')) {
    /**
     * Treffer im Klartext: normalisierte Leerzeichen + optional gleicher Text ohne Leerzeichen
     * (Suchbegriff "1zu1" findet "1 zu 1"). Zusätzlich dieselbe Tippfehlertoleranz wie in der globalen Suche
     * (search.php), damit verschlüsselte Firmen-/Kundenfelder per Klartext-Scan gefunden werden.
     */
    function ticket_search_plaintext_matches(string $hay, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }
        $needle = ticket_search_normalize_ws(mb_strtolower($needle, 'UTF-8'));
        if ($needle === '') {
            return false;
        }
        $hay = ticket_search_normalize_ws(mb_strtolower($hay, 'UTF-8'));
        if ($hay === '') {
            return false;
        }
        if (mb_strpos($hay, $needle) !== false) {
            return true;
        }
        $hayNo = preg_replace('/\s+/u', '', $hay);
        $needNo = preg_replace('/\s+/u', '', $needle);
        // Mindestens 2 Zeichen ohne Leerzeichen, sonst zu viele Zufallstreffer (Einzelzeichen bleibt über mb_strpos oben)
        if (mb_strlen($needNo, 'UTF-8') >= 2 && mb_strpos($hayNo, $needNo) !== false) {
            return true;
        }

        // Mehrere Wörter in beliebiger Reihenfolge (z. B. "Mustermann Max" findet "Max Mustermann")
        $tokens = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);
        $significant = [];
        foreach ($tokens as $t) {
            if (mb_strlen($t, 'UTF-8') >= 2) {
                $significant[] = $t;
            }
        }
        if (count($significant) >= 2) {
            $allInHay = true;
            foreach ($significant as $t) {
                if (mb_strpos($hay, $t) === false) {
                    $allInHay = false;
                    break;
                }
            }
            if ($allInHay) {
                return true;
            }
            $allInHayNo = true;
            foreach ($significant as $t) {
                $tNo = preg_replace('/\s+/u', '', $t);
                if ($tNo === '' || mb_strpos($hayNo, $tNo) === false) {
                    $allInHayNo = false;
                    break;
                }
            }
            if ($allInHayNo) {
                return true;
            }
        }

        $qForFuzzy = mb_strtolower(str_replace(' ', '', $needle), 'UTF-8');
        if (mb_strlen($qForFuzzy, 'UTF-8') >= 4 && ticket_search_plaintext_fuzzy_matches_normalized($hayNo, $qForFuzzy)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('ticket_search_company_ids_matching_plaintext')) {
    /**
     * Firmen-IDs, deren entschlüsselte Felder den Suchbegriff enthalten.
     */
    function ticket_search_company_ids_matching_plaintext(PDO $pdo, string $searchTerm, int $maxIds = 500): array
    {
        if ($searchTerm === '') {
            return [];
        }
        $maxIds = max(1, min(2000, $maxIds));
        try {
            $stmt = $pdo->query(
                'SELECT c.id, c.name, c.kundennummer, c.adresse, c.plz, c.ort, c.email, c.telefonnummer, '
                . 'c.ansprechpartner_manuell_name, c.ansprechpartner_manuell_email, c.ansprechpartner_manuell_telefon, '
                . 'c.ansprechpartner_manuell_notiz, c.ansprechpartner_manuell, '
                . 'u_ap.vorname AS ap_vorname, u_ap.nachname AS ap_nachname, u_ap.email AS ap_user_email, u_ap.telefonnummer AS ap_user_telefon '
                . 'FROM companies c '
                . 'LEFT JOIN users u_ap ON c.ansprechpartner_user_id = u_ap.id'
            );
        } catch (PDOException $e) {
            return [];
        }
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $parts = [];
            foreach (['name', 'kundennummer', 'adresse', 'plz', 'ort', 'email', 'telefonnummer', 'ansprechpartner_manuell_name', 'ansprechpartner_manuell_email', 'ansprechpartner_manuell_telefon', 'ansprechpartner_manuell_notiz', 'ansprechpartner_manuell'] as $col) {
                $v = decrypt_from_db($row[$col] ?? null);
                $v = is_string($v) ? $v : '';
                if ($v !== '') {
                    $parts[] = $v;
                }
            }
            $apName = trim((string) ($row['ap_vorname'] ?? '') . ' ' . (string) ($row['ap_nachname'] ?? ''));
            if ($apName !== '') {
                $parts[] = $apName;
            }
            foreach (['ap_user_email', 'ap_user_telefon'] as $apc) {
                $pv = isset($row[$apc]) && is_string($row[$apc]) ? trim($row[$apc]) : '';
                if ($pv !== '') {
                    $parts[] = $pv;
                }
            }
            $matched = false;
            foreach ($parts as $p) {
                if (ticket_search_plaintext_matches($p, $searchTerm)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched && $parts !== []) {
                $matched = ticket_search_plaintext_matches(implode(' ', $parts), $searchTerm);
            }
            if ($matched) {
                $ids[] = (int) $row['id'];
                if (count($ids) >= $maxIds) {
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('ticket_search_customer_ids_matching_plaintext')) {
    /**
     * Kunden-IDs (inkl. Kundennummer), analog.
     */
    function ticket_search_customer_ids_matching_plaintext(PDO $pdo, string $searchTerm, int $maxIds = 500): array
    {
        if ($searchTerm === '') {
            return [];
        }
        $maxIds = max(1, min(2000, $maxIds));
        try {
            $stmt = $pdo->query(
                'SELECT cust.id, cust.name, cust.kundennummer, cust.adresse, cust.plz, cust.ort, cust.email, cust.telefon, '
                . 'cust.ansprechpartner_manuell_name, cust.ansprechpartner_manuell_email, cust.ansprechpartner_manuell_telefon, '
                . 'cust.ansprechpartner_manuell_notiz, cust.ansprechpartner_manuell, '
                . 'u_ap.vorname AS ap_vorname, u_ap.nachname AS ap_nachname, u_ap.email AS ap_user_email, u_ap.telefonnummer AS ap_user_telefon '
                . 'FROM customers cust '
                . 'LEFT JOIN users u_ap ON cust.ansprechpartner_user_id = u_ap.id'
            );
        } catch (PDOException $e) {
            return [];
        }
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $parts = [];
            foreach (['name', 'kundennummer', 'adresse', 'plz', 'ort', 'email', 'telefon', 'ansprechpartner_manuell_name', 'ansprechpartner_manuell_email', 'ansprechpartner_manuell_telefon', 'ansprechpartner_manuell_notiz', 'ansprechpartner_manuell'] as $col) {
                $v = decrypt_from_db($row[$col] ?? null);
                $v = is_string($v) ? $v : '';
                if ($v !== '') {
                    $parts[] = $v;
                }
            }
            $apName = trim((string) ($row['ap_vorname'] ?? '') . ' ' . (string) ($row['ap_nachname'] ?? ''));
            if ($apName !== '') {
                $parts[] = $apName;
            }
            foreach (['ap_user_email', 'ap_user_telefon'] as $apc) {
                $pv = isset($row[$apc]) && is_string($row[$apc]) ? trim($row[$apc]) : '';
                if ($pv !== '') {
                    $parts[] = $pv;
                }
            }
            $matched = false;
            foreach ($parts as $p) {
                if (ticket_search_plaintext_matches($p, $searchTerm)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched && $parts !== []) {
                $matched = ticket_search_plaintext_matches(implode(' ', $parts), $searchTerm);
            }
            if ($matched) {
                $ids[] = (int) $row['id'];
                if (count($ids) >= $maxIds) {
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('ticket_search_ticket_ids_from_comments_like')) {
    /** Ticket-IDs mit Kommentar-Treffer (eine Abfrage statt EXISTS pro Zeile; begrenzt für Performance). */
    function ticket_search_ticket_ids_from_comments_like(PDO $pdo, string $searchLike, int $limit = 3000): array
    {
        $limit = max(1, min(8000, $limit));
        try {
            $sql = 'SELECT DISTINCT ticket_id FROM ticket_comments WHERE kommentar LIKE :s LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':s', $searchLike, PDO::PARAM_STR);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

            return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        } catch (Throwable $e) {
            error_log('ticket_search_ticket_ids_from_comments_like: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('ticket_search_ticket_ids_from_attachments_like')) {
    function ticket_search_ticket_ids_from_attachments_like(PDO $pdo, string $searchLike, int $limit = 3000): array
    {
        $limit = max(1, min(8000, $limit));
        try {
            $sql = 'SELECT DISTINCT ticket_id FROM ticket_attachments WHERE dateiname LIKE :s LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':s', $searchLike, PDO::PARAM_STR);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

            return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        } catch (Throwable $e) {
            error_log('ticket_search_ticket_ids_from_attachments_like: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('ticket_search_ticket_ids_from_tickets_beschreibung_like')) {
    function ticket_search_ticket_ids_from_tickets_beschreibung_like(PDO $pdo, string $searchLike, int $limit = 3000): array
    {
        $limit = max(1, min(8000, $limit));
        try {
            $sql = 'SELECT id FROM tickets WHERE beschreibung LIKE :s LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':s', $searchLike, PDO::PARAM_STR);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

            return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        } catch (Throwable $e) {
            error_log('ticket_search_ticket_ids_from_tickets_beschreibung_like: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('ticket_search_ticket_ids_from_devices_standort_like')) {
    /** Geräte-Standort/Beschreibung (devices.beschreibung), über Join. */
    function ticket_search_ticket_ids_from_devices_standort_like(PDO $pdo, string $searchLike, int $limit = 3000): array
    {
        $limit = max(1, min(8000, $limit));
        try {
            $sql = 'SELECT DISTINCT t.id FROM tickets t INNER JOIN devices d ON t.device_id = d.id WHERE d.beschreibung LIKE :s LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':s', $searchLike, PDO::PARAM_STR);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

            return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        } catch (Throwable $e) {
            error_log('ticket_search_ticket_ids_from_devices_standort_like: ' . $e->getMessage());

            return [];
        }
    }
}
