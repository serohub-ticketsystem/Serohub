<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/user_profile_fields.php';

header('Content-Type: application/json');

user_profile_fields_ensure_columns($pdo);

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Daten aus POST oder JSON
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
        }
        
        $updates = [];
        $updateParams = [':user_id' => $userId];
        
        // E-Mail aktualisieren
        if (isset($data['email'])) {
            $email = trim($data['email']);
            
            // E-Mail-Validierung
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige E-Mail-Adresse']);
                exit;
            }
            
            // Prüfen ob E-Mail bereits von anderem Benutzer verwendet wird
            $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
            $emailCheckStmt->bindValue(':email', $email);
            $emailCheckStmt->bindValue(':user_id', $userId);
            $emailCheckStmt->execute();
            
            if ($emailCheckStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet']);
                exit;
            }
            
            $updates[] = "email = :email";
            $updateParams[':email'] = $email;
        }
        
        // Vorname aktualisieren
        if (isset($data['vorname'])) {
            $updates[] = "vorname = :vorname";
            $updateParams[':vorname'] = trim($data['vorname']);
        }
        
        // Nachname aktualisieren
        if (isset($data['nachname'])) {
            $updates[] = "nachname = :nachname";
            $updateParams[':nachname'] = trim($data['nachname']);
        }
        
        // Telefonnummer aktualisieren
        if (isset($data['telefonnummer'])) {
            $updates[] = "telefonnummer = :telefonnummer";
            $updateParams[':telefonnummer'] = trim($data['telefonnummer']);
        }

        $profileExtra = user_profile_fields_build_updates($data, $pdo, $userId);
        if ($profileExtra['error'] !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $profileExtra['error']]);
            exit;
        }
        foreach ($profileExtra['updates'] as $sqlPart) {
            $updates[] = $sqlPart;
        }
        foreach ($profileExtra['params'] as $key => $value) {
            $updateParams[$key] = $value;
        }
        
        // Profilbild-Upload verarbeiten
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            
            // Datei-Validierung
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültiger Dateityp. Nur Bilder (JPEG, PNG, GIF, WebP) sind erlaubt.']);
                exit;
            }
            
            if ($file['size'] > $maxFileSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datei ist zu groß (max. 5MB)']);
                exit;
            }
            
            // Upload-Verzeichnis erstellen
            $uploadDir = dirname(__DIR__, 2) . '/uploads/users/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                // .htaccess für Sicherheit
                file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nDeny from all\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\nAllow from all\n</FilesMatch>");
            }
            
            // Dateiname sicher machen
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $fileName = 'user_' . $userId . '_' . $safeName . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            
            // Prüfen ob Verzeichnis beschreibbar ist
            if (!is_writable($uploadDir)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
                exit;
            }
            
            // Altes Profilbild löschen (falls vorhanden)
            $oldImageStmt = $pdo->prepare("SELECT logopfad FROM users WHERE id = ?");
            $oldImageStmt->execute([$userId]);
            $oldImage = $oldImageStmt->fetch(PDO::FETCH_ASSOC);
            if ($oldImage && !empty($oldImage['logopfad'])) {
                $oldImagePath = dirname(__DIR__, 2) . '/' . ltrim($oldImage['logopfad'], '/');
                if (file_exists($oldImagePath) && strpos($oldImagePath, '/uploads/users/profiles/') !== false) {
                    @unlink($oldImagePath);
                }
            }
            
            // Datei speichern
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                error_log("Profile Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei']);
                exit;
            }
            
            // Relativer Pfad für Datenbank
            $relativePath = 'uploads/users/profiles/' . $fileName;
            $updates[] = "logopfad = :logopfad";
            $updateParams[':logopfad'] = $relativePath;
        } elseif (!empty($data['avatar_type']) && $data['avatar_type'] === 'preset' && isset($data['preset_color']) && trim((string) $data['preset_color']) !== '') {
            $presetColor = trim((string) $data['preset_color']);
            if (!preg_match('/^#?[0-9A-Fa-f]{6}$/', $presetColor)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Farbangabe (nur Hex-Farben mit 6 Stellen erlaubt)']);
                exit;
            }
            if ($presetColor[0] !== '#') {
                $presetColor = '#' . $presetColor;
            }

            $initialsStmt = $pdo->prepare('SELECT vorname, nachname, email FROM users WHERE id = ?');
            $initialsStmt->execute([$userId]);
            $iu = $initialsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $fv = isset($data['vorname']) ? trim((string) $data['vorname']) : ($iu['vorname'] ?? '');
            $fn = isset($data['nachname']) ? trim((string) $data['nachname']) : ($iu['nachname'] ?? '');
            $em = isset($data['email']) ? trim((string) $data['email']) : ($iu['email'] ?? '');

            $initials = '';
            if ($fv !== '' && $fn !== '') {
                $initials = strtoupper(substr($fv, 0, 1) . substr($fn, 0, 1));
            } elseif ($em !== '') {
                $initials = strtoupper(substr($em, 0, 1));
            } else {
                $initials = 'U';
            }

            $oldImageStmt = $pdo->prepare('SELECT logopfad FROM users WHERE id = ?');
            $oldImageStmt->execute([$userId]);
            $oldImage = $oldImageStmt->fetch(PDO::FETCH_ASSOC);
            if ($oldImage && !empty($oldImage['logopfad']) && strpos($oldImage['logopfad'], 'preset:') !== 0) {
                $oldImagePath = dirname(__DIR__, 2) . '/' . ltrim($oldImage['logopfad'], '/');
                if (file_exists($oldImagePath) && strpos($oldImagePath, '/uploads/users/profiles/') !== false) {
                    @unlink($oldImagePath);
                }
            }

            $presetPath = 'preset:' . $presetColor . ':' . $initials;
            $updates[] = 'logopfad = :logopfad';
            $updateParams[':logopfad'] = $presetPath;
        }

        // Wenn es Updates gibt
        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(", ", $updates) . ", geaendert_datum = NOW() WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            
            foreach ($updateParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();

            if (array_key_exists('kontaktkanal', $data)) {
                user_profile_fields_sync_email_notifications_for_kontaktkanal(
                    $pdo,
                    $userId,
                    trim((string) $data['kontaktkanal'])
                );
            }
            
            // Aktualisierte Daten zurückgeben
            $extraSelect = user_profile_fields_select_extra_sql($pdo);
            $extraSelect = str_replace('u.', '', $extraSelect);
            $resultStmt = $pdo->prepare("
                SELECT id, email, vorname, nachname, telefonnummer, rolle, status, company_id, logopfad
                       {$extraSelect}
                FROM users
                WHERE id = ?
            ");
            $resultStmt->execute([$userId]);
            $updatedUser = $resultStmt->fetch(PDO::FETCH_ASSOC);
            
            // Preset-Avatar Informationen hinzufügen, falls vorhanden
            if ($updatedUser && !empty($updatedUser['logopfad']) && strpos($updatedUser['logopfad'], 'preset:') === 0) {
                $parts = explode(':', $updatedUser['logopfad']);
                $updatedUser['preset_color'] = isset($parts[1]) ? $parts[1] : null;
                $updatedUser['initials'] = isset($parts[2]) ? $parts[2] : '';
                
                // Initialen generieren falls nicht im Preset gespeichert
                if (empty($updatedUser['initials'])) {
                    if (!empty($updatedUser['vorname']) && !empty($updatedUser['nachname'])) {
                        $updatedUser['initials'] = strtoupper(substr($updatedUser['vorname'], 0, 1) . substr($updatedUser['nachname'], 0, 1));
                    } elseif (!empty($updatedUser['email'])) {
                        $updatedUser['initials'] = strtoupper(substr($updatedUser['email'], 0, 1));
                    } else {
                        $updatedUser['initials'] = 'U';
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Profil erfolgreich aktualisiert',
                'user' => $updatedUser
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Keine Daten zum Aktualisieren']);
        }
        
    } elseif ($method === 'GET') {
        // Aktuelle Profildaten abrufen
        $extraSelect = user_profile_fields_select_extra_sql($pdo);
        $extraSelect = str_replace('u.', '', $extraSelect);
        $stmt = $pdo->prepare("
            SELECT id, email, vorname, nachname, telefonnummer, rolle, status, company_id, logopfad
                   {$extraSelect}
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Profile API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Profile API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
