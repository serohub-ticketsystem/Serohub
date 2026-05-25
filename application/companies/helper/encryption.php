<?php
/**
 * Verschlüsselung für Firmendaten (alle Klartextfelder).
 * Nutzt dieselbe Logik wie todos/helper/encryption.php (ENCRYPTION_KEY bzw. DB_PASS).
 * Bestehende unverschlüsselte Einträge werden beim Lesen unverändert zurückgegeben (Rückwärtskompatibilität).
 */

if (!function_exists('encrypt_for_db')) {
    require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';
}

/** Spalten, die verschlüsselt gespeichert werden (Klartext in DB → verschlüsselt) */
function get_company_encrypted_columns() {
    return [
        'name', 'domain', 'kundennummer', 'adresse', 'plz', 'ort',
        'lieferadresse', 'liefer_plz', 'liefer_ort',
        'rechnungs_adresse', 'rechnungs_plz', 'rechnungs_ort', 'rechnungs_email',
        'email', 'telefonnummer', 'notizen',
        'ansprechpartner_manuell_name', 'ansprechpartner_manuell_email',
        'ansprechpartner_manuell_telefon', 'ansprechpartner_manuell_notiz',
        'ansprechpartner_manuell'
    ];
}

/**
 * Firmen-Zeile: alle Klartextfelder entschlüsseln (falls verschlüsselt).
 * @param array|null $row Zeile aus companies (by reference)
 */
function decrypt_company_row(&$row) {
    if (!is_array($row)) {
        return;
    }
    $cols = get_company_encrypted_columns();
    foreach ($cols as $col) {
        if (array_key_exists($col, $row) && ($row[$col] === null || $row[$col] === '' || is_string($row[$col]))) {
            $row[$col] = decrypt_from_db($row[$col]);
        }
    }
}

/**
 * Einzelnen Wert für die DB verschlüsseln (null/leer bleibt unverändert).
 * @param string|null $value
 * @return string|null
 */
function encrypt_company_value($value) {
    if ($value === null || $value === '') {
        return $value;
    }
    return encrypt_for_db(is_string($value) ? $value : (string) $value);
}
