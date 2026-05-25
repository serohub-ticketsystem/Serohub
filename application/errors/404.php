<?php
/**
 * Fehlerseite 404 – für ErrorDocument / serverseitige Weiterleitung nutzen (nicht die .html direkt).
 */
require_once dirname(__DIR__) . '/assets/config.php';
$template = resolveConfiguredErrorTemplate(404, '404.html');
renderBrandedErrorPage($template, 404, 'Die angeforderte Seite wurde nicht gefunden.');
