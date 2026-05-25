<?php
// Output-Buffering starten, um sicherzustellen, dass nur JSON ausgegeben wird
ob_start();

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fehlerbehandlung: Nur JSON ausgeben
try {
    require_once dirname(__DIR__, 2) . '/assets/config.php';
    require_once dirname(__DIR__, 2) . '/assets/totp.php';
    require_once dirname(__DIR__, 2) . '/assets/email.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Laden der Abhängigkeiten: ' . $e->getMessage()
    ]);
    exit;
}

// Nur für eingeloggte Benutzer
if (!isset($_SESSION['user_id'])) {
    ob_clean(); // Sicherstellen, dass nur JSON ausgegeben wird
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Benutzer-Daten abrufen
    $stmt = $pdo->prepare("SELECT email, vorname, nachname FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Benutzer nicht gefunden');
    }
    
    $userEmail = $user['email'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = $userEmail;
    }
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'generate_secret':
                // Neues Secret generieren
                try {
                    $secret = TOTP::generateSecret();
                    
                    if (empty($secret)) {
                        throw new Exception('Fehler beim Generieren des Secrets');
                    }
                    
                    // QR-Code URL und otpauth-Link generieren
                    $qrCodeUrl = TOTP::getQRCodeImage($secret, $userEmail, 'Serohub', 200);
                    $otpauthUrl = TOTP::getQRCodeUrl($secret, $userEmail, 'Serohub');
                    
                    if (empty($qrCodeUrl)) {
                        throw new Exception('Fehler beim Generieren des QR-Codes');
                    }
                    
                    ob_clean(); // Sicherstellen, dass nur JSON ausgegeben wird
                    echo json_encode([
                        'success' => true,
                        'secret' => $secret,
                        'qr_code_url' => $qrCodeUrl,
                        'otpauth_url' => $otpauthUrl
                    ]);
                } catch (Exception $e) {
                    throw new Exception('Fehler beim Generieren des Secrets: ' . $e->getMessage());
                }
                break;
                
            case 'enable':
                // 2FA aktivieren
                $secret = $input['secret'] ?? '';
                $code = $input['code'] ?? '';
                
                if (empty($secret) || empty($code)) {
                    throw new Exception('Secret und Code sind erforderlich');
                }
                
                // Code validieren
                if (!TOTP::verifyCode($secret, $code)) {
                    throw new Exception('Ungültiger Code. Bitte stelle sicher, dass deine Authenticator-App die richtige Zeit verwendet.');
                }
                
                // Secret und aktiviert-Status in Datenbank speichern
                $pdo->beginTransaction();
                
                try {
                    // Secret speichern
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value) 
                        VALUES (?, '2fa_secret', ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), geaendert_datum = NOW()
                    ");
                    $stmt->execute([$userId, $secret]);
                    
                    // Aktiviert-Status speichern
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value) 
                        VALUES (?, '2fa_enabled', '1')
                        ON DUPLICATE KEY UPDATE setting_value = '1', geaendert_datum = NOW()
                    ");
                    $stmt->execute([$userId]);
                    
                    $pdo->commit();
                    
                    // E-Mail-Benachrichtigung senden (mit Template falls vorhanden)
                    try {
                        // Versuche Template-ID aus system_settings zu finden
                        $templateFound = false;
                        try {
                            // Zuerst nach zugewiesener Template-ID in system_settings suchen
                            $settingStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
                            $settingStmt->execute(['email_template_2fa_enabled']);
                            $templateSetting = $settingStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($templateSetting && !empty($templateSetting['setting_value'])) {
                                $templateId = (int)$templateSetting['setting_value'];
                                
                                // Template aus email_templates laden
                                $templateStmt = $pdo->prepare("SELECT id, subject, body FROM email_templates WHERE id = ? LIMIT 1");
                                $templateStmt->execute([$templateId]);
                                $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($template) {
                                    // Template gefunden - verwende Template mit Variablen
                                    $variables = [
                                        'userName' => htmlspecialchars($userName),
                                        'email' => htmlspecialchars($userEmail)
                                    ];
                                    
                                    $subject = $template['subject'];
                                    $message = $template['body'];
                                    
                                    // Variablen ersetzen
                                    foreach ($variables as $key => $value) {
                                        $subject = str_replace('{{' . $key . '}}', $value, $subject);
                                        $message = str_replace('{{' . $key . '}}', $value, $message);
                                    }
                                    
                                    $templateFound = true;
                                }
                            }
                            
                            // Fallback: Suche nach Template mit Name "2FA Aktiviert"
                            if (!$templateFound) {
                                $templateStmt = $pdo->prepare("SELECT id, subject, body FROM email_templates WHERE name = ? LIMIT 1");
                                $templateStmt->execute(['2FA Aktiviert']);
                                $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($template) {
                                    // Template gefunden - verwende Template mit Variablen
                                    $variables = [
                                        'userName' => htmlspecialchars($userName),
                                        'email' => htmlspecialchars($userEmail)
                                    ];
                                    
                                    $subject = $template['subject'];
                                    $message = $template['body'];
                                    
                                    // Variablen ersetzen
                                    foreach ($variables as $key => $value) {
                                        $subject = str_replace('{{' . $key . '}}', $value, $subject);
                                        $message = str_replace('{{' . $key . '}}', $value, $message);
                                    }
                                    
                                    $templateFound = true;
                                }
                            }
                        } catch (Exception $templateError) {
                            error_log("2FA Template Fehler: " . $templateError->getMessage());
                        }
                        
                        // Falls kein Template gefunden, verwende Standard-HTML
                        if (!$templateFound) {
                            $subject = '2FA erfolgreich aktiviert - Serohub';
                            $message = "
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
                                .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                                .success-box { background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
                                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Serohub</h1>
                                </div>
                                <div class='content'>
                                    <h2>2FA erfolgreich aktiviert</h2>
                                    <p>Hallo " . htmlspecialchars($userName) . ",</p>
                                    <div class='success-box'>
                                        <strong>✓ Die Zwei-Faktor-Authentifizierung wurde erfolgreich für dein Konto aktiviert.</strong>
                                    </div>
                                    <p>Dein Konto ist jetzt besser geschützt. Beim nächsten Login wirst du zusätzlich zu deinem Passwort einen Code aus deiner Authenticator-App eingeben müssen.</p>
                                    <p><strong>Wichtige Hinweise:</strong></p>
                                    <ul>
                                        <li>Bewahre deine Authenticator-App sicher auf</li>
                                        <li>Verwende niemals deine 2FA-Codes mit anderen teilen</li>
                                        <li>Falls du dein Gerät verlierst, kontaktiere bitte den Administrator</li>
                                    </ul>
                                    <p>Falls du diese Aktivierung nicht durchgeführt hast, kontaktiere bitte umgehend den Administrator.</p>
                                    <p>Mit freundlichen Grüßen,<br>Das Serohub Team</p>
                                </div>
                                <div class='footer'>
                                    <p>Diese E-Mail wurde automatisch generiert. Bitte antworte nicht auf diese E-Mail.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        }
                        
                        sendEmail($userEmail, $subject, $message, true, null, null, null, 'Sicherheit · 2FA');
                    } catch (Exception $emailError) {
                        // E-Mail-Fehler nicht an API-Response weitergeben, nur loggen
                        error_log("2FA Aktivierung E-Mail Fehler: " . $emailError->getMessage());
                    }
                    
                    ob_clean(); // Sicherstellen, dass nur JSON ausgegeben wird
                    echo json_encode([
                        'success' => true,
                        'message' => '2FA wurde erfolgreich aktiviert'
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'disable':
                // 2FA deaktivieren
                $code = $input['code'] ?? '';
                
                if (empty($code)) {
                    throw new Exception('Code ist erforderlich');
                }
                
                // Secret aus Datenbank abrufen
                $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_secret'");
                $stmt->execute([$userId]);
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$setting || empty($setting['setting_value'])) {
                    throw new Exception('2FA ist nicht aktiviert');
                }
                
                $secret = $setting['setting_value'];
                
                // Code validieren
                if (!TOTP::verifyCode($secret, $code)) {
                    throw new Exception('Ungültiger Code. Bitte stelle sicher, dass deine Authenticator-App die richtige Zeit verwendet.');
                }
                
                // 2FA deaktivieren
                $pdo->beginTransaction();
                
                try {
                    // Aktiviert-Status deaktivieren
                    $stmt = $pdo->prepare("
                        UPDATE user_settings 
                        SET setting_value = '0', geaendert_datum = NOW()
                        WHERE user_id = ? AND setting_key = '2fa_enabled'
                    ");
                    $stmt->execute([$userId]);
                    
                    // Secret löschen (optional, für mehr Sicherheit)
                    $stmt = $pdo->prepare("
                        DELETE FROM user_settings 
                        WHERE user_id = ? AND setting_key = '2fa_secret'
                    ");
                    $stmt->execute([$userId]);
                    
                    $pdo->commit();
                    
                    // E-Mail-Benachrichtigung senden (mit Template falls vorhanden)
                    try {
                        // Versuche Template-ID aus system_settings zu finden
                        $templateFound = false;
                        try {
                            // Zuerst nach zugewiesener Template-ID in system_settings suchen
                            $settingStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
                            $settingStmt->execute(['email_template_2fa_disabled']);
                            $templateSetting = $settingStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($templateSetting && !empty($templateSetting['setting_value'])) {
                                $templateId = (int)$templateSetting['setting_value'];
                                
                                // Template aus email_templates laden
                                $templateStmt = $pdo->prepare("SELECT id, subject, body FROM email_templates WHERE id = ? LIMIT 1");
                                $templateStmt->execute([$templateId]);
                                $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($template) {
                                    // Template gefunden - verwende Template mit Variablen
                                    $variables = [
                                        'userName' => htmlspecialchars($userName),
                                        'email' => htmlspecialchars($userEmail)
                                    ];
                                    
                                    $subject = $template['subject'];
                                    $message = $template['body'];
                                    
                                    // Variablen ersetzen
                                    foreach ($variables as $key => $value) {
                                        $subject = str_replace('{{' . $key . '}}', $value, $subject);
                                        $message = str_replace('{{' . $key . '}}', $value, $message);
                                    }
                                    
                                    $templateFound = true;
                                }
                            }
                            
                            // Fallback: Suche nach Template mit Name "2FA Deaktiviert"
                            if (!$templateFound) {
                                $templateStmt = $pdo->prepare("SELECT id, subject, body FROM email_templates WHERE name = ? LIMIT 1");
                                $templateStmt->execute(['2FA Deaktiviert']);
                                $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($template) {
                                    // Template gefunden - verwende Template mit Variablen
                                    $variables = [
                                        'userName' => htmlspecialchars($userName),
                                        'email' => htmlspecialchars($userEmail)
                                    ];
                                    
                                    $subject = $template['subject'];
                                    $message = $template['body'];
                                    
                                    // Variablen ersetzen
                                    foreach ($variables as $key => $value) {
                                        $subject = str_replace('{{' . $key . '}}', $value, $subject);
                                        $message = str_replace('{{' . $key . '}}', $value, $message);
                                    }
                                    
                                    $templateFound = true;
                                }
                            }
                        } catch (Exception $templateError) {
                            error_log("2FA Template Fehler: " . $templateError->getMessage());
                        }
                        
                        // Falls kein Template gefunden, verwende Standard-HTML
                        if (!$templateFound) {
                            $subject = '2FA deaktiviert - Serohub';
                            $message = "
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
                                .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                                .warning-box { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Serohub</h1>
                                </div>
                                <div class='content'>
                                    <h2>2FA wurde deaktiviert</h2>
                                    <p>Hallo " . htmlspecialchars($userName) . ",</p>
                                    <div class='warning-box'>
                                        <strong>⚠ Die Zwei-Faktor-Authentifizierung wurde für dein Konto deaktiviert.</strong>
                                    </div>
                                    <p>Dein Konto ist jetzt weniger geschützt, da die zusätzliche Sicherheitsebene entfernt wurde.</p>
                                    <p><strong>Sicherheitshinweis:</strong></p>
                                    <ul>
                                        <li>Wir empfehlen dringend, 2FA erneut zu aktivieren</li>
                                        <li>Verwende ein starkes, eindeutiges Passwort</li>
                                        <li>Falls du diese Deaktivierung nicht durchgeführt hast, kontaktiere bitte umgehend den Administrator</li>
                                    </ul>
                                    <p>Mit freundlichen Grüßen,<br>Das Serohub Team</p>
                                </div>
                                <div class='footer'>
                                    <p>Diese E-Mail wurde automatisch generiert. Bitte antworte nicht auf diese E-Mail.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        }
                        
                        sendEmail($userEmail, $subject, $message, true, null, null, null, 'Sicherheit · 2FA');
                    } catch (Exception $emailError) {
                        // E-Mail-Fehler nicht an API-Response weitergeben, nur loggen
                        error_log("2FA Deaktivierung E-Mail Fehler: " . $emailError->getMessage());
                    }
                    
                    ob_clean(); // Sicherstellen, dass nur JSON ausgegeben wird
                    echo json_encode([
                        'success' => true,
                        'message' => '2FA wurde erfolgreich deaktiviert'
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                throw new Exception('Unbekannte Aktion');
        }
    } else if ($method === 'GET') {
        // 2FA-Status abrufen
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = '2fa_enabled'");
        $stmt->execute([$userId]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $enabled = $setting && $setting['setting_value'] === '1';
        
        ob_clean(); // Sicherstellen, dass nur JSON ausgegeben wird
        echo json_encode([
            'success' => true,
            'enabled' => $enabled
        ]);
    } else {
        throw new Exception('Methode nicht erlaubt');
    }
} catch (Exception $e) {
    error_log("2FA API Error: " . $e->getMessage());
    ob_clean(); // Alle bisherigen Ausgaben löschen
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("2FA API PDO Error: " . $e->getMessage());
    ob_clean(); // Alle bisherigen Ausgaben löschen
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler aufgetreten'
    ]);
} catch (Error $e) {
    error_log("2FA API PHP Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    ob_clean(); // Alle bisherigen Ausgaben löschen
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ein unerwarteter Fehler ist aufgetreten'
    ]);
}

// Output-Buffer ausgeben und leeren
ob_end_flush();
?>
