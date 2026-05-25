<?php
/**
 * Web App Manifest (PWA) – feste scope/start_url helfen iOS/Android,
 * im „Zum Home-Bildschirm“-Modus in derselben App zu bleiben.
 */
require_once __DIR__ . '/assets/config.php';

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=300');

$base = (string) BASE_URL;
if ($base === '' || $base === '/') {
    $scope = '/';
    $start = '/';
} else {
    $scope = rtrim($base, '/') . '/';
    $start = $scope;
}

$iconPath = 'assets/images/Serohub_Icon.png';
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'branding_logo' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty(trim($row['setting_value'] ?? ''))) {
            $iconPath = ltrim(trim($row['setting_value']), '/');
        }
    } catch (Throwable $e) { /* Defaults */ }
}

$iconUrl = $start . $iconPath;
$ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION) ?: '');
$iconType = 'image/png';
if ($ext === 'gif') {
    $iconType = 'image/gif';
} elseif ($ext === 'jpg' || $ext === 'jpeg') {
    $iconType = 'image/jpeg';
} elseif ($ext === 'webp') {
    $iconType = 'image/webp';
}

$manifest = [
    'name'            => 'Serohub',
    'short_name'      => 'Serohub',
    'description'     => 'Serohub',
    'start_url'       => $start,
    'scope'           => $scope,
    'display'         => 'standalone',
    'background_color'=> '#f7fafc',
    'theme_color'     => '#f7fafc',
    'icons'           => [
        [
            'src'   => $iconUrl,
            'sizes' => 'any',
            'type'  => $iconType,
            'purpose' => 'any',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
