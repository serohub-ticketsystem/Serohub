<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/notifications.php';
require_once dirname(__DIR__) . '/helper/encryption.php';

header('Content-Type: application/json');

/**
 * Stellt sicher, dass maximal eine Firma als Primärfirma markiert ist.
 * Wenn $preferredCompanyId gesetzt ist, wird genau diese als primär gesetzt.
 */
function enforceSinglePrimaryCompany(PDO $pdo, ?int $preferredCompanyId = null): void
{
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'is_primary'");
        if ($colStmt->rowCount() === 0) {
            return;
        }
    } catch (PDOException $e) {
        return;
    }

    if ($preferredCompanyId !== null) {
        $setPreferredStmt = $pdo->prepare("UPDATE companies SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END");
        $setPreferredStmt->execute([$preferredCompanyId]);
        return;
    }

    $countStmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_primary = 1");
    $primaryCount = (int)$countStmt->fetchColumn();

    if ($primaryCount <= 1) {
        return;
    }

    $keepStmt = $pdo->query("SELECT id FROM companies WHERE is_primary = 1 ORDER BY id ASC LIMIT 1");
    $keepId = (int)$keepStmt->fetchColumn();
    if ($keepId > 0) {
        $normalizeStmt = $pdo->prepare("UPDATE companies SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE is_primary = 1");
        $normalizeStmt->execute([$keepId]);
    }
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
            // Firmen abrufen
            if (isset($_GET['id'])) {
                // Einzelne Firma
                $companyId = (int)$_GET['id'];
                
                // Prüfen welche Felder in der Tabelle existieren
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM companies");
                    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    $hasLieferadresse = in_array('lieferadresse', $columns);
                    $hasLieferPlz = in_array('liefer_plz', $columns);
                    $hasLieferOrt = in_array('liefer_ort', $columns);
                    $hasRechnungsAdresse = in_array('rechnungs_adresse', $columns);
                    $hasRechnungsPlz = in_array('rechnungs_plz', $columns);
                    $hasRechnungsOrt = in_array('rechnungs_ort', $columns);
                    $hasRechnungsEmail = in_array('rechnungs_email', $columns);
                    $hasNotizen = in_array('notizen', $columns);
                    $hasZugewiesenAn = in_array('zugewiesen_an', $columns);
                    $hasAnsprechpartnerUserId = in_array('ansprechpartner_user_id', $columns);
                    $hasAnsprechpartnerManuellName = in_array('ansprechpartner_manuell_name', $columns);
                    $hasAnsprechpartnerManuellEmail = in_array('ansprechpartner_manuell_email', $columns);
                    $hasAnsprechpartnerManuellTelefon = in_array('ansprechpartner_manuell_telefon', $columns);
                    $hasAnsprechpartnerManuellNotiz = in_array('ansprechpartner_manuell_notiz', $columns);
                    $hasEmail = in_array('email', $columns);
                    $hasTelefonnummer = in_array('telefonnummer', $columns);
                    $hasHatWartungsvertrag = in_array('hat_wartungsvertrag', $columns);
                    $hasWartungZahlungsrhythmus = in_array('wartung_zahlungsrhythmus', $columns);
                    $hasWartungZahlungstag = in_array('wartung_zahlungstag', $columns);
                    $hasLagerZugriff = in_array('lager_zugriff', $columns);
                    $hasIsPrimary = in_array('is_primary', $columns);
                } catch (PDOException $e) {
                    // Falls Prüfung fehlschlägt, nehmen wir an dass alle Felder existieren
                    $hasLieferadresse = true;
                    $hasHatWartungsvertrag = false;
                    $hasWartungZahlungsrhythmus = false;
                    $hasWartungZahlungstag = false;
                    $hasLagerZugriff = false;
                    $hasLieferPlz = true;
                    $hasLieferOrt = true;
                    $hasRechnungsAdresse = true;
                    $hasRechnungsPlz = true;
                    $hasRechnungsOrt = true;
                    $hasRechnungsEmail = true;
                    $hasNotizen = true;
                    $hasZugewiesenAn = true;
                    $hasEmail = true;
                    $hasTelefonnummer = true;
                    $hasIsPrimary = false;
                }
                
                // SQL-Query dynamisch zusammenbauen
                $selectFields = [
                    'c.id',
                    'c.name',
                    'c.domain',
                    'c.kundennummer',
                    'c.adresse',
                    'c.plz',
                    'c.ort',
                    'c.logo',
                    'c.status',
                    'c.erstellt_von',
                    'c.geaendert_von',
                    'c.erstellt_datum',
                    'c.geaendert_datum',
                    'u_erstellt.vorname as ersteller_vorname',
                    'u_erstellt.nachname as ersteller_nachname'
                ];
                
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
                if ($hasNotizen) {
                    $selectFields[] = 'c.notizen';
                }
                if ($hasZugewiesenAn) {
                    $selectFields[] = 'c.zugewiesen_an';
                    $selectFields[] = 'u_zugewiesen.vorname as zugewiesen_vorname';
                    $selectFields[] = 'u_zugewiesen.nachname as zugewiesen_nachname';
                    $selectFields[] = 'u_zugewiesen.email as zugewiesen_email';
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
                if ($hasEmail) {
                    $selectFields[] = 'c.email';
                }
                if ($hasTelefonnummer) {
                    $selectFields[] = 'c.telefonnummer';
                }
                if (!empty($hasHatWartungsvertrag)) {
                    $selectFields[] = 'c.hat_wartungsvertrag';
                }
                if (!empty($hasWartungZahlungsrhythmus)) {
                    $selectFields[] = 'c.wartung_zahlungsrhythmus';
                }
                if (!empty($hasWartungZahlungstag)) {
                    $selectFields[] = 'c.wartung_zahlungstag';
                }
                if (!empty($hasLagerZugriff)) {
                    $selectFields[] = 'c.lager_zugriff';
                }
                if (!empty($hasIsPrimary)) {
                    $selectFields[] = 'c.is_primary';
                }
                
                $joins = [];
                if ($hasZugewiesenAn) {
                    $joins[] = "LEFT JOIN users u_zugewiesen ON c.zugewiesen_an = u_zugewiesen.id";
                }
                if ($hasAnsprechpartnerUserId) {
                    $joins[] = "LEFT JOIN users u_ansprechpartner ON c.ansprechpartner_user_id = u_ansprechpartner.id";
                }
                
                $sql = "
                    SELECT " . implode(', ', $selectFields) . "
                    FROM companies c
                    LEFT JOIN users u_erstellt ON c.erstellt_von = u_erstellt.id
                    " . (!empty($joins) ? implode(' ', $joins) : '') . "
                    WHERE c.id = :company_id
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$company) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                    exit;
                }
                
                // Fehlende Felder mit null-Werten füllen
                if (!$hasLieferadresse) $company['lieferadresse'] = null;
                if (!$hasLieferPlz) $company['liefer_plz'] = null;
                if (!$hasLieferOrt) $company['liefer_ort'] = null;
                if (!$hasRechnungsAdresse) $company['rechnungs_adresse'] = null;
                if (!$hasRechnungsPlz) $company['rechnungs_plz'] = null;
                if (!$hasRechnungsOrt) $company['rechnungs_ort'] = null;
                if (!$hasRechnungsEmail) $company['rechnungs_email'] = null;
                if (!$hasNotizen) $company['notizen'] = null;
                if (!$hasZugewiesenAn) {
                    $company['zugewiesen_an'] = null;
                    $company['zugewiesen_vorname'] = null;
                    $company['zugewiesen_nachname'] = null;
                    $company['zugewiesen_email'] = null;
                }
                if (!$hasAnsprechpartnerUserId) {
                    $company['ansprechpartner_user_id'] = null;
                    $company['ansprechpartner_vorname'] = null;
                    $company['ansprechpartner_nachname'] = null;
                    $company['ansprechpartner_email'] = null;
                }
                if (!$hasAnsprechpartnerManuellName) {
                    $company['ansprechpartner_manuell_name'] = null;
                }
                if (!$hasAnsprechpartnerManuellEmail) {
                    $company['ansprechpartner_manuell_email'] = null;
                }
                if (!$hasAnsprechpartnerManuellTelefon) {
                    $company['ansprechpartner_manuell_telefon'] = null;
                }
                if (!$hasAnsprechpartnerManuellNotiz) {
                    $company['ansprechpartner_manuell_notiz'] = null;
                }
                if (!$hasEmail) $company['email'] = null;
                if (!$hasTelefonnummer) $company['telefonnummer'] = null;
                if (empty($hasHatWartungsvertrag)) {
                    $company['hat_wartungsvertrag'] = 0;
                } else {
                    $company['hat_wartungsvertrag'] = (int)($company['hat_wartungsvertrag'] ?? 0);
                }
                if (empty($hasWartungZahlungsrhythmus)) {
                    $company['wartung_zahlungsrhythmus'] = null;
                }
                if (empty($hasWartungZahlungstag)) {
                    $company['wartung_zahlungstag'] = null;
                }
                if (empty($hasLagerZugriff)) {
                    $company['lager_zugriff'] = 0;
                } else {
                    $company['lager_zugriff'] = (int)($company['lager_zugriff'] ?? 0);
                }
                if (empty($hasIsPrimary)) {
                    $company['is_primary'] = 0;
                } else {
                    $company['is_primary'] = (int)($company['is_primary'] ?? 0);
                }
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                decrypt_company_row($company);
                echo json_encode([
                    'success' => true,
                    'company' => $company
                ]);
                exit;
            }
            
            // User einer Firma abrufen
            if (isset($_GET['company_id']) && isset($_GET['users'])) {
                $companyId = (int)$_GET['company_id'];
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
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
                    WHERE u.company_id = :company_id
                    ORDER BY u.nachname, u.vorname
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
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
            
            // Tickets einer Firma abrufen
            if (isset($_GET['company_id']) && isset($_GET['tickets'])) {
                $companyId = (int)$_GET['company_id'];
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                }
                
                $sql = "
                    SELECT 
                        t.id,
                        t.ticket_nummer,
                        t.titel,
                        t.status,
                        t.prioritaet,
                        t.erstellt_datum,
                        t.geaendert_datum,
                        cust.name as customer_name,
                        u_erstellt.vorname as ersteller_vorname,
                        u_erstellt.nachname as ersteller_nachname
                    FROM tickets t
                    LEFT JOIN customers cust ON t.customer_id = cust.id
                    LEFT JOIN users u_erstellt ON t.erstellt_von = u_erstellt.id
                    WHERE t.company_id = :company_id
                    ORDER BY t.erstellt_datum DESC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'tickets' => $tickets
                ]);
                exit;
            }
            
            // Kunden einer Firma abrufen
            if (isset($_GET['company_id']) && isset($_GET['customers'])) {
                $companyId = (int)$_GET['company_id'];
                
                // Berechtigung prüfen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    if ($userRole === 'Firmen-Admin' && $userCompanyId != $companyId) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                        exit;
                    }
                }
                
                $sql = "
                    SELECT 
                        c.id,
                        c.name,
                        c.email,
                        c.telefon,
                        c.adresse,
                        c.plz,
                        c.ort,
                        c.status,
                        c.erstellt_datum
                    FROM customers c
                    WHERE c.company_id = :company_id
                    ORDER BY c.name
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'customers' => $customers
                ]);
                exit;
            }
            
            // Alle Firmen abrufen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Prüfen welche Felder in der Tabelle existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM companies");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasEmail = in_array('email', $columns);
                $hasTelefonnummer = in_array('telefonnummer', $columns);
                $hasLieferadresse = in_array('lieferadresse', $columns);
                $hasLieferPlz = in_array('liefer_plz', $columns);
                $hasLieferOrt = in_array('liefer_ort', $columns);
                $hasRechnungsAdresse = in_array('rechnungs_adresse', $columns);
                $hasRechnungsPlz = in_array('rechnungs_plz', $columns);
                $hasRechnungsOrt = in_array('rechnungs_ort', $columns);
                $hasHatWartungsvertrag = in_array('hat_wartungsvertrag', $columns);
                $hasWartungZahlungsrhythmus = in_array('wartung_zahlungsrhythmus', $columns);
                $hasWartungZahlungstag = in_array('wartung_zahlungstag', $columns);
                $hasIsPrimary = in_array('is_primary', $columns);
            } catch (PDOException $e) {
                // Falls Prüfung fehlschlägt, nehmen wir an dass die Felder nicht existieren
                $hasHatWartungsvertrag = false;
                $hasWartungZahlungsrhythmus = false;
                $hasWartungZahlungstag = false;
                $hasEmail = false;
                $hasTelefonnummer = false;
                $hasLieferadresse = false;
                $hasLieferPlz = false;
                $hasLieferOrt = false;
                $hasRechnungsAdresse = false;
                $hasRechnungsPlz = false;
                $hasRechnungsOrt = false;
                $hasIsPrimary = false;
            }
            
            $selectFields = [
                'c.id',
                'c.name',
                'c.domain',
                'c.kundennummer',
                'c.adresse',
                'c.plz',
                'c.ort',
                'c.logo',
                'c.status',
                'c.erstellt_datum',
                'c.geaendert_datum',
                'u_erstellt.vorname as ersteller_vorname',
                'u_erstellt.nachname as ersteller_nachname',
                '(SELECT COUNT(*) FROM users WHERE company_id = c.id) as anzahl_benutzer',
                '(SELECT COUNT(*) FROM customers WHERE company_id = c.id) as anzahl_kunden'
            ];
            
            if ($hasEmail) {
                $selectFields[] = 'c.email';
            }
            if ($hasTelefonnummer) {
                $selectFields[] = 'c.telefonnummer';
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
            if (!empty($hasHatWartungsvertrag)) {
                $selectFields[] = 'c.hat_wartungsvertrag';
            }
            if (!empty($hasWartungZahlungsrhythmus)) {
                $selectFields[] = 'c.wartung_zahlungsrhythmus';
            }
            if (!empty($hasWartungZahlungstag)) {
                $selectFields[] = 'c.wartung_zahlungstag';
            }
            if (!empty($hasIsPrimary)) {
                $selectFields[] = 'c.is_primary';
            }
            
            $sql = "
                SELECT " . implode(', ', $selectFields) . "
                FROM companies c
                LEFT JOIN users u_erstellt ON c.erstellt_von = u_erstellt.id
                ORDER BY c.name ASC
            ";
            
            $stmt = $pdo->query($sql);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Standardwerte für fehlende Felder setzen
            foreach ($companies as &$company) {
                if (!$hasEmail) {
                    $company['email'] = null;
                }
                if (!$hasTelefonnummer) {
                    $company['telefonnummer'] = null;
                }
                if (!$hasLieferadresse) {
                    $company['lieferadresse'] = null;
                }
                if (!$hasLieferPlz) {
                    $company['liefer_plz'] = null;
                }
                if (!$hasLieferOrt) {
                    $company['liefer_ort'] = null;
                }
                if (!$hasRechnungsAdresse) {
                    $company['rechnungs_adresse'] = null;
                }
                if (!$hasRechnungsPlz) {
                    $company['rechnungs_plz'] = null;
                }
                if (!$hasRechnungsOrt) {
                    $company['rechnungs_ort'] = null;
                }
                if (empty($hasHatWartungsvertrag)) {
                    $company['hat_wartungsvertrag'] = 0;
                } else {
                    $company['hat_wartungsvertrag'] = (int)($company['hat_wartungsvertrag'] ?? 0);
                }
                if (empty($hasWartungZahlungsrhythmus)) {
                    $company['wartung_zahlungsrhythmus'] = null;
                }
                if (empty($hasWartungZahlungstag)) {
                    $company['wartung_zahlungstag'] = null;
                }
                if (empty($hasIsPrimary)) {
                    $company['is_primary'] = 0;
                } else {
                    $company['is_primary'] = (int)($company['is_primary'] ?? 0);
                }
            }
            unset($company);
            foreach ($companies as &$c) { decrypt_company_row($c); }
            unset($c);
            // Nach Klartext-Namen sortieren (ORDER BY in SQL gilt für ggf. verschlüsselte Werte).
            // Kein Collator/intl: fehlende Extension oder Locale wirft sonst oft 500 + leere Antwort.
            usort($companies, static function (array $a, array $b): int {
                $na = (string)($a['name'] ?? '');
                $nb = (string)($b['name'] ?? '');
                if (function_exists('mb_strtolower')) {
                    $na = mb_strtolower($na, 'UTF-8');
                    $nb = mb_strtolower($nb, 'UTF-8');
                } else {
                    $na = strtolower($na);
                    $nb = strtolower($nb);
                }
                return strnatcasecmp($na, $nb);
            });
            echo json_encode([
                'success' => true,
                'companies' => $companies
            ]);
            break;
            
        case 'POST':
            // Logo-Upload prüfen
            if (isset($_FILES['logo']) && isset($_POST['company_id'])) {
                // Logo-Upload für bestehende Firma
                // Admin und Techniker können Logos hochladen
                if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                    exit;
                }
                
                $companyId = (int)$_POST['company_id'];
                
                // Prüfen ob Firma existiert
                $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE id = ?");
                $checkStmt->execute([$companyId]);
                if (!$checkStmt->fetch()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
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
                $fileName = 'company_' . $companyId . '_' . time() . '.' . $extension;
                $filePath = $logoUploadDir . $fileName;
                
                // Prüfen ob Verzeichnis beschreibbar ist
                if (!is_writable($logoUploadDir)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis ist nicht beschreibbar']);
                    exit;
                }
                
                // Altes Logo löschen falls vorhanden
                $oldLogoStmt = $pdo->prepare("SELECT logo FROM companies WHERE id = ?");
                $oldLogoStmt->execute([$companyId]);
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
                $updateStmt = $pdo->prepare("UPDATE companies SET logo = ?, geaendert_von = ?, geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$relativePath, $userId, $companyId]);
                
                // Log-Eintrag erstellen
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                        VALUES ('company', ?, ?, 'updated', 'logo', ?, ?, NOW())
                    ");
                    $logStmt->execute([
                        $companyId,
                        $userId,
                        $oldLogo ?: '',
                        $relativePath
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags (Logo Upload): " . $e->getMessage());
                }
                
                // Firmennamen für Benachrichtigung abrufen
                $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
                $companyStmt->execute([$companyId]);
                $companyName = decrypt_from_db($companyStmt->fetchColumn()) ?: 'Unbekannt';
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'company_logo_upload',
                    'Logo hochgeladen: ' . $companyName,
                    'Ein neues Logo wurde für die Firma "' . $companyName . '" von ' . $userName . ' hochgeladen.',
                    'niedrig',
                    'companies/detail.php?id=' . $companyId,
                    'company',
                    $companyId
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Logo erfolgreich hochgeladen',
                    'logo_path' => $relativePath
                ]);
                exit;
            }
            
            // Neue Firma erstellen
            if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
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
            
            $name = trim($data['name']);
            $domain = isset($data['domain']) ? trim($data['domain']) : null;
            $kundennummer = isset($data['kundennummer']) ? trim($data['kundennummer']) : null;
            $adresse = isset($data['adresse']) ? trim($data['adresse']) : null;
            $plz = isset($data['plz']) ? trim($data['plz']) : null;
            $ort = isset($data['ort']) ? trim($data['ort']) : null;
            // Liefer- und Rechnungsadresse optional: wenn leer, Hauptadresse übernehmen
            $lieferadresse = isset($data['lieferadresse']) && $data['lieferadresse'] !== '' ? trim($data['lieferadresse']) : null;
            $lieferPlz = isset($data['liefer_plz']) && $data['liefer_plz'] !== '' ? trim($data['liefer_plz']) : null;
            $lieferOrt = isset($data['liefer_ort']) && $data['liefer_ort'] !== '' ? trim($data['liefer_ort']) : null;
            if ($lieferadresse === null) $lieferadresse = $adresse;
            if ($lieferPlz === null) $lieferPlz = $plz;
            if ($lieferOrt === null) $lieferOrt = $ort;
            $rechnungsAdresse = isset($data['rechnungs_adresse']) && $data['rechnungs_adresse'] !== '' ? trim($data['rechnungs_adresse']) : null;
            $rechnungsPlz = isset($data['rechnungs_plz']) && $data['rechnungs_plz'] !== '' ? trim($data['rechnungs_plz']) : null;
            $rechnungsOrt = isset($data['rechnungs_ort']) && $data['rechnungs_ort'] !== '' ? trim($data['rechnungs_ort']) : null;
            if ($rechnungsAdresse === null) $rechnungsAdresse = $adresse;
            if ($rechnungsPlz === null) $rechnungsPlz = $plz;
            if ($rechnungsOrt === null) $rechnungsOrt = $ort;
            $rechnungsEmail = isset($data['rechnungs_email']) ? trim($data['rechnungs_email']) : null;
            $email = isset($data['email']) ? trim($data['email']) : null;
            $telefonnummer = isset($data['telefonnummer']) ? trim($data['telefonnummer']) : null;
            $notizen = isset($data['notizen']) ? trim($data['notizen']) : null;
            $zugewiesenAn = isset($data['zugewiesen_an']) ? (int)$data['zugewiesen_an'] : null;
            $ansprechpartnerUserId = isset($data['ansprechpartner_user_id']) && $data['ansprechpartner_user_id'] ? (int)$data['ansprechpartner_user_id'] : null;
            $ansprechpartnerManuellName = isset($data['ansprechpartner_manuell_name']) && $data['ansprechpartner_manuell_name'] ? trim($data['ansprechpartner_manuell_name']) : null;
            $ansprechpartnerManuellEmail = isset($data['ansprechpartner_manuell_email']) && $data['ansprechpartner_manuell_email'] ? trim($data['ansprechpartner_manuell_email']) : null;
            $ansprechpartnerManuellTelefon = isset($data['ansprechpartner_manuell_telefon']) && $data['ansprechpartner_manuell_telefon'] ? trim($data['ansprechpartner_manuell_telefon']) : null;
            $ansprechpartnerManuellNotiz = isset($data['ansprechpartner_manuell_notiz']) && $data['ansprechpartner_manuell_notiz'] ? trim($data['ansprechpartner_manuell_notiz']) : null;
            $logo = isset($data['logo']) ? trim($data['logo']) : null;
            $status = isset($data['status']) ? $data['status'] : 'aktiv';
            $isPrimary = !empty($data['is_primary']) ? 1 : 0;

            // Primärfirma darf nur im Admin-Bereich gesetzt werden
            if ($isPrimary && $userRole !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Admin darf eine Primärfirma setzen']);
                exit;
            }
            
            // Wenn manueller Ansprechpartner gesetzt ist, User-ID zurücksetzen
            if ($ansprechpartnerManuellName) {
                $ansprechpartnerUserId = null;
            }
            
            // Prüfen ob User zur Firma gehört (wenn User ausgewählt)
            if ($ansprechpartnerUserId) {
                // Firma-ID aus Request
                $checkCompanyId = null;
                // Bei POST gibt es noch keine company_id, daher prüfen wir später beim INSERT
                // Die Validierung erfolgt nach dem INSERT, wenn wir die company_id haben
            }
            
            // Prüfen ob zugewiesener Mitarbeiter existiert und die richtige Rolle hat
            if ($zugewiesenAn) {
                $checkUser = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? AND rolle IN ('Admin', 'Techniker')");
                $checkUser->execute([$zugewiesenAn]);
                if (!$checkUser->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Zugewiesener Mitarbeiter muss Admin oder Techniker sein']);
                    exit;
                }
            }
            
            // Validierung
            $allowedStatus = ['aktiv', 'inaktiv', 'gesperrt'];
            if (!in_array($status, $allowedStatus)) {
                $status = 'aktiv';
            }
            if ($isPrimary && $status !== 'aktiv') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Eine Primärfirma muss aktiv sein']);
                exit;
            }
            
            // Prüfen ob Kundennummer bereits existiert (falls angegeben)
            if ($kundennummer) {
                $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE kundennummer = ? OR kundennummer = ?");
                $checkStmt->execute([$kundennummer, encrypt_company_value($kundennummer)]);
                if ($checkStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Diese Kundennummer existiert bereits']);
                    exit;
                }
            }
            
            // Prüfen ob email und telefonnummer Spalten existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM companies");
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasEmail = in_array('email', $columns);
                $hasTelefonnummer = in_array('telefonnummer', $columns);
                $hasIsPrimary = in_array('is_primary', $columns);
            } catch (PDOException $e) {
                $hasEmail = false;
                $hasTelefonnummer = false;
                $hasIsPrimary = false;
            }
            
            $insertFields = ['name', 'domain', 'kundennummer', 'adresse', 'plz', 'ort', 'lieferadresse', 'liefer_plz', 'liefer_ort', 'rechnungs_adresse', 'rechnungs_plz', 'rechnungs_ort', 'rechnungs_email'];
            $insertValues = [encrypt_company_value($name), encrypt_company_value($domain), encrypt_company_value($kundennummer), encrypt_company_value($adresse), encrypt_company_value($plz), encrypt_company_value($ort), encrypt_company_value($lieferadresse), encrypt_company_value($lieferPlz), encrypt_company_value($lieferOrt), encrypt_company_value($rechnungsAdresse), encrypt_company_value($rechnungsPlz), encrypt_company_value($rechnungsOrt), encrypt_company_value($rechnungsEmail)];
            $insertPlaceholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
            
            if ($hasEmail) {
                $insertFields[] = 'email';
                $insertValues[] = encrypt_company_value($email);
                $insertPlaceholders[] = '?';
            }
            if ($hasTelefonnummer) {
                $insertFields[] = 'telefonnummer';
                $insertValues[] = encrypt_company_value($telefonnummer);
                $insertPlaceholders[] = '?';
            }
            if ($hasIsPrimary) {
                $insertFields[] = 'is_primary';
                $insertValues[] = $isPrimary;
                $insertPlaceholders[] = '?';
            }
            
            // Prüfen ob Ansprechpartner-Felder existieren
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM companies");
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
            
            $insertFields = array_merge($insertFields, ['notizen', 'zugewiesen_an', 'logo', 'status', 'erstellt_von', 'erstellt_datum']);
            $insertValues = array_merge($insertValues, [encrypt_company_value($notizen), $zugewiesenAn, $logo, $status, $userId]);
            $insertPlaceholders = array_merge($insertPlaceholders, ['?', '?', '?', '?', '?', 'NOW()']);
            
            if ($hasAnsprechpartnerUserId) {
                $insertFields[] = 'ansprechpartner_user_id';
                $insertValues[] = $ansprechpartnerUserId;
                $insertPlaceholders[] = '?';
            }
            if ($hasAnsprechpartnerManuellName) {
                $insertFields[] = 'ansprechpartner_manuell_name';
                $insertValues[] = encrypt_company_value($ansprechpartnerManuellName);
                $insertPlaceholders[] = '?';
            }
            if ($hasAnsprechpartnerManuellEmail) {
                $insertFields[] = 'ansprechpartner_manuell_email';
                $insertValues[] = encrypt_company_value($ansprechpartnerManuellEmail);
                $insertPlaceholders[] = '?';
            }
            if ($hasAnsprechpartnerManuellTelefon) {
                $insertFields[] = 'ansprechpartner_manuell_telefon';
                $insertValues[] = encrypt_company_value($ansprechpartnerManuellTelefon);
                $insertPlaceholders[] = '?';
            }
            if ($hasAnsprechpartnerManuellNotiz) {
                $insertFields[] = 'ansprechpartner_manuell_notiz';
                $insertValues[] = encrypt_company_value($ansprechpartnerManuellNotiz);
                $insertPlaceholders[] = '?';
            }
            if (in_array('hat_wartungsvertrag', $columns)) {
                $insertFields[] = 'hat_wartungsvertrag';
                $insertValues[] = (!empty($data['hat_wartungsvertrag'])) ? 1 : 0;
                $insertPlaceholders[] = '?';
            }
            if (in_array('wartung_zahlungsrhythmus', $columns)) {
                $insertFields[] = 'wartung_zahlungsrhythmus';
                $v = isset($data['wartung_zahlungsrhythmus']) && in_array($data['wartung_zahlungsrhythmus'], ['woechentlich', 'monatlich', 'vierteljaehrlich', 'halbjaehrlich', 'jaehrlich'], true) ? $data['wartung_zahlungsrhythmus'] : null;
                $insertValues[] = $v;
                $insertPlaceholders[] = '?';
            }
            if (in_array('wartung_zahlungstag', $columns)) {
                $insertFields[] = 'wartung_zahlungstag';
                $tag = isset($data['wartung_zahlungstag']) ? (int)$data['wartung_zahlungstag'] : null;
                if ($tag !== null && ($tag < 1 || $tag > 31)) $tag = null;
                $insertValues[] = $tag;
                $insertPlaceholders[] = '?';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO companies (" . implode(', ', $insertFields) . ")
                VALUES (" . implode(', ', $insertPlaceholders) . ")
            ");
            $stmt->execute($insertValues);
            
            $companyId = $pdo->lastInsertId();

            if (!empty($hasIsPrimary)) {
                if ($isPrimary) {
                    enforceSinglePrimaryCompany($pdo, (int)$companyId);
                } else {
                    $countPrimaryStmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_primary = 1");
                    if ((int)$countPrimaryStmt->fetchColumn() === 0) {
                        $setPrimaryStmt = $pdo->prepare("UPDATE companies SET is_primary = 1 WHERE id = ?");
                        $setPrimaryStmt->execute([$companyId]);
                    }
                    enforceSinglePrimaryCompany($pdo);
                }
            }
            
            // Prüfen ob User zur Firma gehört (wenn User als Ansprechpartner ausgewählt)
            if ($ansprechpartnerUserId && $companyId) {
                $userCheckStmt = $pdo->prepare("SELECT id, company_id FROM users WHERE id = ?");
                $userCheckStmt->execute([$ansprechpartnerUserId]);
                $selectedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($selectedUser && $selectedUser['company_id'] != $companyId) {
                    // Rollback: Firma löschen
                    $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$companyId]);
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Der ausgewählte User gehört nicht zu dieser Firma']);
                    exit;
                }
            }
            
            // Hauptordner für die Wissensdatenbank erstellen
            try {
                $kbFolderId = 'company-' . $companyId;
                $kbFolderSlug = preg_replace('~[^\pL\d]+~u', '-', strtolower($name));
                $kbFolderSlug = iconv('utf-8', 'us-ascii//TRANSLIT', $kbFolderSlug);
                $kbFolderSlug = preg_replace('~[^-\w]+~', '', $kbFolderSlug);
                $kbFolderSlug = trim($kbFolderSlug, '-');
                if (empty($kbFolderSlug)) $kbFolderSlug = 'firma-' . $companyId;
                
                // Prüfen ob Slug bereits existiert
                $slugCheck = $pdo->prepare("SELECT id FROM kb_pages WHERE slug = ? LIMIT 1");
                $slugCheck->execute([$kbFolderSlug]);
                if ($slugCheck->fetch()) {
                    $kbFolderSlug = $kbFolderSlug . '-' . $companyId;
                }
                
                $defaultContent = json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph']]]);
                $kbStmt = $pdo->prepare("INSERT INTO kb_pages (id, title, slug, content, content_type, parent_id, order_index, author_id, company_id, is_system_folder, created_at, updated_at) VALUES (?, ?, ?, ?, 'json', NULL, ?, ?, ?, 0, NOW(), NOW())");
                $kbStmt->execute([$kbFolderId, $name, $kbFolderSlug, $defaultContent, $companyId, $userId, $companyId]);
                // Systemordner Anrufe, Notizen, Probleme und Wiki erstellen
                $sysCallsId = 'kb-sys-calls-' . $kbFolderId;
                $sysNotesId = 'kb-sys-notes-' . $kbFolderId;
                $sysProblemsId = 'kb-sys-problems-' . $kbFolderId;
                $sysWikiId = 'kb-sys-wiki-' . $kbFolderId;
                $sysSlugCalls = '__system-anruf-' . $companyId;
                $sysSlugNotes = '__system-notiz-' . $companyId;
                $sysSlugProblems = '__system-probleme-' . $companyId;
                $sysSlugWiki = '__system-wiki-' . $companyId;
                $sysStmt = $pdo->prepare("INSERT INTO kb_pages (id, title, slug, content, content_type, parent_id, order_index, author_id, company_id, is_system_folder, system_type, created_at, updated_at) VALUES (?, 'Anrufe', ?, ?, 'json', ?, -3, ?, ?, 1, 'calls', NOW(), NOW()), (?, 'Notizen', ?, ?, 'json', ?, -2, ?, ?, 1, 'notes', NOW(), NOW()), (?, 'Ticket-Archiv', ?, ?, 'json', ?, -1, ?, ?, 1, 'problems', NOW(), NOW()), (?, 'Wiki', ?, ?, 'json', ?, 0, ?, ?, 1, 'wiki', NOW(), NOW())");
                $sysStmt->execute([$sysCallsId, $sysSlugCalls, $defaultContent, $kbFolderId, $userId, $companyId, $sysNotesId, $sysSlugNotes, $defaultContent, $kbFolderId, $userId, $companyId, $sysProblemsId, $sysSlugProblems, $defaultContent, $kbFolderId, $userId, $companyId, $sysWikiId, $sysSlugWiki, $defaultContent, $kbFolderId, $userId, $companyId]);
            } catch (PDOException $e) {
                // Fehler beim Erstellen des KB-Ordners nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des KB-Hauptordners für Firma " . $companyId . ": " . $e->getMessage());
            }
            
            // Log-Eintrag für Erstellung erstellen
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'created', 'Firma erstellt', NOW())
                ");
                $logStmt->execute([$companyId, $userId]);
            } catch (PDOException $e) {
                // Log-Fehler nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $companyId,
                'company_created',
                'Neue Firma erstellt: ' . $name,
                'Eine neue Firma "' . $name . '" wurde von ' . $userName . ' erstellt.',
                'normal',
                'companies/detail.php?id=' . $companyId,
                'company',
                $companyId
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Firma erfolgreich erstellt',
                'company_id' => $companyId
            ]);
            break;
            
        case 'PUT':
            // Firma aktualisieren - nur Admin
            if ($userRole !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['company_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'company_id fehlt']);
                exit;
            }
            
            $companyId = (int)$data['company_id'];
            
            // Prüfen ob Firma existiert und alte Werte für Log abrufen
            $checkStmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $checkStmt->execute([$companyId]);
            $oldCompany = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($oldCompany) {
                decrypt_company_row($oldCompany);
            }
            if (!$oldCompany) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            
            $isCurrentlyPrimary = isset($oldCompany['is_primary']) && (int)$oldCompany['is_primary'] === 1;
            $setPrimaryRequested = isset($data['is_primary']) && (int)!empty($data['is_primary']) === 1;
            $statusChangeRequested = isset($data['status']);
            $requestedStatus = $statusChangeRequested ? $data['status'] : ($oldCompany['status'] ?? 'aktiv');
            $willBePrimary = $isCurrentlyPrimary || $setPrimaryRequested;

            // Eigene Firma darf nicht gesperrt oder auf inaktiv gesetzt werden
            if ($userCompanyId && (int)$userCompanyId === (int)$companyId && isset($data['status']) && in_array($data['status'], ['gesperrt', 'inaktiv'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Die eigene Firma kann nicht gesperrt oder deaktiviert werden']);
                exit;
            }
            if ($willBePrimary && in_array($requestedStatus, ['gesperrt', 'inaktiv'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Die Primärfirma kann nicht gesperrt oder deaktiviert werden']);
                exit;
            }
            
            // Alte Werte für Benachrichtigungen speichern
            $oldCompanyName = $oldCompany['name'];
            $oldCompanyErstelltVon = $oldCompany['erstellt_von'];
            
            // Update-Felder zusammenbauen
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $updateParams[] = encrypt_company_value(trim($data['name']));
            }
            if (isset($data['domain'])) {
                $updateFields[] = "domain = ?";
                $updateParams[] = encrypt_company_value($data['domain'] ? trim($data['domain']) : null);
            }
            if (isset($data['kundennummer'])) {
                // Prüfen ob neue Kundennummer bereits existiert
                if ($data['kundennummer']) {
                    $kn = trim($data['kundennummer']);
                    $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE (kundennummer = ? OR kundennummer = ?) AND id != ?");
                    $checkStmt->execute([$kn, encrypt_company_value($kn), $companyId]);
                    if ($checkStmt->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Diese Kundennummer existiert bereits']);
                        exit;
                    }
                }
                $updateFields[] = "kundennummer = ?";
                $updateParams[] = encrypt_company_value($data['kundennummer'] ? trim($data['kundennummer']) : null);
            }
            if (isset($data['adresse'])) {
                $updateFields[] = "adresse = ?";
                $updateParams[] = encrypt_company_value($data['adresse'] ? trim($data['adresse']) : null);
            }
            if (isset($data['plz'])) {
                $updateFields[] = "plz = ?";
                $updateParams[] = encrypt_company_value($data['plz'] ? trim($data['plz']) : null);
            }
            if (isset($data['ort'])) {
                $updateFields[] = "ort = ?";
                $updateParams[] = encrypt_company_value($data['ort'] ? trim($data['ort']) : null);
            }
            if (isset($data['lieferadresse'])) {
                $updateFields[] = "lieferadresse = ?";
                $updateParams[] = encrypt_company_value($data['lieferadresse'] ? trim($data['lieferadresse']) : null);
            }
            if (isset($data['liefer_plz'])) {
                $updateFields[] = "liefer_plz = ?";
                $updateParams[] = encrypt_company_value($data['liefer_plz'] ? trim($data['liefer_plz']) : null);
            }
            if (isset($data['liefer_ort'])) {
                $updateFields[] = "liefer_ort = ?";
                $updateParams[] = encrypt_company_value($data['liefer_ort'] ? trim($data['liefer_ort']) : null);
            }
            if (isset($data['rechnungs_adresse'])) {
                $updateFields[] = "rechnungs_adresse = ?";
                $updateParams[] = encrypt_company_value($data['rechnungs_adresse'] ? trim($data['rechnungs_adresse']) : null);
            }
            if (isset($data['rechnungs_plz'])) {
                $updateFields[] = "rechnungs_plz = ?";
                $updateParams[] = encrypt_company_value($data['rechnungs_plz'] ? trim($data['rechnungs_plz']) : null);
            }
            if (isset($data['rechnungs_ort'])) {
                $updateFields[] = "rechnungs_ort = ?";
                $updateParams[] = encrypt_company_value($data['rechnungs_ort'] ? trim($data['rechnungs_ort']) : null);
            }
            if (isset($data['rechnungs_email'])) {
                $updateFields[] = "rechnungs_email = ?";
                $updateParams[] = encrypt_company_value($data['rechnungs_email'] ? trim($data['rechnungs_email']) : null);
            }
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $updateParams[] = encrypt_company_value($data['email'] ? trim($data['email']) : null);
            }
            if (isset($data['telefonnummer'])) {
                $updateFields[] = "telefonnummer = ?";
                $updateParams[] = encrypt_company_value($data['telefonnummer'] ? trim($data['telefonnummer']) : null);
            }
            if (isset($data['notizen'])) {
                $updateFields[] = "notizen = ?";
                $updateParams[] = encrypt_company_value($data['notizen'] ? trim($data['notizen']) : null);
            }
            if (isset($data['zugewiesen_an'])) {
                $zugewiesenAn = $data['zugewiesen_an'] ? (int)$data['zugewiesen_an'] : null;
                // Prüfen ob zugewiesener Mitarbeiter existiert und die richtige Rolle hat
                if ($zugewiesenAn) {
                    $checkUser = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? AND rolle IN ('Admin', 'Techniker')");
                    $checkUser->execute([$zugewiesenAn]);
                    if (!$checkUser->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Zugewiesener Mitarbeiter muss Admin oder Techniker sein']);
                        exit;
                    }
                }
                $updateFields[] = "zugewiesen_an = ?";
                $updateParams[] = $zugewiesenAn;
            }
            if (isset($data['ansprechpartner_user_id']) || isset($data['ansprechpartner_manuell_name'])) {
                // Prüfen ob Ansprechpartner-Felder existieren
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM companies");
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
                    
                    if ($selectedUser && $oldCompany['id'] && $selectedUser['company_id'] != $oldCompany['id']) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Der ausgewählte User gehört nicht zu dieser Firma']);
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
                    $updateParams[] = encrypt_company_value($ansprechpartnerManuellName);
                }
                if ($hasAnsprechpartnerManuellEmail) {
                    $updateFields[] = "ansprechpartner_manuell_email = ?";
                    $updateParams[] = encrypt_company_value($ansprechpartnerManuellEmail);
                }
                if ($hasAnsprechpartnerManuellTelefon) {
                    $updateFields[] = "ansprechpartner_manuell_telefon = ?";
                    $updateParams[] = encrypt_company_value($ansprechpartnerManuellTelefon);
                }
                if ($hasAnsprechpartnerManuellNotiz) {
                    $updateFields[] = "ansprechpartner_manuell_notiz = ?";
                    $updateParams[] = encrypt_company_value($ansprechpartnerManuellNotiz);
                }
            }
            if (isset($data['logo'])) {
                $updateFields[] = "logo = ?";
                $updateParams[] = $data['logo'] ? trim($data['logo']) : null;
            }
            if (isset($data['status'])) {
                $allowedStatus = ['aktiv', 'inaktiv', 'gesperrt'];
                if (in_array($data['status'], $allowedStatus)) {
                    $updateFields[] = "status = ?";
                    $updateParams[] = $data['status'];
                }
            }
            if (isset($data['is_primary'])) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'is_primary'");
                    if ($colStmt->rowCount() > 0) {
                        $updateFields[] = "is_primary = ?";
                        $updateParams[] = $setPrimaryRequested ? 1 : 0;
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            if (isset($data['hat_wartungsvertrag'])) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'hat_wartungsvertrag'");
                    if ($colStmt->rowCount() > 0) {
                        $updateFields[] = "hat_wartungsvertrag = ?";
                        $updateParams[] = $data['hat_wartungsvertrag'] ? 1 : 0;
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            if (isset($data['lager_zugriff'])) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'lager_zugriff'");
                    if ($colStmt->rowCount() > 0) {
                        $updateFields[] = "lager_zugriff = ?";
                        $updateParams[] = !empty($data['lager_zugriff']) ? 1 : 0;
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            if (isset($data['wartung_zahlungsrhythmus'])) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'wartung_zahlungsrhythmus'");
                    if ($colStmt->rowCount() > 0) {
                        $v = in_array($data['wartung_zahlungsrhythmus'], ['woechentlich', 'monatlich', 'vierteljaehrlich', 'halbjaehrlich', 'jaehrlich'], true) ? $data['wartung_zahlungsrhythmus'] : null;
                        $updateFields[] = "wartung_zahlungsrhythmus = ?";
                        $updateParams[] = $v;
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            if (isset($data['wartung_zahlungstag'])) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'wartung_zahlungstag'");
                    if ($colStmt->rowCount() > 0) {
                        $tag = $data['wartung_zahlungstag'] === '' || $data['wartung_zahlungstag'] === null ? null : (int)$data['wartung_zahlungstag'];
                        if ($tag !== null && ($tag < 1 || $tag > 31)) $tag = null;
                        $updateFields[] = "wartung_zahlungstag = ?";
                        $updateParams[] = $tag;
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Keine Felder zum Aktualisieren']);
                exit;
            }
            
            $updateFields[] = "geaendert_von = ?";
            $updateFields[] = "geaendert_datum = NOW()";
            $updateParams[] = $userId;
            $updateParams[] = $companyId;
            
            // Log-Einträge erstellen für geänderte Felder (vor dem Update)
            $fieldsToCheck = [
                'name' => 'name',
                'domain' => 'domain',
                'kundennummer' => 'kundennummer',
                'adresse' => 'adresse',
                'plz' => 'plz',
                'ort' => 'ort',
                'lieferadresse' => 'lieferadresse',
                'liefer_plz' => 'liefer_plz',
                'liefer_ort' => 'liefer_ort',
                'rechnungs_adresse' => 'rechnungs_adresse',
                'rechnungs_plz' => 'rechnungs_plz',
                'rechnungs_ort' => 'rechnungs_ort',
                'rechnungs_email' => 'rechnungs_email',
                'email' => 'email',
                'telefonnummer' => 'telefonnummer',
                'notizen' => 'notizen',
                'zugewiesen_an' => 'zugewiesen_an',
                'ansprechpartner_user_id' => 'ansprechpartner_user_id',
                'ansprechpartner_manuell_name' => 'ansprechpartner_manuell_name',
                'ansprechpartner_manuell_email' => 'ansprechpartner_manuell_email',
                'ansprechpartner_manuell_telefon' => 'ansprechpartner_manuell_telefon',
                'ansprechpartner_manuell_notiz' => 'ansprechpartner_manuell_notiz',
                'logo' => 'logo',
                'status' => 'status',
                'is_primary' => 'is_primary'
            ];
            
            foreach ($fieldsToCheck as $dataKey => $dbField) {
                if (isset($data[$dataKey])) {
                    $oldValue = $oldCompany[$dbField] ?? null;
                    $newValue = $data[$dataKey];
                    
                    // Spezielle Behandlung für zugewiesen_an und ansprechpartner_user_id (User-ID zu Name konvertieren)
                    if ($dbField === 'zugewiesen_an' || $dbField === 'ansprechpartner_user_id') {
                        $oldValueStr = '';
                        $newValueStr = '';
                        
                        if ($oldValue) {
                            $oldUserStmt = $pdo->prepare("SELECT vorname, nachname, email FROM users WHERE id = ?");
                            $oldUserStmt->execute([$oldValue]);
                            $oldUser = $oldUserStmt->fetch(PDO::FETCH_ASSOC);
                            if ($oldUser) {
                                $oldValueStr = trim(($oldUser['vorname'] ?? '') . ' ' . ($oldUser['nachname'] ?? '')) ?: $oldUser['email'];
                            }
                        } else {
                            $oldValueStr = '';
                        }
                        
                        if ($newValue) {
                            $newUserStmt = $pdo->prepare("SELECT vorname, nachname, email FROM users WHERE id = ?");
                            $newUserStmt->execute([$newValue]);
                            $newUser = $newUserStmt->fetch(PDO::FETCH_ASSOC);
                            if ($newUser) {
                                $newValueStr = trim(($newUser['vorname'] ?? '') . ' ' . ($newUser['nachname'] ?? '')) ?: $newUser['email'];
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
                                    VALUES ('company', ?, ?, 'updated', ?, ?, ?, NOW())
                                ");
                                $logStmt->execute([
                                    $companyId,
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
                        $newValue = is_string($newValue) ? trim($newValue) : $newValue;
                        if ($newValue === '') $newValue = null;
                        
                        // Vergleich: NULL und leere Strings werden gleich behandelt
                        $oldValueForCompare = ($oldValue === null || $oldValue === '') ? null : (string)$oldValue;
                        $newValueForCompare = ($newValue === null || $newValue === '') ? null : (string)$newValue;
                        
                        // Nur loggen wenn sich der Wert geändert hat
                        if ($oldValueForCompare !== $newValueForCompare) {
                            $oldValueStr = $oldValue !== null ? (string)$oldValue : '';
                            $newValueStr = $newValue !== null ? (string)$newValue : '';
                            
                            // Log-Eintrag erstellen
                            try {
                                $logStmt = $pdo->prepare("
                                    INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                                    VALUES ('company', ?, ?, 'updated', ?, ?, ?, NOW())
                                ");
                                $logStmt->execute([
                                    $companyId,
                                    $userId,
                                    $dbField,
                                    $oldValueStr,
                                    $newValueStr
                                ]);
                            } catch (PDOException $e) {
                                // Log-Fehler nicht kritisch, nur protokollieren
                                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
            
            $sql = "UPDATE companies SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);

            if ($setPrimaryRequested) {
                enforceSinglePrimaryCompany($pdo, (int)$companyId);
            } else {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'is_primary'");
                    if ($colStmt->rowCount() > 0) {
                        $countPrimaryStmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_primary = 1");
                        if ((int)$countPrimaryStmt->fetchColumn() === 0) {
                            $setPrimaryStmt = $pdo->prepare("UPDATE companies SET is_primary = 1 WHERE id = ?");
                            $setPrimaryStmt->execute([$companyId]);
                        }
                        enforceSinglePrimaryCompany($pdo);
                    }
                } catch (PDOException $e) { /* Spalte existiert nicht */ }
            }
            
            // Prüfen ob Status auf 'gesperrt' geändert wurde oder von 'gesperrt' auf 'aktiv'
            $statusChangedToGesperrt = false;
            $statusChangedFromGesperrt = false;
            $oldStatus = $oldCompany['status'] ?? 'aktiv';
            $newStatus = isset($data['status']) ? $data['status'] : $oldStatus;
            if (isset($data['status']) && $data['status'] === 'gesperrt' && $oldStatus !== 'gesperrt') {
                $statusChangedToGesperrt = true;
            } elseif (isset($data['status']) && $oldStatus === 'gesperrt' && $data['status'] !== 'gesperrt') {
                $statusChangedFromGesperrt = true;
            }
            
            // Benachrichtigungen für Änderungen erstellen
            $companyName = isset($data['name']) ? trim($data['name']) : $oldCompanyName;
            $companyName = $companyName ?: 'Unbekannt';
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            if ($statusChangedToGesperrt) {
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'company_status_changed',
                    'Firma gesperrt: ' . $companyName,
                    'Die Firma "' . $companyName . '" wurde von ' . $userName . ' gesperrt.',
                    'hoch',
                    'companies/detail.php?id=' . $companyId,
                    'company',
                    $companyId
                );
            } elseif ($statusChangedFromGesperrt) {
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'company_status_changed',
                    'Firma entsperrt: ' . $companyName,
                    'Die Firma "' . $companyName . '" wurde von ' . $userName . ' entsperrt.',
                    'hoch',
                    'companies/detail.php?id=' . $companyId,
                    'company',
                    $companyId
                );
            } else {
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'company_updated',
                    'Firma aktualisiert: ' . $companyName,
                    'Die Firma "' . $companyName . '" wurde von ' . $userName . ' aktualisiert.',
                    'normal',
                    'companies/detail.php?id=' . $companyId,
                    'company',
                    $companyId
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Firma aktualisiert']);
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
            
            $companyId = (int)$_GET['id'];
            
            // Prüfen ob Firma existiert
            $hasIsPrimary = false;
            try {
                $colStmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'is_primary'");
                $hasIsPrimary = $colStmt->rowCount() > 0;
            } catch (PDOException $e) {
                $hasIsPrimary = false;
            }
            $selectCompanySql = $hasIsPrimary
                ? "SELECT id, name, status, is_primary FROM companies WHERE id = ?"
                : "SELECT id, name, status, 0 AS is_primary FROM companies WHERE id = ?";
            $checkStmt = $pdo->prepare($selectCompanySql);
            $checkStmt->execute([$companyId]);
            $company = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$company) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Firma nicht gefunden']);
                exit;
            }
            
            // Eigene Firma darf weder deaktiviert noch gelöscht werden
            if ($userCompanyId && (int)$userCompanyId === (int)$companyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Die eigene Firma kann nicht deaktiviert oder gelöscht werden']);
                exit;
            }
            if ((int)($company['is_primary'] ?? 0) === 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Die Primärfirma kann nicht deaktiviert oder gelöscht werden']);
                exit;
            }
            
            // Techniker setzen Firmen auf "inaktiv" statt sie zu löschen
            if ($userRole === 'Techniker') {
                // Prüfen ob Firma bereits inaktiv ist
                if ($company['status'] === 'inaktiv') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Firma ist bereits inaktiv']);
                    exit;
                }
                
                // Log-Eintrag für Deaktivierung erstellen
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum)
                        VALUES ('company', ?, ?, 'updated', 'status', ?, 'inaktiv', NOW())
                    ");
                    $logStmt->execute([$companyId, $userId, $company['status']]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
                }
                
                // Status auf inaktiv setzen
                $updateStmt = $pdo->prepare("UPDATE companies SET status = 'inaktiv', geaendert_von = ?, geaendert_datum = NOW() WHERE id = ?");
                $updateStmt->execute([$userId, $companyId]);
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                $companyName = $company['name'];
                createNotificationsForAction(
                    $userId,
                    $companyId,
                    'company_status_changed',
                    'Firma auf inaktiv gesetzt: ' . $companyName,
                    'Die Firma "' . $companyName . '" wurde von ' . $userName . ' auf inaktiv gesetzt.',
                    'hoch',
                    'companies/detail.php?id=' . $companyId,
                    'company',
                    $companyId
                );
                
                echo json_encode(['success' => true, 'message' => 'Firma wurde auf inaktiv gesetzt']);
                break;
            }
            
            // Nur Admins dürfen wirklich löschen
            if ($userRole !== 'Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen Firmen wirklich löschen']);
                exit;
            }
            
            // Prüfen ob Firma noch verwendet wird (nur beim echten Löschen)
            $checkUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
            $checkUsers->execute([$companyId]);
            if ($checkUsers->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Firma kann nicht gelöscht werden, da noch Benutzer zugeordnet sind']);
                exit;
            }
            
            // Log-Eintrag für Löschung erstellen (vor dem Löschen)
            $companyName = $company['name'];
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO logs (kategorie, entity_id, user_id, action, beschreibung, erstellt_datum)
                    VALUES ('company', ?, ?, 'deleted', 'Firma gelöscht', NOW())
                ");
                $logStmt->execute([$companyId, $userId]);
            } catch (PDOException $e) {
                // Log-Fehler nicht kritisch, nur protokollieren
                error_log("Fehler beim Erstellen des Log-Eintrags: " . $e->getMessage());
            }
            
            // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
            createNotificationsForAction(
                $userId,
                $companyId,
                'company_deleted',
                'Firma gelöscht: ' . $companyName,
                'Die Firma "' . $companyName . '" wurde von ' . $userName . ' gelöscht.',
                'kritisch',
                'companies/',
                'company',
                $companyId
            );
            
            // Zugehörige Wissensdatenbank-Seiten (Firmenordner, Anrufe, Notizen, alle Kinder) soft-deleten
            $kbStmt = $pdo->prepare("UPDATE kb_pages SET deleted_at = NOW() WHERE company_id = ?");
            $kbStmt->execute([$companyId]);

            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            
            echo json_encode(['success' => true, 'message' => 'Firma gelöscht']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Companies API Error: " . $e->getMessage());
    error_log("Companies API Error Trace: " . $e->getTraceAsString());
    // In Entwicklungsumgebung mehr Details anzeigen
    $errorMessage = 'Datenbankfehler';
    if (defined('DEBUG') && DEBUG) {
        $errorMessage = 'Datenbankfehler: ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'error' => $errorMessage]);
}
