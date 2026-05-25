<?php
/**
 * Fehlerseite 500 – für ErrorDocument / serverseitige Weiterleitung nutzen (nicht die .html direkt).
 */
require_once dirname(__DIR__) . '/assets/config.php';
$template = resolveConfiguredErrorTemplate(500, '500.html');
renderBrandedErrorPage($template, 500, 'Es ist ein interner Fehler aufgetreten.');
