<?php
/**
 * Web-Push (VAPID) – Smartphone/PWA-Benachrichtigungen.
 *
 * Reihenfolge: 1) Konstanten in assets/config.php (WEBPUSH_*), 2) system_settings (Admin-Oberfläche).
 */

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * @return bool True, wenn alle drei Werte in config.php gesetzt sind (haben Vorrang vor der Datenbank).
 */
function webpush_is_config_file_active(): bool {
    if (!defined('WEBPUSH_VAPID_PUBLIC_KEY') || !defined('WEBPUSH_VAPID_PRIVATE_KEY') || !defined('WEBPUSH_VAPID_SUBJECT')) {
        return false;
    }
    return WEBPUSH_VAPID_PUBLIC_KEY !== '' && WEBPUSH_VAPID_PRIVATE_KEY !== '' && WEBPUSH_VAPID_SUBJECT !== '';
}

/**
 * Lädt VAPID aus system_settings (Schlüssel webpush_vapid_*).
 *
 * @return array{publicKey:string,privateKey:string,subject:string,source:string}|null
 */
function webpush_load_vapid_from_database(): ?array {
    global $pdo;
    if (!isset($pdo)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value FROM system_settings
            WHERE setting_key IN ('webpush_vapid_public_key', 'webpush_vapid_private_key', 'webpush_vapid_subject')
        ");
        $stmt->execute();
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $pub = trim($map['webpush_vapid_public_key'] ?? '');
        $priv = trim($map['webpush_vapid_private_key'] ?? '');
        $sub = trim($map['webpush_vapid_subject'] ?? '');
        if ($pub === '' || $priv === '' || $sub === '') {
            return null;
        }
        return [
            'publicKey'  => $pub,
            'privateKey' => $priv,
            'subject'    => $sub,
            'source'     => 'database',
        ];
    } catch (PDOException $e) {
        error_log('webpush_load_vapid_from_database: ' . $e->getMessage());
        return null;
    }
}

/**
 * Effektive VAPID-Daten (config.php schlägt Datenbank).
 *
 * @return array{publicKey:string,privateKey:string,subject:string,source:string}|null
 */
function webpush_get_vapid_credentials(): ?array {
    static $cached = null;
    static $done = false;
    if ($done) {
        return $cached;
    }
    $done = true;
    if (webpush_is_config_file_active()) {
        $cached = [
            'publicKey'  => WEBPUSH_VAPID_PUBLIC_KEY,
            'privateKey' => WEBPUSH_VAPID_PRIVATE_KEY,
            'subject'    => WEBPUSH_VAPID_SUBJECT,
            'source'     => 'config',
        ];
        return $cached;
    }
    $cached = webpush_load_vapid_from_database();
    return $cached;
}

/**
 * @return bool
 */
function webpush_is_configured(): bool {
    return webpush_get_vapid_credentials() !== null;
}

/**
 * @return void
 */
function webpush_ensure_table(): void {
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                endpoint TEXT NOT NULL,
                endpoint_sha256 CHAR(64) NOT NULL,
                p256dh VARCHAR(255) NOT NULL,
                auth_secret VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(32) NOT NULL DEFAULT 'aesgcm',
                user_agent VARCHAR(512) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_push_endpoint_sha256 (endpoint_sha256),
                KEY idx_push_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log('webpush_ensure_table: ' . $e->getMessage());
    }
}

/**
 * Absolute URL für Klick aus der Push-Mitteilung (funktioniert auch bei Cron, wenn SITE_URL gesetzt ist).
 */
function webpush_absolute_url(?string $relativeLink): string {
    $path = $relativeLink !== null && $relativeLink !== '' ? ltrim($relativeLink, '/') : '';
    if (defined('SITE_URL') && is_string(SITE_URL) && SITE_URL !== '') {
        return rtrim(SITE_URL, '/') . '/' . $path;
    }
    if (php_sapi_name() !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base = defined('BASE_URL') ? (string) BASE_URL : '/';
        $base = $base === '' ? '/' : $base;
        return $scheme . '://' . $host . rtrim($base, '/') . '/' . $path;
    }
    return '/' . $path;
}

/**
 * Sendet eine Push-Mitteilung an alle registrierten Endpunkte des Benutzers.
 *
 * @param int $userId
 * @param string $title
 * @param string $body
 * @param string|null $relativeLink z. B. tickets/?id=1
 */
function webpush_send_for_user(int $userId, string $title, string $body, ?string $relativeLink = null): void {
    if (!webpush_is_configured()) {
        return;
    }
    global $pdo;
    if (!isset($pdo)) {
        return;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        return;
    }
    require_once $autoload;

    webpush_ensure_table();

    $body = mb_substr($body, 0, 2000, 'UTF-8');

    $iconPath = 'assets/images/Serohub_Icon.png';
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute(['branding_logo']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty(trim($row['setting_value'] ?? ''))) {
                $iconPath = ltrim(trim($row['setting_value']), '/');
            }
        } catch (Throwable $e) { /* Defaults */
        }
    }
    $iconUrl = webpush_absolute_url($iconPath);

    $payload = [
        'title' => mb_substr($title, 0, 200, 'UTF-8'),
        'body'  => $body,
        'url'   => webpush_absolute_url($relativeLink),
        'icon'  => $iconUrl,
    ];

    try {
        $stmt = $pdo->prepare('
            SELECT id, endpoint, p256dh, auth_secret, content_encoding
            FROM push_subscriptions
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('webpush_send_for_user: ' . $e->getMessage());
        return;
    }

    if (empty($rows)) {
        return;
    }

    $vapid = webpush_get_vapid_credentials();
    if ($vapid === null) {
        return;
    }

    $auth = [
        'VAPID' => [
            'subject'    => $vapid['subject'],
            'publicKey'  => $vapid['publicKey'],
            'privateKey' => $vapid['privateKey'],
        ],
    ];

    try {
        $webPush = new WebPush($auth, [], 30);
    } catch (Throwable $e) {
        error_log('webpush WebPush init: ' . $e->getMessage());
        return;
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        return;
    }

    foreach ($rows as $row) {
        try {
            $subArray = [
                'endpoint' => $row['endpoint'],
                'keys'     => [
                    'p256dh' => $row['p256dh'],
                    'auth'   => $row['auth_secret'],
                ],
                'contentEncoding' => $row['content_encoding'] ?: 'aesgcm',
            ];
            $subscription = Subscription::create($subArray);
            $webPush->queueNotification($subscription, $jsonPayload);
        } catch (Throwable $e) {
            error_log('webpush queue: ' . $e->getMessage());
        }
    }

    try {
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                webpush_delete_by_endpoint($report->getEndpoint());
            }
        }
    } catch (Throwable $e) {
        error_log('webpush flush: ' . $e->getMessage());
    }
}

/**
 * @param string $endpoint
 */
function webpush_delete_by_endpoint(string $endpoint): void {
    global $pdo;
    if (!isset($pdo) || $endpoint === '') {
        return;
    }
    $hash = hash('sha256', $endpoint);
    try {
        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_sha256 = ?');
        $stmt->execute([$hash]);
    } catch (PDOException $e) {
        error_log('webpush_delete_by_endpoint: ' . $e->getMessage());
    }
}
