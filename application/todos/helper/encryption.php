<?php
/**
 * Verschlüsselung für Aufgaben und Ordner (titel, beschreibung, name).
 * Neue Einträge werden verschlüsselt gespeichert; bestehende unverschlüsselte
 * Werte werden weiterhin unverändert angezeigt (Rückwärtskompatibilität).
 */

// Primärer Schlüssel (zum Schreiben und bevorzugt zum Lesen)
if (!defined('ENCRYPTION_KEY') || ENCRYPTION_KEY === '') {
    $key = defined('DB_PASS') ? hash('sha256', DB_PASS . 'todos_folders_enc_v1', true) : null;
} else {
    $key = is_string(ENCRYPTION_KEY) ? hash('sha256', ENCRYPTION_KEY, true) : ENCRYPTION_KEY;
}
// Fallback-Schlüssel (nur zum Lesen): Daten, die mit DB_PASS abgeleitet wurden, z. B. vor Setzen von ENCRYPTION_KEY
$keyFallback = defined('DB_PASS') ? hash('sha256', DB_PASS . 'todos_folders_enc_v1', true) : null;

const ENCRYPTION_PREFIX = 'ENC:';
const ENCRYPTION_CIPHER = 'aes-256-cbc';

/**
 * Verschlüsselt einen Klartext für die Speicherung in der DB.
 * @param string|null $plaintext
 * @return string|null Null wenn Input null/leer, sonst "ENC:" + base64(iv + ciphertext)
 */
function encrypt_for_db($plaintext) {
    global $key;
    if ($key === null) {
        return $plaintext;
    }
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return $plaintext;
    }
    return ENCRYPTION_PREFIX . base64_encode($iv . $encrypted);
}

/** Platzhalter, wenn Entschlüsselung fehlschlägt (z. B. anderer Schlüssel). */
const DECRYPT_FAILED_PLACEHOLDER = '[Verschlüsselt]';

/**
 * Prüft, ob ein Klartext sicher als lesbarer Text (UTF-8) angezeigt werden kann.
 * OpenSSL liefert bei falschem Schlüssel oft kein false, sondern zufälligen Binärmüll.
 */
function todos_decrypted_plaintext_is_valid($s) {
    if ($s === null || $s === '') {
        return true;
    }
    if (!is_string($s)) {
        return false;
    }
    if (strpos($s, "\0") !== false) {
        return false;
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        return false;
    }
    // Steuerzeichen außer Tab/Zeilenumbruch (Beschreibung kann Umbrüche enthalten)
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $s)) {
        return false;
    }
    return true;
}

/**
 * Eine Entschlüsselungsrunde (ein ENC:-Block).
 * @return string|null Klartext oder null bei Fehler
 */
function todos_decrypt_one_layer($value, $secretKey) {
    if ($secretKey === null) {
        return null;
    }
    if (strpos($value, ENCRYPTION_PREFIX) !== 0) {
        return $value;
    }
    $raw = base64_decode(substr($value, strlen(ENCRYPTION_PREFIX)), true);
    if ($raw === false || strlen($raw) < 16) {
        return null;
    }
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, $secretKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return null;
    }
    return $decrypted;
}

/**
 * Entschlüsselt einen aus der DB gelesenen Wert (oder gibt unverschlüsselten Wert zurück).
 * @param string|null $value
 * @return string|null
 */
function decrypt_from_db($value) {
    global $key, $keyFallback;
    if ($value === null || $value === '') {
        return $value;
    }
    // Klartext ohne ENC: — nur durchreichen, wenn es lesbar wirkt (sonst alter Roh-Binary-Müll)
    if (strpos($value, ENCRYPTION_PREFIX) !== 0) {
        return todos_decrypted_plaintext_is_valid($value) ? $value : DECRYPT_FAILED_PLACEHOLDER;
    }

    $maxLayers = 5;
    $current = $value;
    while ($maxLayers-- > 0 && is_string($current) && strpos($current, ENCRYPTION_PREFIX) === 0) {
        $next = null;
        if ($key !== null) {
            $next = todos_decrypt_one_layer($current, $key);
        }
        if ($next === null && $keyFallback !== null && $keyFallback !== $key) {
            $next = todos_decrypt_one_layer($current, $keyFallback);
        }
        if ($next === null) {
            error_log('Todos/Folders: Entschlüsselung fehlgeschlagen (evtl. anderer ENCRYPTION_KEY/DB_PASS).');
            return DECRYPT_FAILED_PLACEHOLDER;
        }
        $current = $next;
    }
    if (is_string($current) && strpos($current, ENCRYPTION_PREFIX) === 0) {
        return DECRYPT_FAILED_PLACEHOLDER;
    }
    if (!todos_decrypted_plaintext_is_valid($current)) {
        error_log('Todos/Folders: Entschlüsselung liefert keinen gültigen UTF-8-Text (evtl. falscher Schlüssel).');
        return DECRYPT_FAILED_PLACEHOLDER;
    }
    return $current;
}

/**
 * Todo-Zeile: titel, beschreibung, folder_name und company_name entschlüsseln (falls verschlüsselt).
 */
function decrypt_todo_row(&$row) {
    if (!is_array($row)) return;
    if (isset($row['titel'])) $row['titel'] = decrypt_from_db($row['titel']);
    if (isset($row['beschreibung'])) $row['beschreibung'] = decrypt_from_db($row['beschreibung']);
    if (isset($row['folder_name'])) $row['folder_name'] = decrypt_from_db($row['folder_name']);
    if (isset($row['company_name'])) $row['company_name'] = decrypt_from_db($row['company_name']);
    if (isset($row['project_name'])) $row['project_name'] = decrypt_from_db($row['project_name']);
}

/**
 * Ordner-Zeile: name entschlüsseln (falls verschlüsselt).
 */
function decrypt_folder_row(&$row) {
    if (!is_array($row)) return;
    if (isset($row['name'])) $row['name'] = decrypt_from_db($row['name']);
}
