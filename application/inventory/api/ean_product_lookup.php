<?php
/**
 * GTIN-Prüfung und Produktdaten aus öffentlichen Barcode-Datenbanken:
 * Open Food Facts, Open Beauty Facts, Open Products Facts (IT/allgemein), UPCitemdb (IT/Hardware).
 */

declare(strict_types=1);

/**
 * Liefert nur Ziffern einer GTIN/EAN oder null.
 */
function inventory_normalize_gtin(string $code): ?string
{
    $d = preg_replace('/\D+/', '', trim($code));
    if ($d === '' || strlen($d) > 14) {
        return null;
    }
    return $d;
}

function inventory_validate_ean13(string $ean): bool
{
    if (strlen($ean) !== 13 || !ctype_digit($ean)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $ean[$i] * (($i % 2 === 0) ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;

    return $check === (int) $ean[12];
}

function inventory_validate_ean8(string $ean): bool
{
    if (strlen($ean) !== 8 || !ctype_digit($ean)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 7; $i++) {
        $sum += (int) $ean[$i] * (($i % 2 === 0) ? 3 : 1);
    }
    $check = (10 - ($sum % 10)) % 10;

    return $check === (int) $ean[7];
}

function inventory_validate_upca(string $ean): bool
{
    if (strlen($ean) !== 12 || !ctype_digit($ean)) {
        return false;
    }
    $odd = (int) $ean[0] + (int) $ean[2] + (int) $ean[4] + (int) $ean[6] + (int) $ean[8] + (int) $ean[10];
    $even = (int) $ean[1] + (int) $ean[3] + (int) $ean[5] + (int) $ean[7] + (int) $ean[9];
    $total = $odd * 3 + $even;
    $check = (10 - ($total % 10)) % 10;

    return $check === (int) $ean[11];
}

/**
 * Prüft normalisierte Ziffernfolge (nur 0–9) auf gültige GTIN/EAN inkl. Prüfziffer.
 */
function inventory_is_normalized_gtin_valid(string $d): bool
{
    if ($d === '' || !ctype_digit($d)) {
        return false;
    }
    $len = strlen($d);
    if ($len === 13) {
        return inventory_validate_ean13($d);
    }
    if ($len === 8) {
        return inventory_validate_ean8($d);
    }
    if ($len === 12) {
        return inventory_validate_upca($d);
    }
    // GTIN-14 (Logistik) wird hier nicht validiert – bei Bedarf manuell anlegen

    return false;
}

/**
 * Prüft, ob der Code eine gültige GTIN/EAN ist (für automatische Neuanlage per Scan).
 */
function inventory_is_valid_gtin_for_autocreate(string $code): bool
{
    $d = inventory_normalize_gtin($code);

    return $d !== null && inventory_is_normalized_gtin_valid($d);
}

/**
 * @return array{bezeichnung: string, beschreibung: ?string, source: string}
 */
function inventory_gtin_fallback_metadata(string $gtin): array
{
    return [
        'bezeichnung' => 'Artikel EAN ' . $gtin,
        'beschreibung' => 'Automatisch angelegt beim Scannen. In den angebundenen Barcode-Datenbanken (Open Food/Beauty/Products Facts, UPCitemdb) wurden keine Produktdaten zu dieser EAN gefunden. Bitte Bezeichnung und Details unter „Bearbeiten“ ergänzen.',
        'source' => 'fallback',
    ];
}

/**
 * @param list<string> $extraHeaders Zeilen wie "user_key: abc" (ohne \r\n)
 */
function inventory_http_get_json(string $url, int $timeoutSec = 7, array $extraHeaders = []): ?array
{
    $headerLines = array_merge(
        [
            'Accept: application/json',
            'User-Agent: Softwareverteilung-Lager/1.0 (Kontakt: IT-Admin)',
        ],
        $extraHeaders
    );
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeoutSec,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . "\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/**
 * @return array{bezeichnung: string, beschreibung: ?string, source: string}|null
 */
function inventory_parse_open_facts_response(?array $json, string $gtin): ?array
{
    if ($json === null || empty($json['product']) || empty($json['status']) || (int) $json['status'] !== 1) {
        return null;
    }
    $p = $json['product'];
    if (!is_array($p)) {
        return null;
    }
    $name = trim((string) (
        ($p['product_name_de'] ?? '')
        ?: ($p['product_name'] ?? '')
        ?: ($p['product_name_en'] ?? '')
        ?: ($p['generic_name_de'] ?? '')
        ?: ($p['generic_name'] ?? '')
        ?: ''
    ));
    if ($name === '') {
        $name = 'Produkt EAN ' . $gtin;
    }
    $parts = [];
    if (!empty($p['brands'])) {
        $parts[] = 'Marke / Hersteller: ' . trim((string) $p['brands']);
    }
    if (!empty($p['quantity'])) {
        $parts[] = 'Menge / Packung: ' . trim((string) $p['quantity']);
    }
    if (!empty($p['packaging'])) {
        $parts[] = 'Verpackung: ' . trim((string) $p['packaging']);
    }
    if (!empty($p['categories'])) {
        $parts[] = 'Kategorien: ' . trim((string) $p['categories']);
    }
    $ing = trim((string) ($p['ingredients_text_de'] ?? $p['ingredients_text'] ?? ''));
    if ($ing !== '') {
        if (function_exists('mb_substr')) {
            $ingShort = mb_substr($ing, 0, 2000);
            if (mb_strlen($ing) > 2000) {
                $ingShort .= ' …';
            }
        } else {
            $ingShort = strlen($ing) > 2000 ? (substr($ing, 0, 2000) . ' …') : $ing;
        }
        $parts[] = 'Inhaltsstoffe / Hinweise: ' . $ingShort;
    }
    $desc = $parts !== [] ? implode("\n", $parts) : null;
    if ($desc !== null && strlen($desc) > 16000) {
        $desc = substr($desc, 0, 15997) . '…';
    }

    return [
        'bezeichnung' => $name,
        'beschreibung' => $desc,
        'source' => 'openfacts',
    ];
}

/**
 * UPCitemdb (u. a. Elektronik, IT-Zubehör, allgemeiner Handel).
 *
 * @return array{bezeichnung: string, beschreibung: ?string, source: string}|null
 */
function inventory_parse_upcitemdb_response(?array $json, string $gtin): ?array
{
    if ($json === null || ($json['code'] ?? '') !== 'OK') {
        return null;
    }
    $items = $json['items'] ?? null;
    if (!is_array($items) || $items === []) {
        return null;
    }
    $item = $items[0];
    if (!is_array($item)) {
        return null;
    }
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        return null;
    }
    $parts = [];
    $brand = trim((string) ($item['brand'] ?? ''));
    if ($brand !== '') {
        $parts[] = 'Marke / Hersteller: ' . $brand;
    }
    $model = trim((string) ($item['model'] ?? ''));
    if ($model !== '') {
        $parts[] = 'Modell: ' . $model;
    }
    $descShort = trim((string) ($item['description'] ?? ''));
    if ($descShort !== '' && strcasecmp($descShort, $title) !== 0) {
        if (function_exists('mb_substr')) {
            $d = mb_substr($descShort, 0, 2500);
            if (mb_strlen($descShort) > 2500) {
                $d .= ' …';
            }
        } else {
            $d = strlen($descShort) > 2500 ? (substr($descShort, 0, 2500) . ' …') : $descShort;
        }
        $parts[] = $d;
    }
    $category = trim((string) ($item['category'] ?? ''));
    if ($category !== '') {
        $parts[] = 'Kategorie: ' . $category;
    }
    foreach (['dimension' => 'Abmessungen', 'size' => 'Größe', 'color' => 'Farbe', 'weight' => 'Gewicht'] as $key => $label) {
        $v = trim((string) ($item[$key] ?? ''));
        if ($v !== '') {
            $parts[] = $label . ': ' . $v;
        }
    }
    $eanListed = trim((string) ($item['ean'] ?? ''));
    if ($eanListed !== '' && $eanListed !== $gtin) {
        $parts[] = 'EAN (Datenbank): ' . $eanListed;
    }
    $desc = $parts !== [] ? implode("\n", $parts) : null;
    if ($desc !== null && strlen($desc) > 16000) {
        $desc = substr($desc, 0, 15997) . '…';
    }

    return [
        'bezeichnung' => $title,
        'beschreibung' => $desc,
        'source' => 'upcitemdb',
    ];
}

/**
 * @return array{bezeichnung: string, beschreibung: ?string, source: string}|null
 */
function inventory_fetch_upcitemdb_metadata(string $gtin): ?array
{
    $extra = [];
    if (defined('UPCITEMDB_USER_KEY') && is_string(UPCITEMDB_USER_KEY) && UPCITEMDB_USER_KEY !== '') {
        $url = 'https://api.upcitemdb.com/prod/v1/lookup?upc=' . rawurlencode($gtin);
        $extra = [
            'user_key: ' . UPCITEMDB_USER_KEY,
            'key_type: 3scale',
        ];
    } else {
        $url = 'https://api.upcitemdb.com/prod/trial/lookup?upc=' . rawurlencode($gtin);
    }
    $json = inventory_http_get_json($url, 8, $extra);

    return inventory_parse_upcitemdb_response($json, $gtin);
}

/**
 * Ruft mehrere öffentliche Quellen ab; bei Treffer strukturierte Metadaten, sonst Fallback.
 *
 * @return array{bezeichnung: string, beschreibung: ?string, source: string}
 */
function inventory_fetch_gtin_metadata(string $gtin): array
{
    $fields = 'product_name,product_name_de,product_name_en,brands,quantity,generic_name,generic_name_de,packaging,categories,ingredients_text,ingredients_text_de,code,status';
    $urls = [
        'openfoodfacts' => 'https://world.openfoodfacts.org/api/v2/product/' . rawurlencode($gtin) . '.json?fields=' . rawurlencode($fields),
        'openbeautyfacts' => 'https://world.openbeautyfacts.org/api/v2/product/' . rawurlencode($gtin) . '.json?fields=' . rawurlencode($fields),
        'openproductsfacts' => 'https://world.openproductsfacts.org/api/v2/product/' . rawurlencode($gtin) . '.json?fields=' . rawurlencode($fields),
    ];
    foreach ($urls as $label => $url) {
        $json = inventory_http_get_json($url);
        $parsed = inventory_parse_open_facts_response($json, $gtin);
        if ($parsed !== null) {
            $parsed['source'] = $label;

            return $parsed;
        }
    }

    $upc = inventory_fetch_upcitemdb_metadata($gtin);
    if ($upc !== null) {
        return $upc;
    }

    return inventory_gtin_fallback_metadata($gtin);
}
