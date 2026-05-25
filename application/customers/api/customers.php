<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__) . '/helper/encryption.php';
require_once dirname(__DIR__, 2) . '/companies/helper/encryption.php';

header('Content-Type: application/json');

function json_response(array $payload, ?int $statusCode = null): void {
    if ($statusCode !== null) {
        http_response_code($statusCode);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        error_log('Customers API JSON encode failed: ' . json_last_error_msg());
        http_response_code(500);
        echo '{"success":false,"error":"Antwort konnte nicht serialisiert werden"}';
        return;
    }

    echo $json;
}

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, vorname, nachname FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userName = trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? ''));
    if (empty($userName)) {
        $userName = 'Unbekannt';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Logo-Upload-Verzeichnis
$logoUploadDir = dirname(__DIR__, 2) . '/uploads/images/';
if (!is_dir($logoUploadDir)) {
    mkdir($logoUploadDir, 0755, true);
}

try {
    switch ($method) {
        case 'GET':
            // Benutzer eines Kunden abrufen
            if (isset($_GET['customer_id']) && isset($_GET['users'])) {
                $customerId = (int)$_GET['customer_id'];
                
                // Prüfen ob Kunde existiert
                $checkStmt = $pdo->prepare("SELECT id, company_id FROM customers WHERE id = ?");
                $checkStmt->execute([$customerId]);
                $customer = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$customer) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $customer['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                // Prüfen ob logopfad Spalte existiert
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM users");
                    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $hasLogopfad = in_array('logopfad', $columns);
                } catch (PDOException $e) {
                    $hasLogopfad = false;
                }
                
                $selectFields = [
                    'u.id',
                    'u.email',
                    'u.vorname',
                    'u.nachname',
                    'u.rolle',
                    'u.status',
                    'u.erstellt_datum',
                    'u.letzte_anmeldung'
                ];
                
                if ($hasLogopfad) {
                    $selectFields[] = 'u.logopfad';
                }
                
                $sql = "
                    SELECT " . implode(', ', $selectFields) . "
                    FROM users u
                    WHERE u.customer_id = :customer_id
                    ORDER BY u.nachname, u.vorname
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Standardwert für fehlendes Feld setzen
                foreach ($users as &$user) {
                    if (!$hasLogopfad) {
                        $user['logopfad'] = null;
                    }
                }
                unset($user);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users
                ]);
                exit;
            }
            
            // Einzelnen Kunden abrufen
            if (isset($_GET['id'])) {
                $customerId = (int)$_GET['id'];
                
                // Prüfen welche Spalten existieren
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $hasLogo = in_array('logo', $columns);
                    $hasLieferadresse = in_array('lieferadresse', $columns);
                    $hasLieferPlz = in_array('liefer_plz', $columns);
                    $hasLieferOrt = in_array('liefer_ort', $columns);
                    $hasRechnungsAdresse = in_array('rechnungs_adresse', $columns);
                    $hasRechnungsPlz = in_array('rechnungs_plz', $columns);
                    $hasRechnungsOrt = in_array('rechnungs_ort', $columns);
                    $hasRechnungsEmail = in_array('rechnungs_email', $columns);
                    $hasAnsprechpartnerUserId = in_array('ansprechpartner_user_id', $columns);
                    $hasAnsprechpartnerManuellName = in_array('ansprechpartner_manuell_name', $columns);
                    $hasAnsprechpartnerManuellEmail = in_array('ansprechpartner_manuell_email', $columns);
                    $hasAnsprechpartnerManuellTelefon = in_array('ansprechpartner_manuell_telefon', $columns);
                    $hasAnsprechpartnerManuellNotiz = in_array('ansprechpartner_manuell_notiz', $columns);
                } catch (PDOException $e) {
                    $hasLogo = false;
                    $hasLieferadresse = false;
                    $hasLieferPlz = false;
                    $hasLieferOrt = false;
                    $hasRechnungsAdresse = false;
                    $hasRechnungsPlz = false;
                    $hasRechnungsOrt = false;
                    $hasRechnungsEmail = false;
                }
                
                $selectFields = [
                    'c.id',
                    'c.name',
                    'c.kundennummer',
                    'c.email',
                    'c.telefon',
                    'c.adresse',
                    'c.plz',
                    'c.ort',
                    'c.notizen',
                    'c.status',
                    'c.company_id',
                    'c.erstellt_datum',
                    'c.geaendert_datum',
                    'comp.name as company_name'
                ];
                
                if ($hasLogo) {
                    $selectFields[] = 'c.logo';
                }
                if ($hasLieferadresse) {
                    $selectFields[] = 'c.lieferadresse';
                }
                if ($hasLieferPlz) {
                    $selectFields[] = 'c.liefer_plz';
                }
                if ($hasLieferOrt) {
                    $selectFields[] = 'c.liefer_ort';
                }
                if ($hasRechnungsAdresse) {
                    $selectFields[] = 'c.rechnungs_adresse';
                }
                if ($hasRechnungsPlz) {
                    $selectFields[] = 'c.rechnungs_plz';
                }
                if ($hasRechnungsOrt) {
                    $selectFields[] = 'c.rechnungs_ort';
                }
                if ($hasRechnungsEmail) {
                    $selectFields[] = 'c.rechnungs_email';
                }
                if ($hasAnsprechpartnerUserId) {
                    $selectFields[] = 'c.ansprechpartner_user_id';
                    $selectFields[] = 'u_ansprechpartner.vorname as ansprechpartner_vorname';
                    $selectFields[] = 'u_ansprechpartner.nachname as ansprechpartner_nachname';
                    $selectFields[] = 'u_ansprechpartner.email as ansprechpartner_email';
                }
                if ($hasAnsprechpartnerManuellName) {
                    $selectFields[] = 'c.ansprechpartner_manuell_name';
                }
                if ($hasAnsprechpartnerManuellEmail) {
                    $selectFields[] = 'c.ansprechpartner_manuell_email';
                }
                if ($hasAnsprechpartnerManuellTelefon) {
                    $selectFields[] = 'c.ansprechpartner_manuell_telefon';
                }
                if ($hasAnsprechpartnerManuellNotiz) {
                    $selectFields[] = 'c.ansprechpartner_manuell_notiz';
                }
                
                $joins = [];
                if ($hasAnsprechpartnerUserId) {
                    $joins[] = "LEFT JOIN users u_ansprechpartner ON c.ansprechpartner_user_id = u_ansprechpartner.id";
                }
                
                $sql = "
                    SELECT " . implode(', ', $selectFields) . "
                    FROM customers c
                    LEFT JOIN companies comp ON c.company_id = comp.id
                    " . (!empty($joins) ? implode(' ', $joins) : '') . "
                    WHERE c.id = :customer_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
                $stmt->execute();
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$customer) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                    exit;
                }
                
                // Fehlende Felder mit null-Werten füllen
                if (!$hasLogo) {
                    $customer['logo'] = null;
                }
                if (!$hasLieferadresse) {
                    $customer['lieferadresse'] = null;
                }
                if (!$hasLieferPlz) {
                    $customer['liefer_plz'] = null;
                }
                if (!$hasLieferOrt) {
                    $customer['liefer_ort'] = null;
                }
                if (!$hasRechnungsAdresse) {
                    $customer['rechnungs_adresse'] = null;
                }
                if (!$hasRechnungsPlz) {
                    $customer['rechnungs_plz'] = null;
                }
                if (!$hasRechnungsOrt) {
                    $customer['rechnungs_ort'] = null;
                }
                if (!$hasRechnungsEmail) {
                    $customer['rechnungs_email'] = null;
                }
                if (!$hasAnsprechpartnerUserId) {
                    $customer['ansprechpartner_user_id'] = null;
                    $customer['ansprechpartner_vorname'] = null;
                    $customer['ansprechpartner_nachname'] = null;
                    $customer['ansprechpartner_email'] = null;
                }
                if (!$hasAnsprechpartnerManuellName) {
                    $customer['ansprechpartner_manuell_name'] = null;
                }
                if (!$hasAnsprechpartnerManuellEmail) {
                    $customer['ansprechpartner_manuell_email'] = null;
                }
                if (!$hasAnsprechpartnerManuellTelefon) {
                    $customer['ansprechpartner_manuell_telefon'] = null;
                }
                if (!$hasAnsprechpartnerManuellNotiz) {
                    $customer['ansprechpartner_manuell_notiz'] = null;
                }
                
                decrypt_customer_row($customer);
                if (isset($customer['company_name'])) $customer['company_name'] = decrypt_from_db($customer['company_name']);
                
                // Berechtigung prüfen
                $hasPermission = false;
                if ($userRole === 'Admin' || $userRole === 'Techniker') {
                    $hasPermission = true;
                } elseif ($userRole === 'Firmen-Admin' && $customer['company_id'] == $userCompanyId) {
                    $hasPermission = true;
                }
                
                if (!$hasPermission) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'customer' => $customer
                ]);
                exit;
            }
            
            // Kunden abrufen
            $companyFilter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            
            $whereConditions = [];
            $params = [];
            
            if ($userRole === 'Admin' || $userRole === 'Techniker') {
                // Admin und Techniker sehen alle Kunden
                if ($companyFilter !== null) {
                    if ($companyFilter === 0) {
                        // Spezieller Wert 0 bedeutet: nur Kunden ohne Firma
                        $whereConditions[] = "c.company_id IS NULL";
                    } else {
                        $whereConditions[] = "c.company_id = :company_filter";
                        $params[':company_filter'] = $companyFilter;
                    }
                }
            } elseif ($userRole === 'Firmen-Admin' && $userCompanyId) {
                // Firmen-Admin sieht Kunden der eigenen Firma und Kunden ohne Firma
                $whereConditions[] = "(c.company_id = :user_company_id OR c.company_id IS NULL)";
                $params[':user_company_id'] = $userCompanyId;
            } elseif ($userRole === 'Firmen-User' && $userCompanyId) {
                // Firmen-User sieht Kunden der eigenen Firma und Kunden ohne Firma
                $whereConditions[] = "(c.company_id = :user_company_id OR c.company_id IS NULL)";
                $params[':user_company_id'] = $userCompanyId;
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Prüfen ob logo und Lieferadresse-Spalten existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasLogo = in_array('logo', $columns);
                $hasLieferadresse = in_array('lieferadresse', $columns);
                $hasLieferPlz = in_array('liefer_plz', $columns);
                $hasLieferOrt = in_array('liefer_ort', $columns);
            } catch (PDOException $e) {
                $hasLogo = false;
                $hasLieferadresse = false;
                $hasLieferPlz = false;
                $hasLieferOrt = false;
            }
            
            $selectFields = [
                'c.id',
                'c.name',
                'c.kundennummer',
                'c.email',
                'c.telefon',
                'c.adresse',
                'c.plz',
                'c.ort',
                'c.status',
                'c.company_id',
                'comp.name as company_name',
                '(SELECT COUNT(*) FROM users WHERE customer_id = c.id) as anzahl_benutzer'
            ];
            
            if ($hasLogo) {
                $selectFields[] = 'c.logo';
            }
            if ($hasLieferadresse) {
                $selectFields[] = 'c.lieferadresse';
            }
            if ($hasLieferPlz) {
                $selectFields[] = 'c.liefer_plz';
            }
            if ($hasLieferOrt) {
                $selectFields[] = 'c.liefer_ort';
            }
            
            $sql = "
                SELECT " . implode(', ', $selectFields) . "
                FROM customers c
                LEFT JOIN companies comp ON c.company_id = comp.id
                $whereClause
                ORDER BY c.name
            ";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fehlende Felder mit null-Werten füllen und entschlüsseln
            foreach ($customers as &$customer) {
                if (!$hasLogo) {
                    $customer['logo'] = null;
                }
                if (!$hasLieferadresse) {
                    $customer['lieferadresse'] = null;
                }
                if (!$hasLieferPlz) {
                    $customer['liefer_plz'] = null;
                }
                if (!$hasLieferOrt) {
                    $customer['liefer_ort'] = null;
                }
                decrypt_customer_row($customer);
                if (isset($customer['company_name'])) $customer['company_name'] = decrypt_from_db($customer['company_name']);
            }
            unset($customer);
            
            json_response([
                'success' => true,
                'customers' => $customers
            ]);
            break;
            
        case 'POST':
            // Logo-Upload für Kunden
            if (isset($_FILES['logo']) && isset($_POST['customer_id'])) {
                if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $customerId = (int)$_POST['customer_id'];
                
                // Prüfen ob Kunde existiert
                $checkStmt = $pdo->prepare("SELECT id, company_id FROM customers WHERE id = ?");
                $checkStmt->execute([$customerId]);
                $customer = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (!$customer) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                    exit;
                }
                
                // Berechtigung prüfen
                if ($userRole === 'Firmen-Admin' && $customer['company_id'] != $userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Kunden']);
                    exit;
                }
                
                $file = $_FILES['logo'];
                
                // Datei-Validierung
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'Die Datei überschreitet die maximale Größe (php.ini)',
                        UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet die maximale Größe (Formular)',
                        UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen',
                        UPLOAD_ERR_NO_FILE => 'Keine Datei wurde hochgeladen',
                        UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
                        UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei',
                        UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt'
                    ];
                    $errorMsg = $errorMessages[$file['error']] ?? 'Unbekannter Upload-Fehler (Code: ' . $file['error'] . ')';
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $errorMsg]);
                    exit;
                }
                
                // Maximale Dateigröße: 5MB für Logos
                $maxFileSize = 5 * 1024 * 1024;
                if ($file['size'] > $maxFileSize) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Datei ist zu groß (max. 5MB)']);
                    exit;
                }
                
                // Nur Bildformate erlauben
                $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
                $mimeType = $file['type'] ?: mime_content_type($file['tmp_name']);
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nur Bildformate erlaubt (JPEG, PNG, GIF, WebP, SVG)']);
                    exit;
                }
                
                // Dateiname sicher machen
                $originalName = $file['name'];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $fileName = 'customer_' . $customerId . '_' . time() . '.' . $extension;
                $filePath = $logoUploadDir . $fileName;
                
                // Prüfen ob Verzeichnis beschreibbar ist
                if (!is_writable($logoUploadDir)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
                    exit;
                }
                
                // Prüfen ob logo Spalte existiert
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $hasLogo = in_array('logo', $columns);
                } catch (PDOException $e) {
                    $hasLogo = false;
                }
                
                if (!$hasLogo) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Logo-Feld existiert nicht in der Datenbank']);
                    exit;
                }
                
                // Altes Logo löschen falls vorhanden
                $oldLogoStmt = $pdo->prepare("SELECT logo FROM customers WHERE id = ?");
                $oldLogoStmt->execute([$customerId]);
                $oldLogo = $oldLogoStmt->fetchColumn();
                if ($oldLogo && strpos($oldLogo, 'uploads/images/') === 0) {
                    $oldLogoPath = dirname(__DIR__, 2) . '/' . $oldLogo;
                    if (file_exists($oldLogoPath)) {
                        @unlink($oldLogoPath);
                    }
                }
                
                // Datei speichern
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    http_response_code(500);
                    error_log("Logo-Upload-Fehler: Konnte Datei nicht von " . $file['tmp_name'] . " nach " . $filePath . " verschieben");
                    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei. Bitte Berechtigungen prüfen.']);
                    exit;
                }
                
                // Relativer Pfad für Datenbank
                $relativePath = 'uploads/images/' . $fileName;
                
                // In Datenbank speichern
                $updateStmt = $pdo->prepare("UPDATE customers SET logo = ?, geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$relativePath, $customerId]);
                
                // Log-Eintrag erstellen
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                        VALUES ('customer', ?, ?, 'updated', 'logo', ?, ?, NOW())
                    ");
                    $logStmt->execute([
                        $customerId,
                        $userId,
                        $oldLogo ?: '',
                        $relativePath
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags (Logo Upload): " . $e->getMessage());
                }
                
                // Kundennamen und company_id für Benachrichtigung abrufen
                $customerStmt = $pdo->prepare("SELECT name, company_id FROM customers WHERE id = ?");
                $customerStmt->execute([$customerId]);
                $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
                if ($customerData) {
                    decrypt_customer_row($customerData);
                }
                $customerName = $customerData['name'] ?? 'Unbekannt';
                $customerCompanyId = $customerData['company_id'] ?? null;
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $userId,
                    $customerCompanyId,
                    'customer_logo_upload',
                    'Logo hochgeladen: ' . $customerName,
                    'Ein neues Logo wurde für den Kunden "' . $customerName . '" von ' . $userName . ' hochgeladen.',
                    'niedrig',
                    'customers/detail.php?id=' . $customerId,
                    'customer',
                    $customerId
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Logo erfolgreich hochgeladen',
                    'logo_path' => $relativePath
                ]);
                exit;
            }
            
            // Neuen Kunden erstellen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                exit;
            }
            
            $companyId = isset($data['company_id']) && $data['company_id'] ? (int)$data['company_id'] : null;
            
            // Rollenbasierte Validierung
            if ($userRole === 'Firmen-Admin') {
                // Firmen-Admin kann nur Kunden für eigene Firma oder ohne Firma erstellen
                if ($companyId && $companyId != $userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Firma']);
                    exit;
                }
            }
            
            // Prüfen welche Spalten existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasLogo = in_array('logo', $columns);
                $hasKundennummer = in_array('kundennummer', $columns);
                $hasLieferadresse = in_array('lieferadresse', $columns);
                $hasLieferPlz = in_array('liefer_plz', $columns);
                $hasLieferOrt = in_array('liefer_ort', $columns);
                $hasRechnungsAdresse = in_array('rechnungs_adresse', $columns);
                $hasRechnungsPlz = in_array('rechnungs_plz', $columns);
                $hasRechnungsOrt = in_array('rechnungs_ort', $columns);
                $hasRechnungsEmail = in_array('rechnungs_email', $columns);
                $hasAnsprechpartnerUserId = in_array('ansprechpartner_user_id', $columns);
                $hasAnsprechpartnerManuellName = in_array('ansprechpartner_manuell_name', $columns);
                $hasAnsprechpartnerManuellEmail = in_array('ansprechpartner_manuell_email', $columns);
                $hasAnsprechpartnerManuellTelefon = in_array('ansprechpartner_manuell_telefon', $columns);
                $hasAnsprechpartnerManuellNotiz = in_array('ansprechpartner_manuell_notiz', $columns);
            } catch (PDOException $e) {
                $hasLogo = false;
                $hasKundennummer = false;
                $hasLieferadresse = false;
                $hasLieferPlz = false;
                $hasLieferOrt = false;
                $hasRechnungsAdresse = false;
                $hasRechnungsPlz = false;
                $hasRechnungsOrt = false;
                $hasRechnungsEmail = false;
                $hasAnsprechpartnerUserId = false;
                $hasAnsprechpartnerManuellName = false;
                $hasAnsprechpartnerManuellEmail = false;
                $hasAnsprechpartnerManuellTelefon = false;
                $hasAnsprechpartnerManuellNotiz = false;
            }
            
            // Ansprechpartner-Daten extrahieren
            $ansprechpartnerUserId = isset($data['ansprechpartner_user_id']) && $data['ansprechpartner_user_id'] ? (int)$data['ansprechpartner_user_id'] : null;
            $ansprechpartnerManuellName = isset($data['ansprechpartner_manuell_name']) && $data['ansprechpartner_manuell_name'] ? trim($data['ansprechpartner_manuell_name']) : null;
            $ansprechpartnerManuellEmail = isset($data['ansprechpartner_manuell_email']) && $data['ansprechpartner_manuell_email'] ? trim($data['ansprechpartner_manuell_email']) : null;
            $ansprechpartnerManuellTelefon = isset($data['ansprechpartner_manuell_telefon']) && $data['ansprechpartner_manuell_telefon'] ? trim($data['ansprechpartner_manuell_telefon']) : null;
            $ansprechpartnerManuellNotiz = isset($data['ansprechpartner_manuell_notiz']) && $data['ansprechpartner_manuell_notiz'] ? trim($data['ansprechpartner_manuell_notiz']) : null;
            
            // Wenn manueller Ansprechpartner gesetzt ist, User-ID zurücksetzen
            if ($ansprechpartnerManuellName) {
                $ansprechpartnerUserId = null;
            }
            
            // Prüfen ob User zur Firma gehört (wenn User ausgewählt)
            if ($ansprechpartnerUserId && $companyId) {
                $userCheckStmt = $pdo->prepare("SELECT id, company_id FROM users WHERE id = ?");
                $userCheckStmt->execute([$ansprechpartnerUserId]);
                $selectedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($selectedUser && $selectedUser['company_id'] != $companyId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Der ausgewählte User gehört nicht zur angegebenen Firma']);
                    exit;
                }
            }
            
            // Prüfen ob Kundennummer bereits existiert (falls angegeben; bei Verschlüsselung per Decrypt-Vergleich)
            if ($hasKundennummer && isset($data['kundennummer']) && $data['kundennummer']) {
                $kundennummerPlain = trim($data['kundennummer']);
                $checkStmt = $pdo->prepare("SELECT id, kundennummer FROM customers WHERE company_id <=> ?");
                $checkStmt->execute([$companyId]);
                while ($row = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    decrypt_customer_row($row);
                    if ($row['kundennummer'] !== null && $row['kundennummer'] !== '' && trim($row['kundennummer']) === $kundennummerPlain) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Diese Kundennummer existiert bereits']);
                        exit;
                    }
                }
            }
            
            // SQL-Query dynamisch zusammenbauen (Klartextfelder werden verschlüsselt gespeichert)
            $insertFields = ['company_id', 'name'];
            $insertPlaceholders = ['?', '?'];
            $executeValues = [$companyId, encrypt_customer_value(trim($data['name']))];
            
            if ($hasKundennummer) {
                $insertFields[] = 'kundennummer';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value(isset($data['kundennummer']) && $data['kundennummer'] ? trim($data['kundennummer']) : null);
            }
            
            $insertFields = array_merge($insertFields, ['email', 'telefon', 'adresse', 'plz', 'ort']);
            $insertPlaceholders = array_merge($insertPlaceholders, ['?', '?', '?', '?', '?']);
            $executeValues = array_merge($executeValues, [
                encrypt_customer_value((isset($data['email']) && $data['email']) ? trim($data['email']) : null),
                encrypt_customer_value((isset($data['telefon']) && $data['telefon']) ? trim($data['telefon']) : null),
                encrypt_customer_value((isset($data['adresse']) && $data['adresse']) ? trim($data['adresse']) : null),
                encrypt_customer_value((isset($data['plz']) && $data['plz']) ? trim($data['plz']) : null),
                encrypt_customer_value((isset($data['ort']) && $data['ort']) ? trim($data['ort']) : null)
            ]);
            
            // Liefer- und Rechnungsadresse: wenn leer, Hauptadresse übernehmen
            $mainAdresse = (isset($data['adresse']) && $data['adresse']) ? trim($data['adresse']) : null;
            $mainPlz = (isset($data['plz']) && $data['plz']) ? trim($data['plz']) : null;
            $mainOrt = (isset($data['ort']) && $data['ort']) ? trim($data['ort']) : null;
            if ($hasLieferadresse) {
                $insertFields[] = 'lieferadresse';
                $insertPlaceholders[] = '?';
                $v = isset($data['lieferadresse']) && $data['lieferadresse'] ? trim($data['lieferadresse']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainAdresse);
            }
            if ($hasLieferPlz) {
                $insertFields[] = 'liefer_plz';
                $insertPlaceholders[] = '?';
                $v = isset($data['liefer_plz']) && $data['liefer_plz'] ? trim($data['liefer_plz']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainPlz);
            }
            if ($hasLieferOrt) {
                $insertFields[] = 'liefer_ort';
                $insertPlaceholders[] = '?';
                $v = isset($data['liefer_ort']) && $data['liefer_ort'] ? trim($data['liefer_ort']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainOrt);
            }
            if ($hasRechnungsAdresse) {
                $insertFields[] = 'rechnungs_adresse';
                $insertPlaceholders[] = '?';
                $v = isset($data['rechnungs_adresse']) && $data['rechnungs_adresse'] ? trim($data['rechnungs_adresse']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainAdresse);
            }
            if ($hasRechnungsPlz) {
                $insertFields[] = 'rechnungs_plz';
                $insertPlaceholders[] = '?';
                $v = isset($data['rechnungs_plz']) && $data['rechnungs_plz'] ? trim($data['rechnungs_plz']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainPlz);
            }
            if ($hasRechnungsOrt) {
                $insertFields[] = 'rechnungs_ort';
                $insertPlaceholders[] = '?';
                $v = isset($data['rechnungs_ort']) && $data['rechnungs_ort'] ? trim($data['rechnungs_ort']) : null;
                $executeValues[] = encrypt_customer_value($v !== null && $v !== '' ? $v : $mainOrt);
            }
            if ($hasRechnungsEmail) {
                $insertFields[] = 'rechnungs_email';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value(isset($data['rechnungs_email']) && $data['rechnungs_email'] ? trim($data['rechnungs_email']) : null);
            }
            
            $insertFields = array_merge($insertFields, ['notizen', 'status', 'erstellt_von', 'erstellt_datum']);
            $insertPlaceholders = array_merge($insertPlaceholders, ['?', '?', '?', 'NOW()']);
            $executeValues = array_merge($executeValues, [
                encrypt_customer_value((isset($data['notizen']) && $data['notizen']) ? trim($data['notizen']) : null),
                'aktiv',
                $userId
            ]);
            
            if ($hasAnsprechpartnerUserId) {
                $insertFields[] = 'ansprechpartner_user_id';
                $insertPlaceholders[] = '?';
                $executeValues[] = $ansprechpartnerUserId;
            }
            if ($hasAnsprechpartnerManuellName) {
                $insertFields[] = 'ansprechpartner_manuell_name';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value($ansprechpartnerManuellName);
            }
            if ($hasAnsprechpartnerManuellEmail) {
                $insertFields[] = 'ansprechpartner_manuell_email';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value($ansprechpartnerManuellEmail);
            }
            if ($hasAnsprechpartnerManuellTelefon) {
                $insertFields[] = 'ansprechpartner_manuell_telefon';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value($ansprechpartnerManuellTelefon);
            }
            if ($hasAnsprechpartnerManuellNotiz) {
                $insertFields[] = 'ansprechpartner_manuell_notiz';
                $insertPlaceholders[] = '?';
                $executeValues[] = encrypt_customer_value($ansprechpartnerManuellNotiz);
            }
            
            if ($hasLogo) {
                $insertFields[] = 'logo';
                $insertPlaceholders[] = '?';
                $executeValues[] = isset($data['logo']) && $data['logo'] ? trim($data['logo']) : null;
            }
            
            // SQL zusammenbauen - NOW() sollte direkt im SQL bleiben
            $sql = "INSERT INTO customers (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            
            // SQL ist bereits korrekt, da NOW() direkt im Platzhalter-Array steht
            // PDO akzeptiert NOW() direkt im SQL-String
            $stmt = $pdo->prepare($sql);
            $stmt->execute($executeValues);
            $customerId = $pdo->lastInsertId();
            
            // Log-Eintrag für Erstellung erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'created', 'Kunde erstellt', NOW())
                ");
                $logStmt->execute([$customerId, $userId]);
            } catch (PDOException $e) {
                // Log-Fehler nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            $customerName = trim($data['name']);
            createNotificationsForAction(
                $userId,
                $companyId,
                'customer_created',
                'Neuer Kunde erstellt: ' . $customerName,
                'Ein neuer Kunde "' . $customerName . '" wurde von ' . $userName . ' erstellt.',
                'normal',
                'customers/detail.php?id=' . $customerId,
                'customer',
                $customerId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Kunde erfolgreich angelegt',
                'customer_id' => $customerId
            ]);
            break;
            
        case 'PUT':
            // Kunde aktualisieren
            if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['customer_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'customer_id fehlt']);
                exit;
            }
            
            $customerId = (int)$data['customer_id'];
            
            // Prüfen ob Kunde existiert und alte Werte für Log abrufen
            $checkStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $checkStmt->execute([$customerId]);
            $oldCustomer = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldCustomer) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                exit;
            }
            
            decrypt_customer_row($oldCustomer);
            $customer = $oldCustomer;
            $oldCustomerName = $oldCustomer['name'];
            $oldCustomerCompanyId = $oldCustomer['company_id'];
            
            // Rollenbasierte Validierung
            if ($userRole === 'Firmen-Admin') {
                // Firmen-Admin kann nur Kunden der eigenen Firma bearbeiten
                if ($customer['company_id'] != $userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Kunden']);
                    exit;
                }
                // Firmen-Admin kann nur Firma auf eigene Firma oder NULL setzen
                if (isset($data['company_id']) && $data['company_id'] && (int)$data['company_id'] != $userCompanyId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Firma']);
                    exit;
                }
            }
            
            // Prüfen welche Spalten existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasLogo = in_array('logo', $columns);
                $hasKundennummer = in_array('kundennummer', $columns);
                $hasLieferadresse = in_array('lieferadresse', $columns);
                $hasLieferPlz = in_array('liefer_plz', $columns);
                $hasLieferOrt = in_array('liefer_ort', $columns);
                $hasRechnungsAdresse = in_array('rechnungs_adresse', $columns);
                $hasRechnungsPlz = in_array('rechnungs_plz', $columns);
                $hasRechnungsOrt = in_array('rechnungs_ort', $columns);
                $hasRechnungsEmail = in_array('rechnungs_email', $columns);
            } catch (PDOException $e) {
                $hasLogo = false;
                $hasKundennummer = false;
                $hasLieferadresse = false;
                $hasLieferPlz = false;
                $hasLieferOrt = false;
                $hasRechnungsAdresse = false;
                $hasRechnungsPlz = false;
                $hasRechnungsOrt = false;
                $hasRechnungsEmail = false;
            }
            
            // Update-Felder zusammenbauen (nur die, die gesendet wurden)
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $updateParams[] = encrypt_customer_value(trim($data['name']));
            }
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $updateParams[] = encrypt_customer_value($data['email'] ? trim($data['email']) : null);
            }
            if (isset($data['telefon'])) {
                $updateFields[] = "telefon = ?";
                $updateParams[] = encrypt_customer_value($data['telefon'] ? trim($data['telefon']) : null);
            }
            if (isset($data['adresse'])) {
                $updateFields[] = "adresse = ?";
                $updateParams[] = encrypt_customer_value($data['adresse'] ? trim($data['adresse']) : null);
            }
            if (isset($data['plz'])) {
                $updateFields[] = "plz = ?";
                $updateParams[] = encrypt_customer_value($data['plz'] ? trim($data['plz']) : null);
            }
            if (isset($data['ort'])) {
                $updateFields[] = "ort = ?";
                $updateParams[] = encrypt_customer_value($data['ort'] ? trim($data['ort']) : null);
            }
            if (isset($data['notizen'])) {
                $updateFields[] = "notizen = ?";
                $updateParams[] = encrypt_customer_value($data['notizen'] ? trim($data['notizen']) : null);
            }
            if (isset($data['company_id'])) {
                $companyId = $data['company_id'] ? (int)$data['company_id'] : null;
                $updateFields[] = "company_id = ?";
                $updateParams[] = $companyId;
            }
            if (isset($data['status'])) {
                $allowedStatus = ['aktiv', 'inaktiv', 'gesperrt'];
                if (in_array($data['status'], $allowedStatus)) {
                    $updateFields[] = "status = ?";
                    $updateParams[] = $data['status'];
                }
            }
            
            // Prüfen ob Kundennummer bereits existiert (falls angegeben und geändert; bei Verschlüsselung per Decrypt-Vergleich)
            if ($hasKundennummer && isset($data['kundennummer'])) {
                $kundennummerPlain = $data['kundennummer'] ? trim($data['kundennummer']) : null;
                $checkStmt = $pdo->prepare("SELECT id, kundennummer FROM customers WHERE company_id <=> ? AND id != ?");
                $checkStmt->execute([$oldCustomer['company_id'], $customerId]);
                while ($row = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    decrypt_customer_row($row);
                    $existing = $row['kundennummer'] !== null && $row['kundennummer'] !== '' ? trim($row['kundennummer']) : null;
                    if ($existing === $kundennummerPlain) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Diese Kundennummer existiert bereits']);
                        exit;
                    }
                }
                $updateFields[] = "kundennummer = ?";
                $updateParams[] = encrypt_customer_value($kundennummerPlain);
            }
            
            if ($hasLieferadresse && isset($data['lieferadresse'])) {
                $updateFields[] = "lieferadresse = ?";
                $updateParams[] = encrypt_customer_value($data['lieferadresse'] ? trim($data['lieferadresse']) : null);
            }
            if ($hasLieferPlz && isset($data['liefer_plz'])) {
                $updateFields[] = "liefer_plz = ?";
                $updateParams[] = encrypt_customer_value($data['liefer_plz'] ? trim($data['liefer_plz']) : null);
            }
            if ($hasLieferOrt && isset($data['liefer_ort'])) {
                $updateFields[] = "liefer_ort = ?";
                $updateParams[] = encrypt_customer_value($data['liefer_ort'] ? trim($data['liefer_ort']) : null);
            }
            if ($hasRechnungsAdresse && isset($data['rechnungs_adresse'])) {
                $updateFields[] = "rechnungs_adresse = ?";
                $updateParams[] = encrypt_customer_value($data['rechnungs_adresse'] ? trim($data['rechnungs_adresse']) : null);
            }
            if ($hasRechnungsPlz && isset($data['rechnungs_plz'])) {
                $updateFields[] = "rechnungs_plz = ?";
                $updateParams[] = encrypt_customer_value($data['rechnungs_plz'] ? trim($data['rechnungs_plz']) : null);
            }
            if ($hasRechnungsOrt && isset($data['rechnungs_ort'])) {
                $updateFields[] = "rechnungs_ort = ?";
                $updateParams[] = encrypt_customer_value($data['rechnungs_ort'] ? trim($data['rechnungs_ort']) : null);
            }
            if ($hasRechnungsEmail && isset($data['rechnungs_email'])) {
                $updateFields[] = "rechnungs_email = ?";
                $updateParams[] = encrypt_customer_value($data['rechnungs_email'] ? trim($data['rechnungs_email']) : null);
            }
            
            if (isset($data['ansprechpartner_user_id']) || isset($data['ansprechpartner_manuell_name'])) {
                // Prüfen ob Ansprechpartner-Felder existieren
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $hasAnsprechpartnerUserId = in_array('ansprechpartner_user_id', $columns);
                    $hasAnsprechpartnerManuellName = in_array('ansprechpartner_manuell_name', $columns);
                    $hasAnsprechpartnerManuellEmail = in_array('ansprechpartner_manuell_email', $columns);
                    $hasAnsprechpartnerManuellTelefon = in_array('ansprechpartner_manuell_telefon', $columns);
                    $hasAnsprechpartnerManuellNotiz = in_array('ansprechpartner_manuell_notiz', $columns);
                } catch (PDOException $e) {
                    $hasAnsprechpartnerUserId = false;
                    $hasAnsprechpartnerManuellName = false;
                    $hasAnsprechpartnerManuellEmail = false;
                    $hasAnsprechpartnerManuellTelefon = false;
                    $hasAnsprechpartnerManuellNotiz = false;
                }
                
                $ansprechpartnerUserId = null;
                $ansprechpartnerManuellName = null;
                $ansprechpartnerManuellEmail = null;
                $ansprechpartnerManuellTelefon = null;
                $ansprechpartnerManuellNotiz = null;
                
                if (isset($data['ansprechpartner_manuell_name']) && $data['ansprechpartner_manuell_name']) {
                    // Manueller Ansprechpartner hat Priorität
                    $ansprechpartnerManuellName = trim($data['ansprechpartner_manuell_name']);
                    $ansprechpartnerManuellEmail = isset($data['ansprechpartner_manuell_email']) ? trim($data['ansprechpartner_manuell_email']) : null;
                    $ansprechpartnerManuellTelefon = isset($data['ansprechpartner_manuell_telefon']) ? trim($data['ansprechpartner_manuell_telefon']) : null;
                    $ansprechpartnerManuellNotiz = isset($data['ansprechpartner_manuell_notiz']) ? trim($data['ansprechpartner_manuell_notiz']) : null;
                    $ansprechpartnerUserId = null;
                } elseif (isset($data['ansprechpartner_user_id']) && $data['ansprechpartner_user_id']) {
                    $ansprechpartnerUserId = (int)$data['ansprechpartner_user_id'];
                    // Prüfen ob User zur Firma gehört
                    $userCheckStmt = $pdo->prepare("SELECT id, company_id FROM users WHERE id = ?");
                    $userCheckStmt->execute([$ansprechpartnerUserId]);
                    $selectedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Firma-ID aus Request oder bestehender Kunde
                    $checkCompanyId = isset($data['company_id']) ? (int)$data['company_id'] : $oldCustomer['company_id'];
                    
                    if ($selectedUser && $checkCompanyId && $selectedUser['company_id'] != $checkCompanyId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Der ausgewählte User gehört nicht zur angegebenen Firma']);
                        exit;
                    }
                    
                    $ansprechpartnerManuellName = null;
                    $ansprechpartnerManuellEmail = null;
                    $ansprechpartnerManuellTelefon = null;
                    $ansprechpartnerManuellNotiz = null;
                }
                
                if ($hasAnsprechpartnerUserId) {
                    $updateFields[] = "ansprechpartner_user_id = ?";
                    $updateParams[] = $ansprechpartnerUserId;
                }
                if ($hasAnsprechpartnerManuellName) {
                    $updateFields[] = "ansprechpartner_manuell_name = ?";
                    $updateParams[] = encrypt_customer_value($ansprechpartnerManuellName);
                }
                if ($hasAnsprechpartnerManuellEmail) {
                    $updateFields[] = "ansprechpartner_manuell_email = ?";
                    $updateParams[] = encrypt_customer_value($ansprechpartnerManuellEmail);
                }
                if ($hasAnsprechpartnerManuellTelefon) {
                    $updateFields[] = "ansprechpartner_manuell_telefon = ?";
                    $updateParams[] = encrypt_customer_value($ansprechpartnerManuellTelefon);
                }
                if ($hasAnsprechpartnerManuellNotiz) {
                    $updateFields[] = "ansprechpartner_manuell_notiz = ?";
                    $updateParams[] = encrypt_customer_value($ansprechpartnerManuellNotiz);
                }
            }
            
            if ($hasLogo && isset($data['logo'])) {
                $updateFields[] = "logo = ?";
                $updateParams[] = $data['logo'] ? trim($data['logo']) : null;
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Felder zum Aktualisieren']);
                exit;
            }
            
            $updateFields[] = "geaendert_datum = NOW()";
            $updateParams[] = $customerId;
            
            $sql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
            
            // Prüfen ob Status auf 'gesperrt' geändert wurde oder von 'gesperrt' auf 'aktiv'
            $statusChangedToGesperrt = false;
            $statusChangedFromGesperrt = false;
            $oldStatus = $oldCustomer['status'] ?? 'aktiv';
            $newStatus = isset($data['status']) ? $data['status'] : $oldStatus;
            if (isset($data['status']) && $data['status'] === 'gesperrt' && $oldStatus !== 'gesperrt') {
                $statusChangedToGesperrt = true;
            } elseif (isset($data['status']) && $oldStatus === 'gesperrt' && $data['status'] !== 'gesperrt') {
                $statusChangedFromGesperrt = true;
            }
            
            // Benachrichtigungen für Änderungen erstellen
            $customerName = isset($data['name']) ? trim($data['name']) : $oldCustomerName;
            $customerName = $customerName ?: 'Unbekannt';
            $currentCompanyId = isset($data['company_id']) ? (int)$data['company_id'] : $oldCustomerCompanyId;
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            if ($statusChangedToGesperrt) {
                createNotificationsForAction(
                    $userId,
                    $currentCompanyId,
                    'customer_status_changed',
                    'Kunde gesperrt: ' . $customerName,
                    'Der Kunde "' . $customerName . '" wurde von ' . $userName . ' gesperrt.',
                    'hoch',
                    'customers/detail.php?id=' . $customerId,
                    'customer',
                    $customerId
                );
            } elseif ($statusChangedFromGesperrt) {
                createNotificationsForAction(
                    $userId,
                    $currentCompanyId,
                    'customer_status_changed',
                    'Kunde entsperrt: ' . $customerName,
                    'Der Kunde "' . $customerName . '" wurde von ' . $userName . ' entsperrt.',
                    'hoch',
                    'customers/detail.php?id=' . $customerId,
                    'customer',
                    $customerId
                );
            } else {
                createNotificationsForAction(
                    $userId,
                    $currentCompanyId,
                    'customer_updated',
                    'Kunde aktualisiert: ' . $customerName,
                    'Der Kunde "' . $customerName . '" wurde von ' . $userName . ' aktualisiert.',
                    'normal',
                    'customers/detail.php?id=' . $customerId,
                    'customer',
                    $customerId
                );
            }
            
            // Log-Einträge erstellen für geänderte Felder
            $fieldsToCheck = [
                'name' => 'name',
                'kundennummer' => 'kundennummer',
                'email' => 'email',
                'telefon' => 'telefon',
                'adresse' => 'adresse',
                'plz' => 'plz',
                'ort' => 'ort',
                'notizen' => 'notizen',
                'company_id' => 'company_id',
                'logo' => 'logo',
                'status' => 'status'
            ];
            
            foreach ($fieldsToCheck as $dataKey => $dbField) {
                if (isset($data[$dataKey])) {
                    $oldValue = $oldCustomer[$dbField] ?? null;
                    $newValue = $data[$dataKey];
                    
                    // Spezielle Behandlung für company_id (Firma-ID zu Name konvertieren)
                    if ($dbField === 'company_id') {
                        $oldValueStr = '';
                        $newValueStr = '';
                        
                        if ($oldValue) {
                            $oldCompanyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
                            $oldCompanyStmt->execute([$oldValue]);
                            $oldCompany = $oldCompanyStmt->fetch(PDO::FETCH_ASSOC);
                            if ($oldCompany) {
                                $oldValueStr = $oldCompany['name'];
                            }
                        } else {
                            $oldValueStr = '';
                        }
                        
                        if ($newValue) {
                            $newCompanyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
                            $newCompanyStmt->execute([$newValue]);
                            $newCompany = $newCompanyStmt->fetch(PDO::FETCH_ASSOC);
                            if ($newCompany) {
                                $newValueStr = $newCompany['name'];
                            }
                        } else {
                            $newValueStr = '';
                        }
                        
                        // Vergleich: NULL und leere Strings werden gleich behandelt
                        $oldValueForCompare = ($oldValue === null || $oldValue === '') ? null : (string)$oldValue;
                        $newValueForCompare = ($newValue === null || $newValue === '') ? null : (string)$newValue;
                        
                        // Nur loggen wenn sich der Wert geändert hat
                        if ($oldValueForCompare !== $newValueForCompare) {
                            try {
                                $logStmt = $pdo->prepare("
                                    INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                                    VALUES ('customer', ?, ?, 'updated', ?, ?, ?, NOW())
                                ");
                                $logStmt->execute([
                                    $customerId,
                                    $userId,
                                    $dbField,
                                    $oldValueStr,
                                    $newValueStr
                                ]);
                            } catch (PDOException $e) {
                                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                            }
                        }
                    } else {
                        // Normale Behandlung für andere Felder
                        $oldValueForCompare = ($oldValue === null || $oldValue === '') ? null : (string)$oldValue;
                        $newValueForCompare = ($newValue === null || $newValue === '') ? null : (string)$newValue;
                        
                        // Nur loggen wenn sich der Wert geändert hat
                        if ($oldValueForCompare !== $newValueForCompare) {
                            try {
                                $logStmt = $pdo->prepare("
                                    INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                                    VALUES ('customer', ?, ?, 'updated', ?, ?, ?, NOW())
                                ");
                                $logStmt->execute([
                                    $customerId,
                                    $userId,
                                    $dbField,
                                    $oldValue ?: '',
                                    $newValue ?: ''
                                ]);
                            } catch (PDOException $e) {
                                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Kunde erfolgreich aktualisiert'
            ]);
            break;
            
        case 'DELETE':
            // Techniker und Admin können die Löschaktion ausführen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'id fehlt']);
                exit;
            }
            
            $customerId = (int)$_GET['id'];
            
            // Prüfen ob Kunde existiert
            $checkStmt = $pdo->prepare("SELECT id, name, status FROM customers WHERE id = ?");
            $checkStmt->execute([$customerId]);
            $customer = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Kunde nicht gefunden']);
                exit;
            }
            
            // Techniker setzen Kunden auf "inaktiv" statt sie zu löschen
            if ($userRole === 'Techniker') {
                // Prüfen ob Kunde bereits inaktiv ist
                if ($customer['status'] === 'inaktiv') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Kunde ist bereits inaktiv']);
                    exit;
                }
                
                // Log-Eintrag für Deaktivierung erstellen
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                        VALUES ('customer', ?, ?, 'updated', 'status', ?, 'inaktiv', NOW())
                    ");
                    $logStmt->execute([$customerId, $userId, $customer['status']]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                }
                
                // Status auf inaktiv setzen
                $updateStmt = $pdo->prepare("UPDATE customers SET status = 'inaktiv', geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$customerId]);
                
                // company_id für Benachrichtigung abrufen
                $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
                $customerStmt->execute([$customerId]);
                $customerCompanyId = $customerStmt->fetchColumn();
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                $customerName = $customer['name'];
                createNotificationsForAction(
                    $userId,
                    $customerCompanyId,
                    'customer_status_changed',
                    'Kunde auf inaktiv gesetzt: ' . $customerName,
                    'Der Kunde "' . $customerName . '" wurde von ' . $userName . ' auf inaktiv gesetzt.',
                    'hoch',
                    'customers/detail.php?id=' . $customerId,
                    'customer',
                    $customerId
                );
                
                echo json_encode(['success' => true, 'message' => 'Kunde wurde auf inaktiv gesetzt']);
                break;
            }
            
            // Nur Admins dürfen wirklich löschen
            if ($userRole !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen Kunden wirklich löschen']);
                exit;
            }
            
            // Prüfen ob Kunde noch Tickets hat (nur beim echten Löschen)
            $checkTickets = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE customer_id = ?");
            $checkTickets->execute([$customerId]);
            if ($checkTickets->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Kunde kann nicht gelöscht werden, da noch Tickets zugeordnet sind']);
                exit;
            }
            
            // Logo löschen falls vorhanden
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM customers");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasLogo = in_array('logo', $columns);
            } catch (PDOException $e) {
                $hasLogo = false;
            }
            
            if ($hasLogo) {
                $logoStmt = $pdo->prepare("SELECT logo FROM customers WHERE id = ?");
                $logoStmt->execute([$customerId]);
                $logo = $logoStmt->fetchColumn();
                if ($logo && strpos($logo, 'uploads/images/') === 0) {
                    $logoPath = dirname(__DIR__, 2) . '/' . $logo;
                    if (file_exists($logoPath)) {
                        @unlink($logoPath);
                    }
                }
            }
            
            // company_id für Benachrichtigung abrufen (vor dem Löschen)
            $customerStmt = $pdo->prepare("SELECT company_id FROM customers WHERE id = ?");
            $customerStmt->execute([$customerId]);
            $customerCompanyId = $customerStmt->fetchColumn();
            
            // Log-Eintrag für Löschung erstellen (vor dem Löschen)
            $customerName = $customer['name'];
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('customer', ?, ?, 'deleted', 'Kunde gelöscht: " . addslashes($customer['name']) . "', NOW())
                ");
                $logStmt->execute([$customerId, $userId]);
            } catch (PDOException $e) {
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $customerCompanyId,
                'customer_deleted',
                'Kunde gelöscht: ' . $customerName,
                'Der Kunde "' . $customerName . '" wurde von ' . $userName . ' gelöscht.',
                'kritisch',
                'customers/',
                'customer',
                $customerId
            );
            
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            
            echo json_encode(['success' => true, 'message' => 'Kunde gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Customers API Error: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    error_log("SQL Error Info: " . print_r($e->errorInfo ?? [], true));
    json_response(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Customers API Throwable: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler beim Verarbeiten der Anfrage']);
}
