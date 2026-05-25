<?php
/**
 * Proxy für JsSIP-Bibliothek um CORS-Probleme zu umgehen
 * Lädt die Bibliothek vom CDN und gibt sie mit korrekten Headern zurück
 */

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400'); // 24 Stunden Cache

// CDN-Quellen
$sources = [
    'https://cdn.jsdelivr.net/npm/jssip@3.10.1/dist/jssip.min.js',
    'https://unpkg.com/jssip@3.10.1/dist/jssip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jssip/3.10.1/jssip.min.js'
];

$content = null;
$lastError = null;

// Zuerst file_get_contents versuchen (oft zuverlässiger)
foreach ($sources as $url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (compatible; JsSIP-Proxy/1.0)\r\n",
            'timeout' => 15,
            'follow_location' => 1,
            'ignore_errors' => true
        ],
        'https' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (compatible; JsSIP-Proxy/1.0)\r\n",
            'timeout' => 15,
            'follow_location' => 1,
            'ignore_errors' => true,
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content !== false && !empty($content) && strlen($content) > 1000) {
        // Erfolgreich geladen (mindestens 1000 Zeichen = wahrscheinlich echte Bibliothek)
        echo $content;
        exit;
    }
}

// Fallback: curl verwenden (falls verfügbar)
if (function_exists('curl_init')) {
    foreach ($sources as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; JsSIP-Proxy/1.0)');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content !== false && $httpCode === 200 && !empty($content) && strlen($content) > 1000) {
            // Erfolgreich geladen
            echo $content;
            exit;
        } else {
            $lastError = $error ?: "HTTP $httpCode";
        }
    }
}

// Falls alle Quellen fehlgeschlagen sind
http_response_code(500);
header('Content-Type: application/json');
echo json_encode([
    'error' => 'JsSIP-Bibliothek konnte nicht geladen werden',
    'details' => $lastError ?: 'Alle CDN-Quellen fehlgeschlagen',
    'sources_tried' => count($sources),
    'curl_available' => function_exists('curl_init'),
    'file_get_contents_available' => function_exists('file_get_contents')
]);
?>
