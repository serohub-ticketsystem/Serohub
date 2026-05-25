<?php
/**
 * Admin: Web-Push / VAPID (Speicherung in system_settings, optional Override per config.php).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/push_notifications.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare('SELECT rolle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            erstellt_datum DATETIME DEFAULT CURRENT_TIMESTAMP,
            geaendert_datum DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    error_log('push-settings system_settings: ' . $e->getMessage());
}

/**
 * @param string $subject
 */
function push_settings_validate_subject(string $subject): bool {
    $subject = trim($subject);
    if ($subject === '') {
        return false;
    }
    return strncmp($subject, 'mailto:', 7) === 0 || strncmp($subject, 'https://', 8) === 0;
}

/**
 * @return bool
 */
function push_settings_database_has_vapid_keys(): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS c FROM system_settings
            WHERE setting_key = 'webpush_vapid_private_key' AND setting_value IS NOT NULL AND TRIM(setting_value) != ''
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (int) $row['c'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $configActive = webpush_is_config_file_active();
        $cred = webpush_get_vapid_credentials();
        $dbHasKeys = push_settings_database_has_vapid_keys();

        echo json_encode([
            'success'             => true,
            'config_file_active'  => $configActive,
            'configured'          => $cred !== null,
            'active_source'       => $cred !== null ? ($cred['source'] ?? 'none') : 'none',
            'subject'             => $cred['subject'] ?? '',
            'public_key'          => $cred['publicKey'] ?? '',
            'can_manage_in_ui'    => !$configActive,
            'database_has_keys'   => $dbHasKeys,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
        exit;
    }

    $action = isset($data['action']) ? (string) $data['action'] : '';

    if (webpush_is_config_file_active() && $action !== 'clear_database') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'VAPID ist in der Konfigurationsdatei gesetzt – Änderungen nur dort oder Einträge dort entfernen. Datenbank-Einträge können unten entfernt werden.']);
        exit;
    }

    if ($action === 'save_subject') {
        $subject = isset($data['subject']) ? trim((string) $data['subject']) : '';
        if (!push_settings_validate_subject($subject)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject muss mit mailto: oder https:// beginnen.']);
            exit;
        }
        if (!push_settings_database_has_vapid_keys()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Zuerst ein Schlüsselpaar erzeugen oder VAPID vollständig in der Datenbank speichern.']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('webpush_vapid_subject', :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2, geaendert_datum = NOW()
        ");
        $stmt->execute([':v' => $subject, ':v2' => $subject]);
        echo json_encode(['success' => true, 'message' => 'Kontakt (Subject) gespeichert']);
        exit;
    }

    if ($action === 'generate_keys') {
        $subject = isset($data['subject']) ? trim((string) $data['subject']) : '';
        if (!push_settings_validate_subject($subject)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bitte zuerst ein gültiges Subject eintragen (mailto:… oder https://…).']);
            exit;
        }
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Composer-Paket minishlink/web-push fehlt.']);
            exit;
        }
        require_once $autoload;
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2, geaendert_datum = NOW()
        ");
        $pairs = [
            'webpush_vapid_public_key'  => $keys['publicKey'],
            'webpush_vapid_private_key' => $keys['privateKey'],
            'webpush_vapid_subject'     => $subject,
        ];
        foreach ($pairs as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v, ':v2' => $v]);
        }

        echo json_encode([
            'success'    => true,
            'message'    => 'Neues Schlüsselpaar gespeichert. Nutzer müssen Push auf ihren Geräten ggf. neu aktivieren.',
            'public_key' => $keys['publicKey'],
            'subject'    => $subject,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'clear_database') {
        $stmt = $pdo->prepare("
            DELETE FROM system_settings WHERE setting_key IN (
                'webpush_vapid_public_key', 'webpush_vapid_private_key', 'webpush_vapid_subject'
            )
        ");
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'VAPID-Daten aus der Datenbank entfernt']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('push-settings: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Serverfehler']);
}
