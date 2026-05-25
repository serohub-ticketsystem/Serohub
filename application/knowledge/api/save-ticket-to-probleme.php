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

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['rolle'] !== 'Techniker' && $user['rolle'] !== 'Admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Zugriff nur für Techniker und Admins.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ticketId = isset($input['ticket_id']) ? (int) $input['ticket_id'] : 0;
if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ticket_id fehlt oder ungültig']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, titel, ticket_nummer, company_id FROM tickets WHERE id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
        exit;
    }

    $companyId = isset($ticket['company_id']) ? (int) $ticket['company_id'] : null;
    if (!$companyId) {
        echo json_encode(['success' => false, 'error' => 'Der Ticket ist keiner Firma zugeordnet. Bitte zuerst eine Firma zuweisen.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM kb_pages WHERE company_id = ? AND is_system_folder = 1 AND system_type = 'problems' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$companyId]);
    $problemeFolder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$problemeFolder) {
        echo json_encode(['success' => false, 'error' => 'Ticket-Archiv-Ordner für diese Firma wurde nicht gefunden.']);
        exit;
    }

    $parentId = $problemeFolder['id'];
    $title = trim($ticket['titel'] ?? '');
    if ($title === '') {
        $title = 'Ticket ' . ($ticket['ticket_nummer'] ?? '#' . $ticketId);
    }

    function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', strtolower($text));
        $text = trim($text, '-');
        return $text ?: 'seite';
    }

    $baseSlug = 'ticket-' . $ticketId . '-' . slugify($title);
    $slug = $baseSlug;
    $n = 0;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM kb_pages WHERE slug = ? LIMIT 1");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $n++;
        $slug = $baseSlug . ($n > 1 ? '-' . $n : '-1');
    }

    $ticketLabel = $ticket['ticket_nummer'] ?? ('#' . $ticketId);
    $baseUrl = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') . '/' : '/';
    $ticketUrl = $baseUrl . 'tickets/view.php?id=' . $ticketId;
    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Verknüpfter Ticket: '],
                    ['type' => 'text', 'text' => $ticketLabel, 'marks' => [['type' => 'link', 'attrs' => ['href' => $ticketUrl]]]]
                ]
            ]
        ]
    ];

    $pageId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x4000) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 AS next_pos FROM kb_pages WHERE parent_id <=> ?");
    $stmt->execute([$parentId]);
    $nextOrder = (int) $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];

    $stmt = $pdo->prepare("INSERT INTO kb_pages (id, title, slug, content, content_type, parent_id, order_index, author_id, company_id) VALUES (?, ?, ?, ?, 'json', ?, ?, ?, ?)");
    $stmt->execute([$pageId, $title, $slug, json_encode($content), $parentId, $nextOrder, $userId, $companyId]);

    $kbUrl = (defined('BASE_URL') ? BASE_URL : '/') . 'knowledge/?id=' . urlencode($pageId);
    echo json_encode([
        'success' => true,
        'message' => 'Ticket in Wissensdatenbank unter „Ticket-Archiv“ gespeichert.',
        'page_id' => $pageId,
        'kb_url' => $kbUrl
    ]);
} catch (PDOException $e) {
    error_log("save-ticket-to-probleme: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern in der Wissensdatenbank.']);
}
