<?php
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json; charset=utf-8');

function getRequestHeadersSafe(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            return $headers;
        }
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$headerName] = $value;
        }
    }
    return $headers;
}

function getHeaderValueCaseInsensitive(array $headers, string $name): ?string
{
    foreach ($headers as $k => $v) {
        if (strcasecmp($k, $name) === 0) {
            return is_string($v) ? $v : null;
        }
    }
    return null;
}

function readSystemSettings(PDO $pdo): array
{
    $settings = [
        'lagersystem_api_key' => '',
        'lagersystem_user_id' => ''
    ];

    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('lagersystem_api_key', 'lagersystem_user_id')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($settings[$row['setting_key']])) {
            $settings[$row['setting_key']] = (string)$row['setting_value'];
        }
    }

    return $settings;
}

function recordExistsById(PDO $pdo, string $table, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $allowedTables = ['users', 'companies', 'customers'];
    if (!in_array($table, $allowedTables, true)) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function resolveBestellungDurchSupport(PDO $pdo): array
{
    $result = [
        'has_column' => false,
        'value_to_set' => null
    ];

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'bestellung_durch'");
        $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$col) {
            return $result;
        }

        $result['has_column'] = true;
        $type = isset($col['Type']) ? (string)$col['Type'] : '';

        if (stripos($type, 'enum(') === 0) {
            $enumValues = [];
            if (preg_match_all("/'([^']+)'/", $type, $matches) && isset($matches[1])) {
                $enumValues = $matches[1];
            }

            $preferred = ['lagersystem', 'firma', 'kunde', 'kunde_firma', 'intern'];
            foreach ($preferred as $candidate) {
                if (in_array($candidate, $enumValues, true)) {
                    $result['value_to_set'] = $candidate;
                    break;
                }
            }
        } else {
            // Bei varchar/text setzen wir den fachlich korrekten Wert.
            $result['value_to_set'] = 'lagersystem';
        }
    } catch (PDOException $e) {
        return $result;
    }

    return $result;
}

function getRequestData(string $method): array
{
    if ($method === 'GET') {
        return $_GET;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $rawBody = file_get_contents('php://input');

    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($rawBody, true);
        return is_array($json) ? $json : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
        $parsed = [];
        parse_str($rawBody, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    return [];
}

function resolveApiKey(array $headers, array $requestData): string
{
    $apiKey = '';

    if (isset($_GET['api_key'])) {
        $apiKey = trim((string)$_GET['api_key']);
    }

    if ($apiKey === '' && isset($requestData['api_key'])) {
        $apiKey = trim((string)$requestData['api_key']);
    }

    if ($apiKey === '') {
        $xApiKey = getHeaderValueCaseInsensitive($headers, 'X-API-Key');
        if ($xApiKey !== null) {
            $apiKey = trim($xApiKey);
        }
    }

    $authHeader = getHeaderValueCaseInsensitive($headers, 'Authorization');
    if ($apiKey === '' && $authHeader) {
        if (stripos($authHeader, 'Bearer ') === 0) {
            $apiKey = trim(substr($authHeader, 7));
        } elseif (stripos($authHeader, 'Basic ') === 0) {
            $encoded = trim(substr($authHeader, 6));
            $decoded = base64_decode($encoded, true);
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                $parts = explode(':', $decoded, 2);
                $apiKey = trim((string)$parts[1]);
            }
        }
    }

    return $apiKey;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $settings = readSystemSettings($pdo);
    $configuredApiKey = trim((string)$settings['lagersystem_api_key']);
    $configuredUserId = (int)($settings['lagersystem_user_id'] ?? 0);

    if ($configuredApiKey === '' || $configuredUserId <= 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Lagersystem-Schnittstelle ist nicht vollständig konfiguriert']);
        exit;
    }

    if (!recordExistsById($pdo, 'users', $configuredUserId)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Konfigurierter "Erstellt von"-Benutzer existiert nicht mehr']);
        exit;
    }

    $requestData = getRequestData($method);
    $headers = getRequestHeadersSafe();
    $apiKey = resolveApiKey($headers, $requestData);

    if ($apiKey === '' || !hash_equals($configuredApiKey, $apiKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Ungültiger API-Schlüssel']);
        exit;
    }

    $beschreibung = isset($requestData['beschreibung']) ? trim((string)$requestData['beschreibung']) : '';
    if ($beschreibung === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Pflichtfeld fehlt: beschreibung']);
        exit;
    }

    $bestellnummer = isset($requestData['bestellnummer']) ? trim((string)$requestData['bestellnummer']) : '';
    if ($bestellnummer === '') {
        do {
            $bestellnummer = 'Lager-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE bestellnummer = ?");
            $checkStmt->execute([$bestellnummer]);
        } while ($checkStmt->fetch());
    }

    $notizen = null;
    if (isset($requestData['notiz'])) {
        $notizen = trim((string)$requestData['notiz']);
    } elseif (isset($requestData['notizen'])) {
        $notizen = trim((string)$requestData['notizen']);
    }
    if ($notizen === '') {
        $notizen = null;
    }

    $companyId = isset($requestData['company_id']) && $requestData['company_id'] !== '' ? (int)$requestData['company_id'] : null;
    $customerId = isset($requestData['customer_id']) && $requestData['customer_id'] !== '' ? (int)$requestData['customer_id'] : null;
    $warnings = [];

    if ($companyId !== null && !recordExistsById($pdo, 'companies', $companyId)) {
        $warnings[] = 'company_id nicht gefunden und wurde ignoriert';
        $companyId = null;
    }
    if ($customerId !== null && !recordExistsById($pdo, 'customers', $customerId)) {
        $warnings[] = 'customer_id nicht gefunden und wurde ignoriert';
        $customerId = null;
    }
    $status = 'Neu';

    $bestellungDurchSupport = resolveBestellungDurchSupport($pdo);
    $bestellungDurchValue = $bestellungDurchSupport['value_to_set'];
    $setBestellungDurch = $bestellungDurchSupport['has_column'] && $bestellungDurchValue !== null;

    $insertSql = "
        INSERT INTO orders (
            bestellnummer, beschreibung, notizen, status, company_id, customer_id, erstellt_von, geaendert_datum" .
            ($setBestellungDurch ? ", bestellung_durch" : "") . "
        ) VALUES (
            :bestellnummer, :beschreibung, :notizen, :status, :company_id, :customer_id, :erstellt_von, NOW()" .
            ($setBestellungDurch ? ", :bestellung_durch" : "") . "
        )
    ";

    $stmt = $pdo->prepare($insertSql);
    $stmt->bindValue(':bestellnummer', $bestellnummer, PDO::PARAM_STR);
    $stmt->bindValue(':beschreibung', $beschreibung, PDO::PARAM_STR);
    $stmt->bindValue(':notizen', $notizen, $notizen !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':company_id', $companyId, $companyId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':erstellt_von', $configuredUserId, PDO::PARAM_INT);
    if ($setBestellungDurch) {
        $stmt->bindValue(':bestellung_durch', $bestellungDurchValue, PDO::PARAM_STR);
    }
    $stmt->execute();

    $orderId = (int)$pdo->lastInsertId();

    try {
        $historyStmt = $pdo->prepare("
            INSERT INTO order_status_history (order_id, status, geaendert_von)
            VALUES (:order_id, :status, :geaendert_von)
        ");
        $historyStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $historyStmt->bindValue(':status', $status, PDO::PARAM_STR);
        $historyStmt->bindValue(':geaendert_von', $configuredUserId, PDO::PARAM_INT);
        $historyStmt->execute();
    } catch (PDOException $e) {
        error_log('Lagersystem: Status-Historie konnte nicht geschrieben werden: ' . $e->getMessage());
    }

    try {
        $logStmt = $pdo->prepare("
            INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
            VALUES ('order', ?, ?, 'created', NULL, NULL, NULL, NOW())
        ");
        $logStmt->execute([$orderId, $configuredUserId]);
    } catch (PDOException $e) {
        error_log('Lagersystem: Log-Eintrag konnte nicht geschrieben werden: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Bestellung erfolgreich erstellt',
        'order_id' => $orderId,
        'bestellnummer' => $bestellnummer,
        'warnings' => $warnings
    ]);
} catch (PDOException $e) {
    error_log('Lagersystem: Datenbankfehler beim Bestellimport: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler beim Anlegen der Bestellung',
        'detail' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Lagersystem: Interner Fehler beim Bestellimport: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}
