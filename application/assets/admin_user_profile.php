<?php
/**
 * Admin: Benutzerprofil laden, user_settings auswerten (geteilt mit Profil-Übersicht).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/companies/helper/encryption.php';

/**
 * @param mixed $decoded
 * @param array<string, mixed> $out
 */
function admin_user_profile_flatten(mixed $decoded, string $prefix, array &$out, callable $pathIsSensitive): void
{
    if (!is_array($decoded)) {
        $out[$prefix] = $decoded;
        return;
    }
    foreach ($decoded as $k => $v) {
        $segment = is_string($k) || is_int($k) ? (string) $k : '';
        $path = $prefix === '' ? $segment : $prefix . '.' . $segment;
        if ($pathIsSensitive($path)) {
            $out[$path] = '*** (verborgen)';
            continue;
        }
        if (is_array($v)) {
            admin_user_profile_flatten($v, $path, $out, $pathIsSensitive);
        } else {
            $out[$path] = $v;
        }
    }
}

function admin_user_profile_path_is_sensitive(): callable
{
    return static function (string $path): bool {
        $lower = strtolower($path);
        if (str_contains($lower, 'password') || str_contains($lower, 'passwort')) {
            return true;
        }
        if (str_contains($lower, 'secret')) {
            return true;
        }
        if (str_contains($lower, 'token')) {
            return true;
        }
        if (str_contains($lower, 'api_key') || str_contains($lower, 'apikey')) {
            return true;
        }
        return false;
    };
}

/** @return list<array{path: string, value: string}> */
function admin_user_profile_expand_row(string $settingKey, ?string $rawValue, ?callable $pathIsSensitive = null): array
{
    $pathIsSensitive ??= admin_user_profile_path_is_sensitive();

    if ($settingKey === '2fa_secret') {
        return [['path' => $settingKey, 'value' => '*** (Zwei-Faktor-Geheimnis, nicht angezeigt)']];
    }

    $trimmed = trim((string) $rawValue);
    if ($trimmed === '') {
        return [['path' => $settingKey, 'value' => '(leer)']];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $display = $trimmed;
        if ($pathIsSensitive($settingKey)) {
            $display = '*** (verborgen)';
        }
        return [['path' => $settingKey, 'value' => $display]];
    }

    $flat = [];
    admin_user_profile_flatten($decoded, $settingKey, $flat, $pathIsSensitive);
    ksort($flat, SORT_NATURAL);
    if ($flat === []) {
        return [['path' => $settingKey, 'value' => '(leeres JSON-Objekt oder -Array)']];
    }
    $lines = [];
    foreach ($flat as $path => $val) {
        if (is_bool($val)) {
            $lines[] = ['path' => $path, 'value' => $val ? 'true' : 'false'];
        } elseif ($val === null) {
            $lines[] = ['path' => $path, 'value' => 'null'];
        } elseif (is_scalar($val)) {
            $lines[] = ['path' => $path, 'value' => (string) $val];
        } else {
            $lines[] = ['path' => $path, 'value' => json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
    }
    return $lines;
}

/** Bekannte setting_keys mit deutscher Beschreibung (für Auswertung). */
function admin_user_profile_known_setting_labels(): array
{
    return [
        'easy_mode' => 'Einfache Ansicht (Easy Mode)',
        'mobile_start_page' => 'Mobile Startseite',
        'mobile_start_enabled' => 'Mobile Start aktiviert',
        '2fa_enabled' => 'Zwei-Faktor-Authentifizierung aktiv',
        '2fa_secret' => 'Zwei-Faktor-Geheimnis',
        'dismissed_cards' => 'Verworfene Dashboard-Cards',
        'tickets_assigned_card_dismissed_count' => 'Zugewiesene-Tickets-Card (Dismiss-Zähler)',
        'sidebar_expanded' => 'Sidebar ausgeklappt',
        'sidebar_expand_on_hover' => 'Sidebar bei Hover erweitern',
        'sidebar_tickets_count' => 'Ticket-Zähler in Sidebar',
        'sidebar_todos_count' => 'Todo-Zähler in Sidebar',
        'sidebar_tickets_filters' => 'Ticket-Filter in Sidebar',
        'toast_display' => 'Toast-Benachrichtigungen',
        'sounds_enabled' => 'Sounds aktiviert',
        'email_enabled' => 'E-Mail-Benachrichtigungen',
        'marketing_emails_enabled' => 'Marketing-E-Mails',
        'chat_display_name' => 'Ticket-Chat: Anzeigename',
        'ticket_search_scope' => 'Ticket-Suchbereich',
        'order_search_scope' => 'Bestellungs-Suchbereich',
        'global_search_scope' => 'Globale Suche: Bereiche',
        'company_favorites' => 'Firmen-Favoriten',
        'notification_settings' => 'Benachrichtigungs-Einstellungen',
        'notification_hide_own' => 'Eigene Benachrichtigungen ausblenden',
        'system_notifications_enabled' => 'System-Benachrichtigungen',
        'push_notifications_enabled' => 'Push-Benachrichtigungen',
        'service_pinned_tickets' => 'Angeheftete Service-Tickets',
        'speed_dial_items' => 'Speed-Dial-Einträge',
        'speed_dial_visible' => 'Speed-Dial sichtbar',
        'ticket_tasks_require_folder' => 'Ticket-Aufgaben: Ordner Pflicht',
        'project_tasks_require_folder' => 'Projekt-Aufgaben: Ordner Pflicht',
        'calendar_export_sources' => 'Kalender-Export-Quellen',
        'calendar_export_sources_caldav' => 'CalDAV-Export-Quellen',
        'weekly_hours' => 'Wochenstunden (Zeiterfassung)',
        'vacation_days' => 'Urlaubstage',
        'work_start_time' => 'Arbeitsbeginn',
        'work_end_time' => 'Arbeitsende',
        'employment_start_date' => 'Beschäftigungsbeginn',
        'sip_settings' => 'SIP-Telefonie',
        'sessions_valid_after' => 'Sitzungen gültig ab',
        'sessions_revoked_ids' => 'Widerrufene Sitzungen',
        'daten_bestaetigt' => 'Daten bestätigt',
    ];
}

/**
 * @return array{
 *   user: array<string, mixed>,
 *   settings_rows: list<array>,
 *   settings_map: array<string, string>,
 *   dismissed_card_ids: list<string>,
 *   dashboard_cards: list<array>,
 *   settings_evaluation: list<array{key: string, label: string, is_set: bool, preview: string}>,
 *   stats: array{settings_count: int, known_set: int, known_total: int}
 * }
 */
function admin_user_profile_load(PDO $pdo, int $targetUserId): array
{
    $empty = [
        'user' => [],
        'settings_rows' => [],
        'settings_map' => [],
        'dismissed_card_ids' => [],
        'dashboard_cards' => [],
        'settings_evaluation' => [],
        'stats' => ['settings_count' => 0, 'known_set' => 0, 'known_total' => 0],
    ];

    $stmt = $pdo->prepare('
        SELECT u.*, c.name AS company_name, c.logo AS company_logo
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return $empty;
    }

    if (!empty($user['company_name'])) {
        $user['company_name'] = decrypt_from_db($user['company_name']);
    }

    if (array_key_exists('calendar_token', $user)) {
        $user['calendar_token'] = !empty($user['calendar_token'])
            ? '*** (vorhanden)'
            : null;
    }
    unset($user['passwort']);

    $sStmt = $pdo->prepare('
        SELECT id, setting_key, setting_value, erstellt_datum, geaendert_datum
        FROM user_settings
        WHERE user_id = ?
        ORDER BY setting_key ASC, id ASC
    ');
    $sStmt->execute([$targetUserId]);
    $settingsRows = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    $settingsMap = [];
    foreach ($settingsRows as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key !== '') {
            $settingsMap[$key] = (string) ($row['setting_value'] ?? '');
        }
    }

    $dismissedCardIds = [];
    if (!empty($settingsMap['dismissed_cards'])) {
        $decoded = json_decode($settingsMap['dismissed_cards'], true);
        if (is_array($decoded)) {
            $dismissedCardIds = array_map('strval', $decoded);
        }
    }

    $companyId = isset($user['company_id']) && $user['company_id'] !== null ? (int) $user['company_id'] : null;
    $dashboardCards = [];
    try {
        if ($companyId) {
            $dcStmt = $pdo->prepare('
                SELECT id, titel, nachricht, typ, aktiv, company_id, sort_order
                FROM dashboard_cards
                WHERE aktiv = 1 AND (company_id IS NULL OR company_id = ?)
                ORDER BY sort_order ASC, id ASC
            ');
            $dcStmt->execute([$companyId]);
        } else {
            $dcStmt = $pdo->prepare('
                SELECT id, titel, nachricht, typ, aktiv, company_id, sort_order
                FROM dashboard_cards
                WHERE aktiv = 1 AND company_id IS NULL
                ORDER BY sort_order ASC, id ASC
            ');
            $dcStmt->execute();
        }
        $dbCards = $dcStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dbCards as $card) {
            $cardId = (string) ($card['id'] ?? '');
            $dashboardCards[] = [
                'id' => $cardId,
                'titel' => $card['titel'] ?? '',
                'typ' => $card['typ'] ?? '',
                'company_id' => $card['company_id'],
                'dismissed' => in_array($cardId, $dismissedCardIds, true),
                'source' => 'dashboard_cards',
            ];
        }
    } catch (PDOException $e) {
        error_log('admin_user_profile_load dashboard_cards: ' . $e->getMessage());
    }

    $systemCardLabels = [
        'system_tickets_nicht_abgerechnet' => 'System: Tickets nicht abgerechnet',
        'system_tickets_closed_no_time' => 'System: Geschlossen ohne Zeit',
        'system_tickets_assigned' => 'System: Zugewiesene Tickets',
        'system_order_arrived' => 'System: Bestellung angekommen',
        'system_order_at_customer' => 'System: Bestellung beim Kunden',
        'system_order_in_warehouse' => 'System: Bestellung im Lager',
        'system_wartung_zahlung' => 'System: Wartung Zahlung',
        'system_service_pinned' => 'System: Angeheftetes Ticket',
    ];
    foreach ($dismissedCardIds as $cid) {
        if (isset($systemCardLabels[$cid])) {
            $dashboardCards[] = [
                'id' => $cid,
                'titel' => $systemCardLabels[$cid],
                'typ' => 'system',
                'company_id' => null,
                'dismissed' => true,
                'source' => 'system',
            ];
        } elseif (str_starts_with($cid, 'system_service_pinned_')) {
            $dashboardCards[] = [
                'id' => $cid,
                'titel' => 'System: Angeheftetes Ticket #' . substr($cid, strlen('system_service_pinned_')),
                'typ' => 'system',
                'company_id' => null,
                'dismissed' => true,
                'source' => 'system',
            ];
        }
    }

    $knownLabels = admin_user_profile_known_setting_labels();
    $allKeys = array_unique(array_merge(array_keys($knownLabels), array_keys($settingsMap)));
    sort($allKeys, SORT_NATURAL);

    $pathIsSensitive = admin_user_profile_path_is_sensitive();
    $evaluation = [];
    $knownSet = 0;
    foreach ($allKeys as $key) {
        $isSet = isset($settingsMap[$key]) && trim($settingsMap[$key]) !== '';
        if ($isSet && isset($knownLabels[$key])) {
            $knownSet++;
        }
        $preview = '(nicht gesetzt)';
        if ($isSet) {
            $lines = admin_user_profile_expand_row($key, $settingsMap[$key], $pathIsSensitive);
            $preview = $lines[0]['value'] ?? '(gesetzt)';
            if (count($lines) > 1) {
                $preview .= ' (+' . (count($lines) - 1) . ' Unterwerte)';
            }
            if (strlen($preview) > 120) {
                $preview = substr($preview, 0, 117) . '…';
            }
        }
        $evaluation[] = [
            'key' => $key,
            'label' => $knownLabels[$key] ?? $key,
            'is_set' => $isSet,
            'preview' => $preview,
        ];
    }

    $knownTotal = count($knownLabels);

    return [
        'user' => $user,
        'settings_rows' => $settingsRows,
        'settings_map' => $settingsMap,
        'dismissed_card_ids' => $dismissedCardIds,
        'dashboard_cards' => $dashboardCards,
        'settings_evaluation' => $evaluation,
        'stats' => [
            'settings_count' => count($settingsRows),
            'known_set' => $knownSet,
            'known_total' => $knownTotal,
        ],
    ];
}

function admin_require_admin_role(PDO $pdo, int $sessionUserId): ?string
{
    $stmt = $pdo->prepare('SELECT rolle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($row['rolle'] ?? '') !== 'Admin') {
        return null;
    }
    return 'Admin';
}
