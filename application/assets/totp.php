<?php
/**
 * TOTP (Time-based One-Time Password) Helper
 * Kompatibel mit Google Authenticator, Authy, etc.
 */
class TOTP {
    private static $secretLength = 32;
    
    /**
     * Generiert einen zufälligen Secret für 2FA
     */
    public static function generateSecret() {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 Alphabet
        $secret = '';
        for ($i = 0; $i < self::$secretLength; $i++) {
            $secret .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $secret;
    }
    
    /**
     * Konvertiert Base32 zu Binär
     */
    private static function base32Decode($secret) {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $secret = strtoupper($secret);
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($base32chars, $secret[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        
        $bytes = '';
        for ($i = 0; $i < strlen($bits) - 7; $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }
        
        return $bytes;
    }
    
    /**
     * Generiert TOTP-Code
     */
    public static function getCode($secret, $timeStep = 30) {
        $time = floor(time() / $timeStep);
        return self::generateHOTP($secret, $time);
    }
    
    /**
     * Generiert HOTP-Code (HMAC-based One-Time Password)
     */
    private static function generateHOTP($secret, $counter) {
        $secretKey = self::base32Decode($secret);
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secretKey, true);
        
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Validiert einen TOTP-Code
     */
    public static function verifyCode($secret, $code, $timeStep = 30, $window = 1) {
        $code = trim($code);
        
        // Zeitfenster prüfen (aktuell, vorher, nachher)
        for ($i = -$window; $i <= $window; $i++) {
            $time = floor(time() / $timeStep) + $i;
            $validCode = self::generateHOTP($secret, $time);
            if (hash_equals($validCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generiert QR-Code URL für Google Authenticator
     */
    public static function getQRCodeUrl($secret, $email, $issuer = 'Serohub') {
        $label = urlencode($email);
        $issuer = urlencode($issuer);
        $secret = urlencode($secret);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
    }
    
    /**
     * Generiert QR-Code als Data URL (verwendet externen Service)
     */
    public static function getQRCodeImage($secret, $email, $issuer = 'Serohub', $size = 200) {
        $qrUrl = self::getQRCodeUrl($secret, $email, $issuer);
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($qrUrl);
        return $qrApiUrl;
    }
}
?>
