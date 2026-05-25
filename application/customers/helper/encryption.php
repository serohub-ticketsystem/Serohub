<?php
/**
 * Verschlüsselung für Kundendaten (alle Klartextfelder).
 * Nutzt dieselbe Logik wie todos/helper/encryption.php (ENCRYPTION_KEY bzw. DB_PASS).
 * Bestehende unverschlüsselte Einträge werden beim Lesen unverändert zurückgegeben (Rückwärtskompatibilität).
 */

if (!function_exists('encrypt_for_db')) {
    require_once dirname(__DIR__, 2) . '/todos/helper/encryption.php';
}

/** Spalten, die verschlüsselt gespeichert werden (Klartext in DB → verschlüsselt) */
function get_customer_encrypted_columns() {
    return [
        'name', 'kundennummer', 'email', 'telefon', 'adresse', 'plz', 'ort',
        'lieferadresse', 'liefer_plz', 'liefer_ort',
        'rechnungs_adresse', 'rechnungs_plz', 'rechnungs_ort', 'rechnungs_email',
        'notizen',
        'ansprechpartner_manuell_name', 'ansprechpartner_manuell_email',
        'ansprechpartner_manuell_telefon', 'ansprechpartner_manuell_notiz',
        'ansprechpartner_manuell'
    ];
}

/**
 * Kunden-Zeile: alle Klartextfelder entschlüsseln (falls verschlüsselt).
 * @param array|null $row Zeile aus customers (by reference)
 */
function decrypt_customer_row(&$row) {
    if (!is_array($row)) {
        return;
    }
    $cols = get_customer_encrypted_columns();
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
function encrypt_customer_value($value) {
    if ($value === null || $value === '') {
        return $value;
    }
    return encrypt_for_db(is_string($value) ? $value : (string) $value);
}
