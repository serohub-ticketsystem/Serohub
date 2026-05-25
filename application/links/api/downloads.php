<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/customers/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
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
    $userCompanyId = $user['company_id'] ? (int)$user['company_id'] : null;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

$isAdminOrTechniker = ($userRole === 'Admin' || $userRole === 'Techniker');

// Upload-Verzeichnis für Dateien (absoluter Pfad) – Ordner "links" (engl.)
$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'links' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        if ($method === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Upload-Ordner konnte nicht erstellt werden']);
            exit;
        }
    }
}
if ($method === 'POST' && is_dir($uploadDir) && !is_writable($uploadDir)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Upload-Ordner ist nicht beschreibbar. Berechtigungen prüfen.']);
    exit;
}

/**
 * Prüft ob der aktuelle Benutzer eine Verknüpfung sehen darf.
 * Unterstützt intern, firmenweit und für alle.
 */
function userCanSeeDownload($download, $userCompanyId) {
    if ($download['sichtbar_fuer'] === 'alle') {
        return true;
    }
    if ($download['sichtbar_fuer'] === 'firma' && $userCompanyId && (int)$download['company_id'] === $userCompanyId) {
        return true;
    }
    return false;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT d.*,
                           u.vorname as ersteller_vorname, u.nachname as ersteller_nachname,
                           usr.vorname as user_vorname, usr.nachname as user_nachname,
                           c.name as company_name,
                           c.logo as company_logo,
                           cust.name as customer_name
                    FROM downloads d
                    LEFT JOIN users u ON d.erstellt_von = u.id
                    LEFT JOIN users usr ON d.user_id = usr.id
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN customers cust ON d.customer_id = cust.id
                    WHERE d.id = ?
                ");
                $stmt->execute([$id]);
                $download = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$download) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verknüpfung nicht gefunden']);
                    exit;
                }
                if (!$isAdminOrTechniker && (int)(isset($download['intern']) ? $download['intern'] : 0) === 1) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                if (!$isAdminOrTechniker && !userCanSeeDownload($download, $userCompanyId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                // Kunde und Firma entschlüsseln
                if (isset($download['company_name'])) {
                    $download['company_name'] = decrypt_from_db($download['company_name']);
                }
                if (isset($download['customer_name'])) {
                    $download['customer_name'] = decrypt_from_db($download['customer_name']);
                }
                
                echo json_encode(['success' => true, 'download' => $download]);
                exit;
            }

            $sql = "
                SELECT d.*,
                       u.vorname as ersteller_vorname, u.nachname as ersteller_nachname,
                       usr.vorname as user_vorname, usr.nachname as user_nachname, usr.email as user_email,
                       c.name as company_name,
                       c.logo as company_logo,
                       cust.name as customer_name
                FROM downloads d
                LEFT JOIN users u ON d.erstellt_von = u.id
                LEFT JOIN users usr ON d.user_id = usr.id
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN customers cust ON d.customer_id = cust.id
            ";
            $params = [];
            if (!$isAdminOrTechniker) {
                $sql .= " WHERE (COALESCE(d.intern, 0) = 0) AND (
                    d.sichtbar_fuer = 'alle'
                    OR (d.sichtbar_fuer = 'firma' AND d.company_id = :cid)
                )";
                $params[':cid'] = $userCompanyId;
            }
            $sql .= " ORDER BY d.erstellt_datum DESC";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            }
            $stmt->execute();
            $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kunde und Firma entschlüsseln
            foreach ($downloads as &$download) {
                if (isset($download['company_name'])) {
                    $download['company_name'] = decrypt_from_db($download['company_name']);
                }
                if (isset($download['customer_name'])) {
                    $download['customer_name'] = decrypt_from_db($download['customer_name']);
                }
            }
            unset($download);

            echo json_encode(['success' => true, 'downloads' => $downloads]);
            break;

        case 'POST':
            if (!$isAdminOrTechniker) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Techniker oder Admin können Verknüpfungen anlegen.']);
                exit;
            }

            $titel = null;
            $typ = null;
            $url = null;
            $sichtbar_fuer = 'alle';
            $target_company_id = null;

            if (!empty($_POST)) {
                $titel = isset($_POST['titel']) ? trim($_POST['titel']) : '';
                $typ = isset($_POST['typ']) ? ($_POST['typ'] === 'datei' ? 'datei' : 'link') : 'link';
                $url = isset($_POST['url']) ? trim($_POST['url']) : null;
                $target_company_id = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
                $intern = isset($_POST['intern']) && ($_POST['intern'] === '1' || $_POST['intern'] === 'true');
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                    exit;
                }
                $titel = isset($input['titel']) ? trim($input['titel']) : '';
                $typ = isset($input['typ']) ? ($input['typ'] === 'datei' ? 'datei' : 'link') : 'link';
                $url = isset($input['url']) ? trim($input['url']) : null;
                $target_company_id = isset($input['company_id']) && $input['company_id'] !== '' && $input['company_id'] !== null ? (int)$input['company_id'] : null;
                $intern = isset($input['intern']) && ($input['intern'] === true || $input['intern'] === '1' || $input['intern'] === 'true');
            }
            if (!isset($intern)) {
                $intern = 0;
            }
            $intern = (int)(bool)$intern;
            $requestedSichtbar = null;
            if (!empty($_POST)) {
                $requestedSichtbar = isset($_POST['sichtbar_fuer']) ? trim((string)$_POST['sichtbar_fuer']) : null;
            } else {
                $requestedSichtbar = isset($input['sichtbar_fuer']) ? trim((string)$input['sichtbar_fuer']) : null;
            }
            if ($intern === 1) {
                $sichtbar_fuer = 'alle';
            } else {
                if (!in_array($requestedSichtbar, ['alle', 'firma'], true)) {
                    $requestedSichtbar = null;
                }
                $sichtbar_fuer = $requestedSichtbar ?: ($target_company_id ? 'firma' : 'alle');
            }

            if ($titel === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel ist erforderlich']);
                exit;
            }
            if ($sichtbar_fuer === 'firma' && !$target_company_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Firma ist für firmenweite Verknüpfungen erforderlich']);
                exit;
            }
            if ($sichtbar_fuer === 'alle') {
                $target_company_id = null;
            }

            $dateipfad = null;
            $dateiname = null;

            if ($typ === 'datei') {
                $uploadError = null;
                if (empty($_FILES['datei']) || !isset($_FILES['datei']['error'])) {
                    $uploadError = 'Keine Datei empfangen. Bitte wählen Sie eine Datei aus.';
                } elseif ($_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
                    $codes = [
                        UPLOAD_ERR_INI_SIZE => 'Datei ist zu groß (Server-Limit).',
                        UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
                        UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
                        UPLOAD_ERR_NO_FILE => 'Es wurde keine Datei ausgewählt.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt auf dem Server.',
                        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht auf den Server geschrieben werden.',
                        UPLOAD_ERR_EXTENSION => 'Upload durch Server-Erweiterung gestoppt.',
                    ];
                    $uploadError = $codes[$_FILES['datei']['error']] ?? 'Upload-Fehler (Code ' . (int)$_FILES['datei']['error'] . ').';
                } elseif (empty($_FILES['datei']['tmp_name']) || !is_uploaded_file($_FILES['datei']['tmp_name'])) {
                    $uploadError = 'Datei-Upload ungültig.';
                }
                if ($uploadError !== null) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $uploadError]);
                    exit;
                }
                $file = $_FILES['datei'];
                $safeName = 'link_' . $userId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
                if ($safeName === 'link_' . $userId . '_' . time() . '_') {
                    $safeName .= 'datei';
                }
                $relPath = 'uploads/links/' . $safeName;
                $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
                if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden. Ordner-Berechtigung prüfen.']);
                    exit;
                }
                $dateipfad = $relPath;
                $dateiname = $file['name'];
            } else {
                if (empty($url)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'URL ist bei Link-Verknüpfungen erforderlich']);
                    exit;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO downloads (titel, typ, url, dateipfad, dateiname, sichtbar_fuer, user_id, company_id, customer_id, intern, erstellt_von)
                VALUES (:titel, :typ, :url, :dateipfad, :dateiname, :sichtbar_fuer, :user_id, :company_id, :customer_id, :intern, :erstellt_von)
            ");
            $stmt->execute([
                ':titel' => $titel,
                ':typ' => $typ,
                ':url' => $typ === 'link' ? $url : null,
                ':dateipfad' => $dateipfad,
                ':dateiname' => $dateiname,
                ':sichtbar_fuer' => $sichtbar_fuer,
                ':user_id' => null,
                ':company_id' => $target_company_id,
                ':customer_id' => null,
                ':intern' => $intern,
                ':erstellt_von' => $userId
            ]);
            $newId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT d.*, u.vorname as ersteller_vorname, u.nachname as ersteller_nachname,
                       usr.vorname as user_vorname, usr.nachname as user_nachname,
                       c.name as company_name, c.logo as company_logo, cust.name as customer_name
                FROM downloads d
                LEFT JOIN users u ON d.erstellt_von = u.id
                LEFT JOIN users usr ON d.user_id = usr.id
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN customers cust ON d.customer_id = cust.id
                WHERE d.id = ?
            ");
            $stmt->execute([$newId]);
            $download = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kunde und Firma entschlüsseln
            if (isset($download['company_name'])) {
                $download['company_name'] = decrypt_from_db($download['company_name']);
            }
            if (isset($download['customer_name'])) {
                $download['customer_name'] = decrypt_from_db($download['customer_name']);
            }

            echo json_encode(['success' => true, 'message' => 'Verknüpfung angelegt', 'download' => $download]);
            break;

        case 'PUT':
            if (!$isAdminOrTechniker) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID erforderlich']);
                exit;
            }
            $id = (int)$input['id'];
            $titel = isset($input['titel']) ? trim($input['titel']) : '';
            $typ = isset($input['typ']) ? ($input['typ'] === 'datei' ? 'datei' : 'link') : 'link';
            $url = isset($input['url']) ? trim($input['url']) : null;
            $target_company_id = isset($input['company_id']) && $input['company_id'] !== '' && $input['company_id'] !== null ? (int)$input['company_id'] : null;
            $intern = isset($input['intern']) && ($input['intern'] === true || $input['intern'] === '1' || $input['intern'] === 'true');
            $intern = (int)(bool)$intern;
            $requestedSichtbar = isset($input['sichtbar_fuer']) ? trim((string)$input['sichtbar_fuer']) : null;
            if ($intern === 1) {
                $sichtbar_fuer = 'alle';
            } else {
                if (!in_array($requestedSichtbar, ['alle', 'firma'], true)) {
                    $requestedSichtbar = null;
                }
                $sichtbar_fuer = $requestedSichtbar ?: ($target_company_id ? 'firma' : 'alle');
            }

            if ($titel === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Titel ist erforderlich']);
                exit;
            }
            if ($sichtbar_fuer === 'firma' && !$target_company_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Firma ist für firmenweite Verknüpfungen erforderlich']);
                exit;
            }
            if ($sichtbar_fuer === 'alle') {
                $target_company_id = null;
            }

            $stmt = $pdo->prepare("SELECT id, typ, dateipfad FROM downloads WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Verknüpfung nicht gefunden']);
                exit;
            }

            if ($typ === 'link') {
                if (empty($url)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'URL ist bei Link-Verknüpfungen erforderlich']);
                    exit;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE downloads SET
                    titel = :titel, typ = :typ, url = :url,
                    sichtbar_fuer = :sichtbar_fuer, user_id = :user_id, company_id = :company_id, customer_id = :customer_id, intern = :intern
                WHERE id = :id
            ");
            $stmt->execute([
                ':titel' => $titel,
                ':typ' => $typ,
                ':url' => $typ === 'link' ? $url : null,
                ':sichtbar_fuer' => $sichtbar_fuer,
                ':user_id' => null,
                ':company_id' => $target_company_id,
                ':customer_id' => null,
                ':intern' => $intern,
                ':id' => $id
            ]);

            $stmt = $pdo->prepare("
                SELECT d.*, u.vorname as ersteller_vorname, u.nachname as ersteller_nachname,
                       usr.vorname as user_vorname, usr.nachname as user_nachname,
                       c.name as company_name, c.logo as company_logo, cust.name as customer_name
                FROM downloads d
                LEFT JOIN users u ON d.erstellt_von = u.id
                LEFT JOIN users usr ON d.user_id = usr.id
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN customers cust ON d.customer_id = cust.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $download = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kunde und Firma entschlüsseln
            if (isset($download['company_name'])) {
                $download['company_name'] = decrypt_from_db($download['company_name']);
            }
            if (isset($download['customer_name'])) {
                $download['customer_name'] = decrypt_from_db($download['customer_name']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Verknüpfung aktualisiert', 'download' => $download]);
            break;

        case 'DELETE':
            if (!$isAdminOrTechniker) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, dateipfad FROM downloads WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Verknüpfung nicht gefunden']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM downloads WHERE id = ?");
            $stmt->execute([$id]);
            if ($row['dateipfad']) {
                // Unterstütze beide Pfade (uploads/downloads und uploads/links) für Migration
                $baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
                $fullPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $row['dateipfad']);
                if (!file_exists($fullPath)) {
                    $legacyPath = str_replace('uploads/links/', 'uploads/downloads/', $row['dateipfad']);
                    $fullPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $legacyPath);
                }
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Verknüpfung gelöscht']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Links API PDO: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Links API: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
