<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/service_log_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    $userRole = $user['rolle'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

function canAccessProject($pdo, $projectId, $userId, $userRole) {
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$projectId]);
    return $stmt->fetch() && ($userRole === 'Admin' || $userRole === 'Techniker');
}

$uploadBaseDir = dirname(__DIR__, 2) . '/uploads/projects/';
if (!file_exists($uploadBaseDir)) {
    mkdir($uploadBaseDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            $attachmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $download = isset($_GET['download']) && $_GET['download'];
            if (!$attachmentId || !$download) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id und download=1 erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT pa.* FROM project_attachments pa WHERE pa.id = ?");
            $stmt->execute([$attachmentId]);
            $att = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$att || !canAccessProject($pdo, $att['project_id'], $userId, $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anhang nicht gefunden']);
                exit;
            }
            $fullPath = dirname(__DIR__, 2) . '/' . $att['dateipfad'];
            if (!file_exists($fullPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Datei nicht gefunden']);
                exit;
            }
            header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($att['dateiname']) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;

        case 'POST':
            if (!isset($_POST['project_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'project_id fehlt']);
                exit;
            }
            $projectId = (int)$_POST['project_id'];
            if (!canAccessProject($pdo, $projectId, $userId, $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden']);
                exit;
            }
            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
                exit;
            }
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errMsg = [UPLOAD_ERR_INI_SIZE => 'Datei zu groß', UPLOAD_ERR_FORM_SIZE => 'Datei zu groß', UPLOAD_ERR_PARTIAL => 'Nur teilweise hochgeladen', UPLOAD_ERR_NO_FILE => 'Keine Datei', UPLOAD_ERR_NO_TMP_DIR => 'Temp-Verzeichnis fehlt', UPLOAD_ERR_CANT_WRITE => 'Schreibfehler', UPLOAD_ERR_EXTENSION => 'Upload gestoppt'];
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $errMsg[$file['error']] ?? 'Upload-Fehler']);
                exit;
            }
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datei zu groß (max. 10MB)']);
                exit;
            }
            $originalName = $file['name'];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $fileName = 'project_' . $projectId . '_' . $safeName . '_' . time() . '.' . $ext;
            $relPath = 'uploads/projects/' . $fileName;
            $fullPath = dirname(__DIR__, 2) . '/' . $relPath;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden']);
                exit;
            }
            $mimeType = $file['type'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO project_attachments (project_id, dateiname, dateipfad, dateigroesse, mime_type, erstellt_von) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $originalName, $relPath, (int)$file['size'], $mimeType, $userId]);
            $id = (int)$pdo->lastInsertId();
            if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $projectId, 'created', 'project_attachment', (string)$id, null, 'Projekt-Dokument hochgeladen');
            echo json_encode(['success' => true, 'id' => $id, 'dateiname' => $originalName]);
            break;

        case 'DELETE':
            $attachmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$attachmentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT pa.id, pa.project_id, pa.dateipfad FROM project_attachments pa WHERE pa.id = ?");
            $stmt->execute([$attachmentId]);
            $att = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$att || !canAccessProject($pdo, $att['project_id'], $userId, $userRole)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anhang nicht gefunden']);
                exit;
            }
            $pdo->prepare("DELETE FROM project_attachments WHERE id = ?")->execute([$attachmentId]);
            $fullPath = dirname(__DIR__, 2) . '/' . $att['dateipfad'];
            if (file_exists($fullPath)) @unlink($fullPath);
            if (function_exists('service_log')) service_log($pdo, $userId, 'sonstiges', $att['project_id'], 'deleted', 'project_attachment', (string)$attachmentId, null, 'Projekt-Dokument gelöscht');
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
