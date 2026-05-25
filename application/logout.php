<?php
session_start();

// Angemeldet bleiben: Token aus DB und Cookie entfernen; aktive Session aus Übersicht entfernen
if (file_exists(__DIR__ . '/assets/config.php')) {
    require_once __DIR__ . '/assets/config.php';
    if (function_exists('deleteRememberMeToken')) {
        deleteRememberMeToken();
    }
    if (function_exists('removeCurrentUserSession')) {
        removeCurrentUserSession();
    }
}

// Session beenden
$_SESSION = array();

// Session-Cookie löschen
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Session zerstören
session_destroy();

// BASE_URL definieren falls nicht vorhanden
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$redirectUrl = BASE_URL . '?logged_out=1';
$logoutTitle = 'Abmeldung';

try {
    require_once __DIR__ . '/assets/config.php';
    $parts = ['Serohub', ''];
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('branding_name_part1', 'branding_name_part2')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['setting_key'] === 'branding_name_part1' && $r['setting_value'] !== null) $parts[0] = trim($r['setting_value']);
        if ($r['setting_key'] === 'branding_name_part2' && $r['setting_value'] !== null) $parts[1] = trim($r['setting_value']);
    }
    $logoutTitle = trim($parts[0] . ' ' . $parts[1]) . ' – Abmeldung';
} catch (Exception $e) {
    // Fallback beibehalten
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php echo htmlspecialchars($logoutTitle); ?></title>
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($redirectUrl); ?>">
</head>
<body>
    <p>Sie wurden abgemeldet. Sie werden weitergeleitet …</p>
</body>
</html>