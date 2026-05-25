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

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $userStmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :user_id LIMIT 1");
    $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }

    if (($user['rolle'] ?? '') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

function removeFromPinnedSettings(PDO $pdo, int $ticketId): void
{
    $stmt = $pdo->prepare("
        SELECT id, setting_value
        FROM user_settings
        WHERE setting_key = 'service_pinned_tickets'
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE user_settings SET setting_value = :setting_value WHERE id = :id");
    foreach ($rows as $row) {
        $current = json_decode((string)($row['setting_value'] ?? ''), true);
        if (!is_array($current)) {
            continue;
        }
        $updated = array_values(array_filter(array_map('intval', $current), static fn($v) => $v > 0 && $v !== $ticketId));
        if ($updated === $current) {
            continue;
        }
        $updateStmt->bindValue(':setting_value', json_encode($updated), PDO::PARAM_STR);
        $updateStmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $updateStmt->execute();
    }
}

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT
                t.id,
                t.ticket_nummer,
                t.titel,
                t.status,
                t.prioritaet,
                t.erstellt_datum,
                t.geaendert_datum,
                t.abgeschlossen_datum,
                c.name AS company_name,
                CONCAT(COALESCE(u.vorname, ''), ' ', COALESCE(u.nachname, '')) AS creator_name
            FROM tickets t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN users u ON t.erstellt_von = u.id
            WHERE t.titel LIKE '[Gelöscht] %'
            ORDER BY COALESCE(t.geaendert_datum, t.erstellt_datum) DESC
            LIMIT 1000
        ");
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'tickets' => $tickets]);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '[]', true);
    $ticketId = isset($data['ticket_id']) ? (int)$data['ticket_id'] : 0;
    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ticket_id fehlt']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id, ticket_nummer, titel FROM tickets WHERE id = :id LIMIT 1");
    $checkStmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
    $checkStmt->execute();
    $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket nicht gefunden']);
        exit;
    }

    $title = (string)($ticket['titel'] ?? '');
    if (strpos($title, '[Gelöscht] ') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ticket ist nicht soft-gelöscht']);
        exit;
    }

    if ($method === 'POST') {
        $restoredTitle = preg_replace('/^\[Gelöscht\]\s+/', '', $title, 1);
        if ($restoredTitle === null || $restoredTitle === '') {
            $restoredTitle = trim($title);
        }
        $restoreStmt = $pdo->prepare("
            UPDATE tickets
            SET titel = :titel,
                status = 'Neu',
                abgeschlossen_datum = NULL,
                geaendert_datum = NOW()
            WHERE id = :id
        ");
        $restoreStmt->bindValue(':titel', $restoredTitle, PDO::PARAM_STR);
        $restoreStmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
        $restoreStmt->execute();
        service_log($pdo, $userId, 'ticket', $ticketId, 'updated', null, null, null, 'Ticket aus Papierkorb wiederhergestellt');
        echo json_encode(['success' => true, 'message' => 'Ticket wiederhergestellt']);
        exit;
    }

    if ($method === 'DELETE') {
        $commentAttachmentTableExists = false;
        try {
            $tbl = $pdo->query("SHOW TABLES LIKE 'comment_attachments'");
            $commentAttachmentTableExists = (bool)($tbl && $tbl->rowCount() > 0);
        } catch (PDOException $e) {
            $commentAttachmentTableExists = false;
        }

        $pdo->beginTransaction();
        try {
            $webappRoot = dirname(__DIR__, 2);
            $filePaths = [];

            $ticketAttachmentStmt = $pdo->prepare("SELECT dateipfad FROM ticket_attachments WHERE ticket_id = :ticket_id");
            $ticketAttachmentStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $ticketAttachmentStmt->execute();
            foreach ($ticketAttachmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $path = trim((string)($row['dateipfad'] ?? ''));
                if ($path !== '') {
                    $filePaths[] = $path;
                }
            }

            $commentIdsStmt = $pdo->prepare("SELECT id FROM ticket_comments WHERE ticket_id = :ticket_id");
            $commentIdsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $commentIdsStmt->execute();
            $commentIds = array_map('intval', array_column($commentIdsStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

            if ($commentAttachmentTableExists && !empty($commentIds)) {
                $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
                $commentFilesStmt = $pdo->prepare("SELECT dateipfad FROM comment_attachments WHERE comment_id IN ($placeholders)");
                $commentFilesStmt->execute($commentIds);
                foreach ($commentFilesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $path = trim((string)($row['dateipfad'] ?? ''));
                    if ($path !== '') {
                        $filePaths[] = $path;
                    }
                }
            }

            removeFromPinnedSettings($pdo, $ticketId);

            $clearOrdersStmt = $pdo->prepare("UPDATE orders SET ticket_id = NULL WHERE ticket_id = :ticket_id");
            $clearOrdersStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $clearOrdersStmt->execute();

            $clearTodosStmt = $pdo->prepare("UPDATE todos SET ticket_id = NULL WHERE ticket_id = :ticket_id");
            $clearTodosStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $clearTodosStmt->execute();

            $deleteObserverStmt = $pdo->prepare("DELETE FROM ticket_observers WHERE ticket_id = :ticket_id");
            $deleteObserverStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteObserverStmt->execute();

            $deleteTicketAttachmentsStmt = $pdo->prepare("DELETE FROM ticket_attachments WHERE ticket_id = :ticket_id");
            $deleteTicketAttachmentsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteTicketAttachmentsStmt->execute();

            if (!empty($commentIds)) {
                $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
                $deleteReadsStmt = $pdo->prepare("DELETE FROM ticket_comment_reads WHERE comment_id IN ($placeholders)");
                $deleteReadsStmt->execute($commentIds);

                if ($commentAttachmentTableExists) {
                    $deleteCommentAttachmentsStmt = $pdo->prepare("DELETE FROM comment_attachments WHERE comment_id IN ($placeholders)");
                    $deleteCommentAttachmentsStmt->execute($commentIds);
                }
            }

            $deleteCommentsStmt = $pdo->prepare("DELETE FROM ticket_comments WHERE ticket_id = :ticket_id");
            $deleteCommentsStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteCommentsStmt->execute();

            $deleteTicketStmt = $pdo->prepare("DELETE FROM tickets WHERE id = :ticket_id");
            $deleteTicketStmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $deleteTicketStmt->execute();

            $pdo->commit();

            foreach (array_unique($filePaths) as $relativePath) {
                if (strpos($relativePath, '..') !== false) {
                    continue;
                }
                $fullPath = $webappRoot . '/' . ltrim($relativePath, '/');
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            service_log($pdo, $userId, 'ticket', $ticketId, 'deleted', null, null, null, 'Ticket endgültig gelöscht');
            echo json_encode(['success' => true, 'message' => 'Ticket endgültig gelöscht']);
            exit;
        } catch (Throwable $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Deleted tickets API Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Deleted tickets API Fehler: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unerwarteter Fehler']);
}
?>
