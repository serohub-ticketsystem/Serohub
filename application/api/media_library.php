<?php
/**
 * Medienbibliothek: Bilder unter uploads/images/ (Kunden- & Firmenlogos u. a.)
 * GET: Liste mit optionaler Suche, Sortierung, Filter nach Dateiname-Prefix.
 */
session_start();
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sessionUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
    $userRole = $sessionUser['rolle'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

// Wer Kunden- oder Firmenformulare nutzen darf, darf die Bibliothek lesen
if (!in_array($userRole, ['Admin', 'Techniker', 'Firmen-Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$uploadsDir = dirname(__DIR__) . '/uploads/images/';
if (!is_dir($uploadsDir)) {
    echo json_encode([
        'success' => true,
        'items' => [],
        'base_url' => rtrim(BASE_URL, '/'),
    ]);
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$kind = isset($_GET['kind']) ? trim((string)$_GET['kind']) : 'all';
if (!in_array($kind, ['all', 'customer', 'company'], true)) {
    $kind = 'all';
}

$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'mtime_desc';
$allowedSort = ['mtime_desc', 'mtime_asc', 'name_asc', 'name_desc', 'size_desc', 'size_asc'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'mtime_desc';
}

$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

/** @var array<string, list<array{entity:string,id:int,label:string}>> */
$usageByPath = [];

try {
    $checkCustomers = $pdo->query("SHOW COLUMNS FROM customers LIKE 'logo'");
    $hasCustomerLogo = $checkCustomers && $checkCustomers->rowCount() > 0;
} catch (PDOException $e) {
    $hasCustomerLogo = false;
}

if ($hasCustomerLogo) {
    $custStmt = $pdo->query('SELECT id, name, logo FROM customers WHERE logo IS NOT NULL AND logo != \'\'');
    if ($custStmt) {
        while ($row = $custStmt->fetch(PDO::FETCH_ASSOC)) {
            $path = trim((string)$row['logo']);
            if ($path === '') {
                continue;
            }
            decrypt_customer_row($row);
            $label = trim((string)($row['name'] ?? '')) ?: ('Kunde #' . (int)$row['id']);
            if (!isset($usageByPath[$path])) {
                $usageByPath[$path] = [];
            }
            $usageByPath[$path][] = [
                'entity' => 'customer',
                'id' => (int)$row['id'],
                'label' => $label,
            ];
        }
    }
}

try {
    $checkCompanies = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo'");
    $hasCompanyLogo = $checkCompanies && $checkCompanies->rowCount() > 0;
} catch (PDOException $e) {
    $hasCompanyLogo = false;
}

if ($hasCompanyLogo) {
    $compStmt = $pdo->query('SELECT id, name, logo FROM companies WHERE logo IS NOT NULL AND logo != \'\'');
    if ($compStmt) {
        while ($row = $compStmt->fetch(PDO::FETCH_ASSOC)) {
            $path = trim((string)$row['logo']);
            if ($path === '') {
                continue;
            }
            decrypt_company_row($row);
            $label = trim((string)($row['name'] ?? '')) ?: ('Firma #' . (int)$row['id']);
            if (!isset($usageByPath[$path])) {
                $usageByPath[$path] = [];
            }
            $usageByPath[$path][] = [
                'entity' => 'company',
                'id' => (int)$row['id'],
                'label' => $label,
            ];
        }
    }
}

$pathsInDb = array_keys($usageByPath);

$usageFilter = isset($_GET['usage']) ? trim((string)$_GET['usage']) : 'all';
if (!in_array($usageFilter, ['all', 'in_use', 'unused'], true)) {
    $usageFilter = 'all';
}

$items = [];
$files = @scandir($uploadsDir);
if ($files === false) {
    $files = [];
}

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    $full = $uploadsDir . $file;
    if (!is_file($full)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $imageExtensions, true)) {
        continue;
    }

    if ($kind === 'customer' && stripos($file, 'customer_') !== 0) {
        continue;
    }
    if ($kind === 'company' && stripos($file, 'company_') !== 0) {
        continue;
    }

    $relative = 'uploads/images/' . $file;
    if ($q !== '' && stripos($file, $q) === false) {
        continue;
    }

    $inDb = in_array($relative, $pathsInDb, true);
    if ($usageFilter === 'in_use' && !$inDb) {
        continue;
    }
    if ($usageFilter === 'unused' && $inDb) {
        continue;
    }

    $size = @filesize($full) ?: 0;
    $mtime = @filemtime($full) ?: 0;

    $items[] = [
        'path' => $relative,
        'filename' => $file,
        'ext' => strtoupper($ext === 'jpeg' ? 'JPG' : $ext),
        'size' => $size,
        'mtime' => $mtime,
        'used_in' => $usageByPath[$relative] ?? [],
    ];
}

$compare = null;
switch ($sort) {
    case 'mtime_asc':
        $compare = static function ($a, $b) {
            return $a['mtime'] <=> $b['mtime'];
        };
        break;
    case 'name_asc':
        $compare = static function ($a, $b) {
            return strcasecmp($a['filename'], $b['filename']);
        };
        break;
    case 'name_desc':
        $compare = static function ($a, $b) {
            return strcasecmp($b['filename'], $a['filename']);
        };
        break;
    case 'size_asc':
        $compare = static function ($a, $b) {
            return $a['size'] <=> $b['size'];
        };
        break;
    case 'size_desc':
        $compare = static function ($a, $b) {
            return $b['size'] <=> $a['size'];
        };
        break;
    case 'mtime_desc':
    default:
        $compare = static function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        };
        break;
}

usort($items, $compare);

echo json_encode([
    'success' => true,
    'items' => $items,
    'base_url' => rtrim(BASE_URL, '/'),
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
