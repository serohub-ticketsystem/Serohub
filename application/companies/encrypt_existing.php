<?php
/**
 * Verschlüsselt bestehende Firmeneinträge in der Datenbank.
 * Nur Felder, die noch Klartext sind (nicht mit "ENC:" beginnen), werden verschlüsselt.
 * Aufruf: php encrypt_existing.php (CLI) oder im Browser mit Admin-Login (GET/POST).
 */

if (php_sapi_name() === 'cli') {
    require_once dirname(__DIR__) . '/assets/config.php';
    $isCli = true;
} else {
    session_start();
    require_once dirname(__DIR__) . '/assets/config.php';
    require_once __DIR__ . '/helper/encryption.php';
    $isCli = false;
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        exit;
    }
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || $u['rolle'] !== 'Admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nur Admins']);
        exit;
    }
}

require_once __DIR__ . '/helper/encryption.php';

if (!function_exists('encrypt_for_db')) {
    if ($isCli) {
        echo "Fehler: encrypt_for_db nicht verfügbar (todos/helper/encryption.php).\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Verschlüsselung nicht verfügbar']);
    }
    exit(1);
}

const ENCRYPTION_PREFIX = 'ENC:';
$wanted = get_company_encrypted_columns();
$existingCols = $pdo->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_COLUMN);
$columns = array_intersect($wanted, $existingCols);
if (empty($columns)) {
    if ($isCli) {
        echo "Keine zu verschlüsselnden Spalten gefunden.\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Keine Spalten zu verschlüsseln', 'updated' => 0]);
    }
    exit(0);
}

try {
    $stmt = $pdo->query("SELECT id, " . implode(', ', array_map(function ($c) { return '`' . $c . '`'; }, $columns)) . " FROM companies");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $updates = [];
        $params = [];
        $needsUpdate = false;

        foreach ($columns as $col) {
            if (!array_key_exists($col, $row)) {
                continue;
            }
            $val = $row[$col];
            if ($val === null || $val === '') {
                continue;
            }
            if (strpos((string) $val, ENCRYPTION_PREFIX) === 0) {
                continue;
            }
            $encrypted = encrypt_company_value($val);
            if ($encrypted === $val) {
                continue;
            }
            $updates[] = "`" . $col . "` = ?";
            $params[] = $encrypted;
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $params[] = $id;
            $sql = "UPDATE companies SET " . implode(', ', $updates) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            $updated++;
        }
    }

    if ($isCli) {
        echo "Fertig. Verschlüsselt: $updated Firmen.\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Bestehende Firmendaten verschlüsselt',
            'updated' => $updated
        ]);
    }
} catch (Exception $e) {
    error_log("Firmen verschlüsseln: " . $e->getMessage());
    if ($isCli) {
        echo "Fehler: " . $e->getMessage() . "\n";
        exit(1);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit(1);
}
