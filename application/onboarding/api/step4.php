<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $stepCheck = $pdo->prepare('SELECT letztes_pw_change, vorname, nachname FROM users WHERE id = ?');
    $stepCheck->execute([$userId]);
    $stepUser = $stepCheck->fetch(PDO::FETCH_ASSOC);

    if (!$stepUser || empty($stepUser['letztes_pw_change']) || empty($_SESSION['onboarding_profile_step_seen'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte schließen Sie zuerst die vorherigen Schritte ab.']);
        exit;
    }

    if (empty($_SESSION['onboarding_contact_step_seen'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte schließen Sie zuerst Schritt 3 ab.']);
        exit;
    }

    $skip = isset($_POST['skip']) && $_POST['skip'] == '1';
    $avatarType = $_POST['avatar_type'] ?? null;
    $presetColor = $_POST['preset_color'] ?? null;

    $response = [
        'success' => true,
        'message' => 'Onboarding abgeschlossen',
    ];

    if (!$skip && $avatarType === 'preset' && $presetColor) {
        $initialsStmt = $pdo->prepare('SELECT vorname, nachname, email FROM users WHERE id = ?');
        $initialsStmt->execute([$userId]);
        $initialsUser = $initialsStmt->fetch(PDO::FETCH_ASSOC);

        $initials = '';
        if (!empty($initialsUser['vorname']) && !empty($initialsUser['nachname'])) {
            $initials = strtoupper(substr($initialsUser['vorname'], 0, 1) . substr($initialsUser['nachname'], 0, 1));
        } elseif (!empty($initialsUser['email'])) {
            $initials = strtoupper(substr($initialsUser['email'], 0, 1));
        } else {
            $initials = 'U';
        }

        $presetAvatarPath = 'preset:' . $presetColor . ':' . $initials;
        $updateStmt = $pdo->prepare('UPDATE users SET logopfad = ?, geaendert_datum = NOW() WHERE id = ?');
        $updateStmt->execute([$presetAvatarPath, $userId]);

        $resultStmt = $pdo->prepare('SELECT id, email, vorname, nachname, rolle, status, company_id, logopfad FROM users WHERE id = ?');
        $resultStmt->execute([$userId]);
        $updatedUser = $resultStmt->fetch(PDO::FETCH_ASSOC);
        $updatedUser['preset_color'] = $presetColor;
        $updatedUser['initials'] = $initials;

        $response['message'] = 'Avatar erfolgreich gespeichert';
        $response['user'] = $updatedUser;
    } elseif (!$skip && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültiger Dateityp. Nur Bilder (JPEG, PNG, GIF, WebP) sind erlaubt.']);
            exit;
        }

        if ($file['size'] > $maxFileSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datei ist zu groß (max. 5MB)']);
            exit;
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/users/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nDeny from all\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\nAllow from all\n</FilesMatch>");
        }

        if (!is_writable($uploadDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
            exit;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fileName = 'user_' . $userId . '_' . $safeName . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        $oldImageStmt = $pdo->prepare('SELECT logopfad FROM users WHERE id = ?');
        $oldImageStmt->execute([$userId]);
        $oldImage = $oldImageStmt->fetch(PDO::FETCH_ASSOC);
        if ($oldImage && !empty($oldImage['logopfad'])) {
            $oldImagePath = dirname(__DIR__, 2) . '/' . ltrim($oldImage['logopfad'], '/');
            if (file_exists($oldImagePath) && strpos($oldImagePath, '/uploads/users/profiles/') !== false) {
                @unlink($oldImagePath);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            http_response_code(500);
            error_log('Onboarding Step 4 Upload-Fehler: Konnte Datei nicht nach ' . $filePath . ' verschieben');
            echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei']);
            exit;
        }

        $relativePath = 'uploads/users/profiles/' . $fileName;
        $updateStmt = $pdo->prepare('UPDATE users SET logopfad = ?, geaendert_datum = NOW() WHERE id = ?');
        $updateStmt->execute([$relativePath, $userId]);

        $resultStmt = $pdo->prepare('SELECT id, email, vorname, nachname, rolle, status, company_id, logopfad FROM users WHERE id = ?');
        $resultStmt->execute([$userId]);
        $updatedUser = $resultStmt->fetch(PDO::FETCH_ASSOC);

        $response['message'] = 'Avatar erfolgreich gespeichert';
        $response['user'] = $updatedUser;
    } elseif ($skip) {
        $response['message'] = 'Avatar-Schritt übersprungen';
    }

    $_SESSION['onboarding_avatar_step_seen'] = true;

    $completeStmt = $pdo->prepare('UPDATE users SET onboarding_abgeschlossen = 1, geaendert_datum = NOW() WHERE id = ?');
    $completeStmt->execute([$userId]);

    $response['redirect'] = BASE_URL . 'dashboard/';

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Onboarding Step 4 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Onboarding Step 4 API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
