<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt und Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['rolle'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}

$defaultCards = [
    ['label' => 'E-Mail', 'value' => 'support@serohub.de', 'href' => 'mailto:support@serohub.de', 'icon_type' => 'fa', 'icon' => 'fas fa-envelope'],
    ['label' => 'Higtech', 'value' => 'Der Technik Onlineshop', 'href' => 'https://higtech.de', 'icon_type' => 'fa', 'icon' => 'fa-solid fa-bag-shopping'],
    ['label' => 'Wissensdatenbank', 'value' => 'Hilfe & Anleitungen', 'href' => 'knowledge/', 'icon_type' => 'fa', 'icon' => 'fas fa-book']
];

$defaultFooterLinks = [
    ['label' => 'Datenschutz', 'url' => 'https://serohub.de/datenschutz'],
    ['label' => 'Impressum', 'url' => 'https://serohub.de/impressum']
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $cards = $defaultCards;
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_cards' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
            $decoded = json_decode($row['setting_value'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $cards = $decoded;
                foreach ($cards as &$c) {
                    if (empty($c['icon_type'])) {
                        $c['icon_type'] = 'fa';
                        $c['icon'] = isset($c['icon']) ? $c['icon'] : 'fas fa-link';
                    }
                }
                unset($c);
            }
        }

        $footerLinks = $defaultFooterLinks;
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_footer_links' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
            $decoded = json_decode($row['setting_value'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $footerLinks = $decoded;
            }
        }

        echo json_encode(['success' => true, 'cards' => $cards, 'footer_links' => $footerLinks]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
        exit;
    }

    if (isset($data['cards']) && is_array($data['cards'])) {
        $valid = [];
        foreach ($data['cards'] as $c) {
            $label = isset($c['label']) ? trim((string) $c['label']) : '';
            $value = isset($c['value']) ? trim((string) $c['value']) : '';
            $href = isset($c['href']) ? trim((string) $c['href']) : '#';
            $iconType = isset($c['icon_type']) && in_array($c['icon_type'], ['fa', 'image', 'svg'], true) ? $c['icon_type'] : 'fa';
            $icon = isset($c['icon']) ? (string) $c['icon'] : ($iconType === 'fa' ? 'fas fa-link' : '');
            if ($label !== '' || $value !== '' || $href !== '#') {
                $valid[] = ['label' => $label, 'value' => $value, 'href' => $href, 'icon_type' => $iconType, 'icon' => $icon];
            }
        }
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('login_cards', :val)
            ON DUPLICATE KEY UPDATE setting_value = :val_update, geaendert_datum = NOW()
        ");
        $json = json_encode($valid);
        $stmt->execute([':val' => $json, ':val_update' => $json]);
    }

    if (isset($data['footer_links']) && is_array($data['footer_links'])) {
        $validLinks = [];
        foreach ($data['footer_links'] as $l) {
            $lbl = isset($l['label']) ? trim((string) $l['label']) : '';
            $url = isset($l['url']) ? trim((string) $l['url']) : '';
            if ($lbl !== '' || $url !== '') {
                $validLinks[] = ['label' => $lbl, 'url' => $url];
            }
        }
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('login_footer_links', :val)
            ON DUPLICATE KEY UPDATE setting_value = :val_update, geaendert_datum = NOW()
        ");
        $json = json_encode($validLinks);
        $stmt->execute([':val' => $json, ':val_update' => $json]);
    }

    echo json_encode(['success' => true, 'message' => 'Gespeichert']);
} catch (PDOException $e) {
    error_log("Login-Cards API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
