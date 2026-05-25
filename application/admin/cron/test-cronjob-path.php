<?php
/**
 * Test-Skript zum Prüfen der Pfade und Umgebung für den Cronjob
 * Aufruf: php test-cronjob-path.php
 */

echo "=== Cronjob-Pfad-Test ===\n\n";

// Aktuelle Umgebung
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "Aktuelles Arbeitsverzeichnis: " . getcwd() . "\n";
echo "Script-Datei: " . __FILE__ . "\n";
echo "Script-Verzeichnis: " . dirname(__FILE__) . "\n\n";

// Pfade berechnen
$scriptDir = dirname(__FILE__);
$webappDir = dirname($scriptDir, 1); // Ein Verzeichnis nach oben (von cron zu admin)
$baseDir = dirname($webappDir, 1); // Noch ein Verzeichnis nach oben (von admin zu webapp)

echo "Berechnete Pfade:\n";
echo "  Script-Dir: $scriptDir\n";
echo "  Webapp-Dir: $webappDir\n";
echo "  Base-Dir: $baseDir\n\n";

// Prüfe wichtige Dateien
echo "Prüfe wichtige Dateien:\n";
$configFile = $baseDir . '/assets/config.php';
echo "  config.php: " . ($configFile) . " - " . (file_exists($configFile) ? "✓ EXISTIERT" : "✗ NICHT GEFUNDEN") . "\n";

$fetchEmailsFile = $baseDir . '/admin/api/fetch-emails.php';
echo "  fetch-emails.php: " . ($fetchEmailsFile) . " - " . (file_exists($fetchEmailsFile) ? "✓ EXISTIERT" : "✗ NICHT GEFUNDEN") . "\n";

$logDir = $baseDir . '/logs';
echo "  Log-Verzeichnis: " . ($logDir) . " - " . (file_exists($logDir) ? "✓ EXISTIERT" : "✗ NICHT GEFUNDEN") . "\n";
if (file_exists($logDir)) {
    echo "    Beschreibbar: " . (is_writable($logDir) ? "✓ JA" : "✗ NEIN") . "\n";
}

echo "\n";

// Prüfe IMAP-Erweiterung
echo "PHP-Erweiterungen:\n";
echo "  IMAP: " . (function_exists('imap_open') ? "✓ VERFÜGBAR" : "✗ NICHT VERFÜGBAR") . "\n";
echo "  PDO: " . (extension_loaded('pdo') ? "✓ VERFÜGBAR" : "✗ NICHT VERFÜGBAR") . "\n";
echo "  PDO MySQL: " . (extension_loaded('pdo_mysql') ? "✓ VERFÜGBAR" : "✗ NICHT VERFÜGBAR") . "\n";

echo "\n";

// Versuche config.php zu laden
if (file_exists($configFile)) {
    echo "Versuche config.php zu laden...\n";
    try {
        chdir($baseDir);
        require_once $configFile;
        echo "  ✓ config.php erfolgreich geladen\n";
        
        if (isset($pdo)) {
            echo "  ✓ PDO-Verbindung vorhanden\n";
            try {
                $stmt = $pdo->query("SELECT 1");
                echo "  ✓ Datenbankverbindung funktioniert\n";
            } catch (Exception $e) {
                echo "  ✗ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  ✗ PDO-Verbindung nicht vorhanden\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Fehler beim Laden: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ config.php nicht gefunden - kann nicht getestet werden\n";
}

echo "\n=== Test abgeschlossen ===\n";
