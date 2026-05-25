<?php
/**
 * API-Endpunkt zum Testen des E-Mail-Empfang-Cronjobs
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once dirname(__DIR__, 2) . '/assets/config.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Konfiguration: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Prüfen ob eingeloggt und Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
    exit;
}

// Nur GET erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

try {
    $readCronLog = static function () {
        $logFile = dirname(__DIR__, 2) . '/logs/email-receive-cron.log';
        if (!file_exists($logFile)) {
            return '';
        }
        $lines = file($logFile);
        if (!$lines) {
            return '';
        }
        return implode('', array_slice($lines, -50));
    };

    // Cronjob-Datei ausführen
    $cronjobFile = dirname(__DIR__) . '/cron/fetch-emails-cron.php';
    
    if (!file_exists($cronjobFile)) {
        throw new Exception('Cronjob-Datei nicht gefunden: ' . $cronjobFile);
    }
    
    // Output-Buffering starten, um die Ausgabe zu erfassen
    ob_start();
    
    // Cronjob ausführen
    // Wir müssen die Datei inkludieren, aber die Ausgabe abfangen
    // Da der Cronjob echo verwendet, müssen wir die Ausgabe abfangen
    try {
        include $cronjobFile;
    } catch (Throwable $cronError) {
        // Fehler vom Cronjob abfangen
        ob_end_clean();
        throw new Exception('Cronjob-Fehler: ' . $cronError->getMessage());
    }
    
    // Ausgabe erfassen
    $output = ob_get_clean();
    
    // Prüfen ob der Cronjob erfolgreich war (er sollte nicht exit(1) aufgerufen haben)
    // Wenn exit(1) aufgerufen wurde, würde die Ausgabe leer sein oder einen Fehler enthalten
    
    // Log-Datei lesen (falls vorhanden)
    $logContent = $readCronLog();
    
    // Prüfen ob in der Ausgabe oder im Log Fehler enthalten sind
    $hasError = false;
    $errorMessage = '';
    
    if (stripos($output, 'FEHLER') !== false || stripos($output, 'ERROR') !== false) {
        $hasError = true;
        $errorMessage = 'Der Cronjob hat Fehler gemeldet. Bitte die Log-Datei prüfen.';
    }
    
    if (stripos($logContent, 'FEHLER') !== false || stripos($logContent, 'ERROR') !== false) {
        $hasError = true;
        if (empty($errorMessage)) {
            $errorMessage = 'Der Cronjob hat Fehler in der Log-Datei gemeldet.';
        }
    }
    
    // Erfolg zurückgeben
    echo json_encode([
        'success' => !$hasError,
        'message' => $hasError ? $errorMessage : 'Cronjob erfolgreich ausgeführt',
        'output' => $output,
        'log' => $logContent
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Ausführen des Cronjobs: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'log' => (isset($readCronLog) && is_callable($readCronLog)) ? $readCronLog() : ''
    ], JSON_UNESCAPED_UNICODE);
}
