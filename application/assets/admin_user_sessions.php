<?php
/**
 * Admin: aktive Sessions eines Benutzers laden (wie settings/index.php).
 */
declare(strict_types=1);

function admin_user_parse_session_ua(array $sess): array
{
    $ua = (string) ($sess['user_agent'] ?? '');
    $browser = !empty($sess['browser_name']) ? (string) $sess['browser_name'] : 'Unbekannt';
    $os = !empty($sess['os_name']) ? (string) $sess['os_name'] : 'Unbekannt';
    if ($browser === 'Unbekannt' && preg_match('/(Chrome|Firefox|Safari|Edge|Opera|MSIE)[\/\s](\d+\.\d+)/i', $ua, $m)) {
        $browser = $m[1];
    }
    if ($os === 'Unbekannt' && preg_match('/(Windows NT|Windows|Mac OS X|Linux|iPhone|iPad|Android)/i', $ua, $m)) {
        $os = $m[1];
        if ($os === 'Windows NT') {
            $os = 'Windows';
        }
        if ($os === 'Mac OS X') {
            $os = 'macOS';
        }
    }
    $deviceType = !empty($sess['device_type']) ? (string) $sess['device_type'] : (preg_match('/(Android|iPhone|iPad|Mobile|Tablet)/i', $ua) ? 'mobile' : 'desktop');
    return ['browser' => $browser, 'os' => $os, 'device_type' => $deviceType];
}

/** @return list<array<string, mixed>> */
function admin_user_load_sessions(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT id, session_id, ip_address, user_agent, last_activity, created_at,
                   browser_name, os_name, device_type, login_method, remember_me_used
            FROM user_sessions
            WHERE user_id = ?
            ORDER BY last_activity DESC
        ');
        $stmt->execute([$userId]);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $deduped = [];
    foreach ($raw as $sess) {
        $parsed = admin_user_parse_session_ua($sess);
        $sess['browser'] = $parsed['browser'];
        $sess['os'] = $parsed['os'];
        $sess['device_type'] = $parsed['device_type'];

        $normalizedUa = strtolower(trim((string) ($sess['user_agent'] ?? '')));
        $fp = $normalizedUa !== ''
            ? 'ua:' . md5($normalizedUa)
            : 'fallback:' . md5(strtolower(trim(($sess['os'] ?? '') . '|' . ($sess['browser'] ?? '') . '|' . ($sess['ip_address'] ?? ''))));

        $existing = $deduped[$fp] ?? null;
        if ($existing === null) {
            $deduped[$fp] = $sess;
            continue;
        }
        $existingTs = strtotime((string) ($existing['last_activity'] ?? '')) ?: 0;
        $newTs = strtotime((string) ($sess['last_activity'] ?? '')) ?: 0;
        if ($newTs > $existingTs) {
            $deduped[$fp] = $sess;
        }
    }

    return array_values($deduped);
}

function admin_user_remember_me_active(PDO $pdo, int $userId): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM remember_me_tokens WHERE user_id = ? AND expires_at > NOW()');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function admin_user_logout_everywhere(PDO $pdo, int $targetUserId): void
{
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO user_settings (user_id, setting_key, setting_value)
        VALUES (?, 'sessions_valid_after', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ")->execute([$targetUserId, $now]);

    $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$targetUserId]);
    $pdo->prepare('DELETE FROM remember_me_tokens WHERE user_id = ?')->execute([$targetUserId]);
}

function admin_user_logout_device(PDO $pdo, int $targetUserId, int $rowId, string $sessionId): bool
{
    if ($rowId > 0) {
        $check = $pdo->prepare('SELECT session_id FROM user_sessions WHERE id = ? AND user_id = ? LIMIT 1');
        $check->execute([$rowId, $targetUserId]);
    } else {
        $check = $pdo->prepare('SELECT session_id FROM user_sessions WHERE session_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$sessionId, $targetUserId]);
    }
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $sid = (string) ($row['session_id'] ?? '');
    if ($sid === '') {
        return false;
    }
    if (function_exists('addRevokedSessionId')) {
        addRevokedSessionId($targetUserId, $sid);
    }
    $del = $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ? AND session_id = ? LIMIT 1');
    $del->execute([$targetUserId, $sid]);
    return $del->rowCount() > 0;
}
