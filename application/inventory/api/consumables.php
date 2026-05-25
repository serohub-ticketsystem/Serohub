<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';
require_once dirname(__DIR__, 2) . '/assets/inventory_permissions.php';
require_once __DIR__ . '/consumables_pending_stockin.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

inventory_permissions_ensure_columns($pdo);
$invUser = inventory_permissions_load_user($pdo, $userId);
if (!$invUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
    exit;
}
$userRole = $invUser['rolle'];
$userCompanyId = $invUser['company_id'] ?? null;

// Prüfe ob es sich um einen öffentlichen Zugriff handelt (z.B. für Bestellungen)
$isPublicAccess = ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'by_device');

// Lager: Admin/Techniker oder Firma mit freigeschaltetem Lagerzugriff (by_device ausgenommen)
if (!$isPublicAccess && !inventory_user_can_view_from_row($invUser)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für das Lager.']);
    exit;
}

$canEdit = inventory_user_can_full_edit($userRole);
$canAdjustStock = inventory_user_can_adjust_from_row($invUser);
$isCompanyInventoryUser = $canEdit ? false : inventory_user_can_view_from_row($invUser);

/** Prüft einmal pro Request, ob consumables.deleted_at existiert (Migration 109). */
function consumablesHasDeletedAt($pdo) {
    if (isset($GLOBALS['_consumables_has_deleted_at'])) {
        return (bool)$GLOBALS['_consumables_has_deleted_at'];
    }
    try {
        $pdo->query("SELECT deleted_at FROM consumables LIMIT 0");
        $GLOBALS['_consumables_has_deleted_at'] = true;
        return true;
    } catch (PDOException $e) {
        $GLOBALS['_consumables_has_deleted_at'] = false;
        return false;
    }
}

/** Stellt sicher, dass die Spalte deleted_at existiert (legt sie bei Bedarf an). */
function ensureConsumablesDeletedAtColumn($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'deleted_at'");
        if ($stmt && $stmt->rowCount() > 0) {
            $GLOBALS['_consumables_has_deleted_at'] = true;
            return;
        }
        $pdo->exec("ALTER TABLE consumables ADD COLUMN deleted_at datetime DEFAULT NULL COMMENT 'Soft-Delete-Zeitpunkt; NULL = aktiv' AFTER geaendert_datum");
        $GLOBALS['_consumables_has_deleted_at'] = true;
    } catch (PDOException $e) {
        error_log('Consumables deleted_at Spalte: ' . $e->getMessage());
    }
}

/** Prüft einmal pro Request, ob consumables.scan_auto_review existiert. */
function consumablesHasScanAutoReview($pdo) {
    if (isset($GLOBALS['_consumables_has_scan_auto_review'])) {
        return (bool)$GLOBALS['_consumables_has_scan_auto_review'];
    }
    try {
        $pdo->query("SELECT scan_auto_review FROM consumables LIMIT 0");
        $GLOBALS['_consumables_has_scan_auto_review'] = true;
        return true;
    } catch (PDOException $e) {
        $GLOBALS['_consumables_has_scan_auto_review'] = false;
        return false;
    }
}

/** Legt scan_auto_review an, falls die Spalte fehlt (Migration 112). */
function ensureConsumablesScanAutoReviewColumn($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'scan_auto_review'");
        if ($stmt && $stmt->rowCount() > 0) {
            $GLOBALS['_consumables_has_scan_auto_review'] = true;
            return;
        }
        $pdo->exec("ALTER TABLE consumables ADD COLUMN scan_auto_review tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = per Barcode automatisch angelegt, Daten prüfen' AFTER company_id");
        $GLOBALS['_consumables_has_scan_auto_review'] = true;
    } catch (PDOException $e) {
        error_log('Consumables scan_auto_review Spalte: ' . $e->getMessage());
    }
}

/** Gleiche Logik wie in der Listen-Abfrage: offene Bestellung zu diesem Verbrauchsmaterial. */
function inventoryConsumableHasOpenOrder(PDO $pdo, int $consumableId, $bezeichnung, $artikelnummer): bool {
    $bez = trim((string)($bezeichnung ?? ''));
    $art = ($artikelnummer !== null && $artikelnummer !== '') ? trim((string)$artikelnummer) : '';
    $stmt = $pdo->prepare("SELECT EXISTS(
        SELECT 1 FROM orders o
        WHERE o.status NOT IN ('Im Lager', 'Angekommen')
          AND (
            o.notizen LIKE CONCAT('%[inventar_consumable_id=', ?, ']%')
            OR o.beschreibung LIKE CONCAT('%[inventar_consumable_id=', ?, ']%')
            OR (
              (o.notizen IS NULL OR o.notizen NOT LIKE '%[inventar_consumable_id=%')
              AND (o.beschreibung IS NULL OR o.beschreibung NOT LIKE '%[inventar_consumable_id=%')
              AND (
                o.beschreibung = CONCAT('Mindestbestand: ', COALESCE(NULLIF(TRIM(?), ''), 'Verbrauchsmaterial'))
                OR (
                  ? <> ''
                  AND o.beschreibung = CONCAT(
                    'Mindestbestand: ',
                    COALESCE(NULLIF(TRIM(?), ''), 'Verbrauchsmaterial'),
                    ' (Art. ',
                    ?,
                    ')'
                  )
                )
              )
            )
          )
    )");
    $stmt->execute([$consumableId, $consumableId, $bez, $art, $bez, $art]);
    return (bool)$stmt->fetchColumn();
}

function buildLagerortText($shelfName, $spalte, $fach) {
    $parts = [];
    if (!empty($shelfName)) {
        $parts[] = 'Regal ' . $shelfName;
    }
    if (isset($spalte) && $spalte !== '' && $spalte !== null) {
        $parts[] = 'Spalte ' . (int)$spalte;
    }
    if (isset($fach) && $fach !== '' && $fach !== null) {
        $parts[] = 'Fach ' . (int)$fach;
    }
    return $parts ? implode(', ', $parts) : null;
}

/**
 * Nach Auslagern: die neueste Bestellung zu diesem Artikel mit Status „Im Lager“ auf „Angekommen“ setzen
 * (z. B. Ticket-Bestellung, bei der der Artikel zunächst im Lager verbucht war).
 *
 * @return int|null ID der aktualisierten Bestellung oder null
 */
function inventory_complete_im_lager_order_after_auslagern(PDO $pdo, int $consumableId, int $userId): ?int {
    if ($consumableId <= 0 || $userId <= 0) {
        return null;
    }
    $markerLike = '%[inventar_consumable_id=' . $consumableId . ']%';
    try {
        $stmt = $pdo->prepare(
            "SELECT id, ticket_id, beschreibung, bestellnummer, company_id, notizen, status
             FROM orders
             WHERE status = 'Im Lager'
               AND (notizen LIKE ? OR beschreibung LIKE ?)
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$markerLike, $markerLike]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('inventory_complete_im_lager_order_after_auslagern: ' . $e->getMessage());
        return null;
    }
    if (!$order || (int)$order['id'] <= 0) {
        return null;
    }
    $orderId = (int)$order['id'];
    try {
        $upd = $pdo->prepare("UPDATE orders SET status = 'Angekommen', geaendert_datum = NOW() WHERE id = ? AND status = 'Im Lager'");
        $upd->execute([$orderId]);
        if ($upd->rowCount() === 0) {
            return null;
        }
        try {
            $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von) VALUES (?, 'Angekommen', ?)");
            $histStmt->execute([$orderId, $userId]);
        } catch (PDOException $e) {
            error_log('inventory_complete_im_lager_order_after_auslagern history: ' . $e->getMessage());
        }
        $notizen = $order['notizen'] ?? null;
        $beschreibung = $order['beschreibung'] ?? null;
        consumableApplyPendingStockinFlagFromOrderNotizen($pdo, $notizen ?? '', true, $beschreibung);
        $betreffLabel = trim((string)($beschreibung ?? ''));
        $betreffLabel = preg_replace('/\s*\[inventar_consumable_id=\d+\]\s*/', '', $betreffLabel);
        $betreffLabel = $betreffLabel !== '' ? mb_substr($betreffLabel, 0, 200) : '';
        $ticketId = isset($order['ticket_id']) ? (int)$order['ticket_id'] : 0;
        if ($ticketId > 0) {
            try {
                $ticketUpdateStmt = $pdo->prepare("UPDATE tickets SET status = 'Neu', geaendert_datum = NOW() WHERE id = ?");
                $ticketUpdateStmt->execute([$ticketId]);
            } catch (PDOException $e) {
                error_log('inventory_complete_im_lager_order_after_auslagern ticket: ' . $e->getMessage());
            }
            $kommentar = 'Die Bestellung' . ($betreffLabel !== '' ? ' "' . $betreffLabel . '"' : '') . ' ist angekommen.';
            try {
                $commentStmt = $pdo->prepare(
                    "INSERT INTO ticket_comments (ticket_id, user_id, kommentar, nachrichtentyp, ist_intern, erstellt_datum)
                     VALUES (?, ?, ?, 'nachricht', 0, NOW())"
                );
                $commentStmt->execute([$ticketId, $userId, $kommentar]);
                $pdo->prepare("UPDATE tickets SET geaendert_datum = NOW() WHERE id = ?")->execute([$ticketId]);
            } catch (PDOException $e) {
                error_log('inventory_complete_im_lager_order_after_auslagern ticket comment: ' . $e->getMessage());
            }
        }
        if (is_file(dirname(__DIR__, 2) . '/assets/notifications.php')) {
            require_once dirname(__DIR__, 2) . '/assets/notifications.php';
            try {
                $orderBetreff = $betreffLabel !== '' ? $betreffLabel : trim((string)($order['bestellnummer'] ?? ''));
                if ($orderBetreff === '') {
                    $orderBetreff = (string)($order['bestellnummer'] ?? 'Bestellung');
                }
                createNotificationsForAction(
                    $userId,
                    $order['company_id'] ?? null,
                    'order_status_changed',
                    'Bestellung Status geändert: ' . $orderBetreff,
                    'Der Status der Bestellung "' . $orderBetreff . '" wurde von "' . ($order['status'] ?? 'Im Lager') . '" auf "Angekommen" geändert (Auslagerung).',
                    'hoch',
                    'orders/detail.php?id=' . $orderId,
                    'order',
                    $orderId
                );
            } catch (Throwable $e) {
                error_log('inventory_complete_im_lager_order_after_auslagern notify: ' . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log('inventory_complete_im_lager_order_after_auslagern update: ' . $e->getMessage());
        return null;
    }
    return $orderId;
}

function parseBoolFlag($value) {
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value === 1;
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }
    return !empty($value);
}

/**
 * Liefert die ID eines aktiven Artikels mit derselben EAN, sonst null.
 *
 * @param int|null $excludeConsumableId Bei Update: eigene ID ausschließen
 */
function consumablesFindActiveIdByEan(PDO $pdo, ?string $ean, $excludeConsumableId = null): ?int {
    if ($ean === null) {
        return null;
    }
    $ean = trim($ean);
    if ($ean === '') {
        return null;
    }
    $delWhere = consumablesHasDeletedAt($pdo) ? ' AND (deleted_at IS NULL)' : '';
    if ($excludeConsumableId !== null && (int)$excludeConsumableId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM consumables WHERE ean = ? AND id <> ?" . $delWhere . " LIMIT 1");
        $stmt->execute([$ean, (int)$excludeConsumableId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM consumables WHERE ean = ?" . $delWhere . " LIMIT 1");
        $stmt->execute([$ean]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['id'] : null;
}

/**
 * Liefert die ID eines aktiven Artikels mit derselben Artikelnummer, sonst null.
 *
 * @param int|null $excludeConsumableId Bei Update: eigene ID ausschließen
 */
function consumablesFindActiveIdByArtikelnummer(PDO $pdo, ?string $artikelnummer, $excludeConsumableId = null): ?int {
    if ($artikelnummer === null) {
        return null;
    }
    $artikelnummer = trim($artikelnummer);
    if ($artikelnummer === '') {
        return null;
    }
    $delWhere = consumablesHasDeletedAt($pdo) ? ' AND (deleted_at IS NULL)' : '';
    if ($excludeConsumableId !== null && (int)$excludeConsumableId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM consumables WHERE artikelnummer = ? AND id <> ?" . $delWhere . " LIMIT 1");
        $stmt->execute([$artikelnummer, (int)$excludeConsumableId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM consumables WHERE artikelnummer = ?" . $delWhere . " LIMIT 1");
        $stmt->execute([$artikelnummer]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['id'] : null;
}

function consumablesHasCompanyLinkTable(PDO $pdo): bool {
    if (isset($GLOBALS['_consumables_has_company_link'])) {
        return (bool)$GLOBALS['_consumables_has_company_link'];
    }
    try {
        $pdo->query('SELECT 1 FROM consumable_company_link LIMIT 0');
        $GLOBALS['_consumables_has_company_link'] = true;
        return true;
    } catch (PDOException $e) {
        $GLOBALS['_consumables_has_company_link'] = false;
        return false;
    }
}

function ensureConsumableCompanyLinkTable(PDO $pdo): void {
    if (consumablesHasCompanyLinkTable($pdo)) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS consumable_company_link (
            consumable_id int UNSIGNED NOT NULL,
            company_id int UNSIGNED NOT NULL,
            PRIMARY KEY (consumable_id, company_id),
            KEY idx_ccl_company (company_id),
            CONSTRAINT fk_ccl_consumable FOREIGN KEY (consumable_id) REFERENCES consumables (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_ccl_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung Verbrauchsmaterial zu Firmen'");
        $pdo->exec('INSERT IGNORE INTO consumable_company_link (consumable_id, company_id) SELECT id, company_id FROM consumables WHERE company_id IS NOT NULL');
        $GLOBALS['_consumables_has_company_link'] = true;
    } catch (PDOException $e) {
        error_log('consumable_company_link: ' . $e->getMessage());
    }
}

/** @return int[] eindeutige Firmen-IDs in Reihenfolge der Auswahl */
function consumablesParseCompanyIdsFromInput(array $input): array {
    $out = [];
    $seen = [];
    if (!empty($input['company_ids']) && is_array($input['company_ids'])) {
        foreach ($input['company_ids'] as $cid) {
            $cid = (int)$cid;
            if ($cid > 0 && !isset($seen[$cid])) {
                $seen[$cid] = true;
                $out[] = $cid;
            }
        }
    }
    if (empty($out) && isset($input['company_id']) && (int)$input['company_id'] > 0) {
        $cid = (int)$input['company_id'];
        if (!isset($seen[$cid])) {
            $out[] = $cid;
        }
    }
    return $out;
}

function consumableReplaceCompanyLinks(PDO $pdo, int $consumableId, array $companyIds): void {
    if (!consumablesHasCompanyLinkTable($pdo) || $consumableId <= 0) {
        return;
    }
    $pdo->prepare('DELETE FROM consumable_company_link WHERE consumable_id = ?')->execute([$consumableId]);
    if ($companyIds === []) {
        return;
    }
    $ins = $pdo->prepare('INSERT IGNORE INTO consumable_company_link (consumable_id, company_id) VALUES (?, ?)');
    foreach ($companyIds as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            $ins->execute([$consumableId, $cid]);
        }
    }
}

/**
 * Liefert genau eine zugeordnete Firma eines Verbrauchsmaterials, sonst null.
 * Berücksichtigt sowohl Mehrfachzuordnung (Link-Tabelle) als auch Legacy company_id.
 */
function consumableResolveSingleCompanyId(PDO $pdo, int $consumableId): ?int {
    if ($consumableId <= 0) {
        return null;
    }

    $companyIds = [];
    $seen = [];

    if (consumablesHasCompanyLinkTable($pdo)) {
        try {
            $stmt = $pdo->prepare('SELECT company_id FROM consumable_company_link WHERE consumable_id = ?');
            $stmt->execute([$consumableId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cidRaw) {
                $cid = (int)$cidRaw;
                if ($cid > 0 && !isset($seen[$cid])) {
                    $seen[$cid] = true;
                    $companyIds[] = $cid;
                }
            }
        } catch (PDOException $e) {
            error_log('consumableResolveSingleCompanyId link lookup: ' . $e->getMessage());
        }
    }

    if ($companyIds === []) {
        try {
            $stmt = $pdo->prepare('SELECT company_id FROM consumables WHERE id = ? LIMIT 1');
            $stmt->execute([$consumableId]);
            $legacyCompanyId = (int)$stmt->fetchColumn();
            if ($legacyCompanyId > 0) {
                $companyIds[] = $legacyCompanyId;
            }
        } catch (PDOException $e) {
            error_log('consumableResolveSingleCompanyId legacy lookup: ' . $e->getMessage());
        }
    }

    return count($companyIds) === 1 ? (int)$companyIds[0] : null;
}

try {
    ensureConsumablesScanAutoReviewColumn($pdo);
    ensureConsumablesPendingStockinAfterDeliveryColumn($pdo);
    ensureConsumableCompanyLinkTable($pdo);
    switch ($method) {
        case 'GET':
            // Alle Kategorien (für Mehrfachauswahl)
            if (isset($_GET['action']) && $_GET['action'] === 'get_categories') {
                $stmt = $pdo->query("SELECT id, name FROM consumable_categories ORDER BY name");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'categories' => $categories]);
                exit;
            }

            // Gelöschte Verbrauchsmaterialien (nur Admin, für Wiederherstellung im Admin-Bereich)
            if (isset($_GET['action']) && $_GET['action'] === 'list_deleted') {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Admins können gelöschte Artikel einsehen']);
                    exit;
                }
                ensureConsumablesDeletedAtColumn($pdo);
                if (!consumablesHasDeletedAt($pdo)) {
                    echo json_encode(['success' => true, 'consumables' => []]);
                    exit;
                }
                $stmt = $pdo->query("
                    SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.deleted_at
                    FROM consumables c
                    WHERE c.deleted_at IS NOT NULL
                    ORDER BY c.deleted_at DESC
                    LIMIT 1000
                ");
                $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'consumables' => $consumables]);
                exit;
            }

            // Verbrauchsmaterialien für ein Gerät (Hersteller+Modell) – für Geräte-Detail-Reiter
            // Öffentlicher Zugriff: Alle können auswählen, aber lagerbestand wird nicht angezeigt
            if (isset($_GET['action']) && $_GET['action'] === 'by_device' && isset($_GET['device_id'])) {
                $deviceId = (int)$_GET['device_id'];
                $stmt = $pdo->prepare("SELECT hersteller, modell FROM devices WHERE id = ? LIMIT 1");
                $stmt->execute([$deviceId]);
                $device = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$device || (empty($device['hersteller']) && empty($device['modell']))) {
                    echo json_encode(['success' => true, 'consumables' => []]);
                    exit;
                }
                $hersteller = $device['hersteller'] ?? '';
                $modell = $device['modell'] ?? '';
                $delFilter = consumablesHasDeletedAt($pdo) ? ' AND (c.deleted_at IS NULL)' : '';
                $stmt = $pdo->prepare("
                    SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.shop_veroeffentlicht, c.auto_nachbestellen, c.beschreibung, c.mindestbestand,
                           c.lagerbestand, cdm.hersteller, cdm.modell
                    FROM consumables c
                    INNER JOIN consumable_device_models cdm ON cdm.consumable_id = c.id
                    WHERE cdm.hersteller = ? AND cdm.modell = ?" . $delFilter . "
                    ORDER BY c.bezeichnung
                ");
                $stmt->execute([$hersteller, $modell]);
                $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'consumables' => $consumables]);
                exit;
            }

            // Einzelnes Verbrauchsmaterial inkl. zugeordneter Gerätemodelle und Lagerort
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $delFilter = consumablesHasDeletedAt($pdo) ? ' AND (c.deleted_at IS NULL)' : '';
                $scanColDet = consumablesHasScanAutoReview($pdo) ? ', c.scan_auto_review' : '';
                $pendingColDet = consumablesHasPendingStockinAfterDelivery($pdo) ? ', c.pending_stockin_after_delivery' : '';
                $stmt = $pdo->prepare("SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.shop_veroeffentlicht, c.auto_nachbestellen, c.beschreibung, c.mindestbestand, c.lagerbestand, c.shelf_id, c.spalte, c.fach, c.company_id" . $scanColDet . $pendingColDet . ", c.erstellt_datum, c.geaendert_datum, s.name AS shelf_name, COALESCE(s.spalten_anzahl, 5) AS shelf_spalten_anzahl, COALESCE(s.faecher_anzahl, 6) AS shelf_faecher_anzahl FROM consumables c LEFT JOIN shelves s ON s.id = c.shelf_id WHERE c.id = ?" . $delFilter);
                $stmt->execute([$id]);
                $consumable = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$consumable) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden']);
                    exit;
                }
                if (isset($consumable['shelf_name'])) {
                    $consumable['lagerort_text'] = buildLagerortText($consumable['shelf_name'], $consumable['spalte'], $consumable['fach']);
                }
                $stmt = $pdo->prepare("SELECT id, hersteller, modell FROM consumable_device_models WHERE consumable_id = ? ORDER BY hersteller, modell");
                $stmt->execute([$id]);
                $consumable['device_models'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("SELECT category_id FROM consumable_category_link WHERE consumable_id = ?");
                $stmt->execute([$id]);
                $consumable['category_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                $stmt = $pdo->prepare("SELECT cc.id, cc.name FROM consumable_category_link ccl JOIN consumable_categories cc ON cc.id = ccl.category_id WHERE ccl.consumable_id = ? ORDER BY cc.name");
                $stmt->execute([$id]);
                $consumable['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $consumable['company_ids'] = [];
                if (consumablesHasCompanyLinkTable($pdo)) {
                    $stmt = $pdo->prepare('SELECT company_id FROM consumable_company_link WHERE consumable_id = ? ORDER BY company_id');
                    $stmt->execute([$id]);
                    $consumable['company_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                }
                if ($consumable['company_ids'] === [] && !empty($consumable['company_id'])) {
                    $consumable['company_ids'] = [(int)$consumable['company_id']];
                }
                $consumable['has_open_order'] = inventoryConsumableHasOpenOrder(
                    $pdo,
                    (int)$consumable['id'],
                    $consumable['bezeichnung'] ?? '',
                    $consumable['artikelnummer'] ?? null
                ) ? 1 : 0;
                if (!empty($isCompanyInventoryUser) && $userCompanyId && !inventory_consumable_visible_for_company($pdo, $id, (int)$userCompanyId)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden']);
                    exit;
                }
                echo json_encode(['success' => true, 'consumable' => $consumable]);
                exit;
            }

            // Liste aller Verbrauchsmaterialien inkl. Gerätemodelle und Lagerort (ohne soft-gelöschte, falls Spalte vorhanden)
            $delWhere = consumablesHasDeletedAt($pdo) ? ' WHERE c.deleted_at IS NULL' : '';
            $navSelectedCompanyId = null;
            if (!empty($isCompanyInventoryUser) && $userCompanyId) {
                $navSelectedCompanyId = (int)$userCompanyId;
            } elseif (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null) {
                $sc = (int)$_SESSION['selected_company_id'];
                if ($sc > 0) {
                    $navSelectedCompanyId = $sc;
                }
            }
            $companyWhere = '';
            $listParams = [];
            if ($navSelectedCompanyId !== null) {
                if (consumablesHasCompanyLinkTable($pdo)) {
                    $companyWhere = $delWhere !== '' ? ' AND (c.company_id = ? OR EXISTS (SELECT 1 FROM consumable_company_link cclf WHERE cclf.consumable_id = c.id AND cclf.company_id = ?))' : ' WHERE (c.company_id = ? OR EXISTS (SELECT 1 FROM consumable_company_link cclf WHERE cclf.consumable_id = c.id AND cclf.company_id = ?))';
                    $listParams = [$navSelectedCompanyId, $navSelectedCompanyId];
                } else {
                    $companyWhere = $delWhere !== '' ? ' AND c.company_id = ?' : ' WHERE c.company_id = ?';
                    $listParams = [$navSelectedCompanyId];
                }
            }
            $scanCol = consumablesHasScanAutoReview($pdo) ? ', c.scan_auto_review' : '';
            $pendingCol = consumablesHasPendingStockinAfterDelivery($pdo) ? ', c.pending_stockin_after_delivery' : '';
            $companyIdsCsvCol = consumablesHasCompanyLinkTable($pdo)
                ? ", (SELECT GROUP_CONCAT(ccl.company_id ORDER BY ccl.company_id SEPARATOR ',') FROM consumable_company_link ccl WHERE ccl.consumable_id = c.id) AS company_ids_csv"
                : ', NULL AS company_ids_csv';
            $listSql = "
                SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.shop_veroeffentlicht, c.auto_nachbestellen, c.beschreibung, c.mindestbestand, c.lagerbestand, c.shelf_id, c.spalte, c.fach, c.company_id" . $scanCol . $pendingCol . ", c.erstellt_datum, c.geaendert_datum, s.name AS shelf_name" . $companyIdsCsvCol . ",
                       EXISTS(
                           SELECT 1
                           FROM orders o
                           WHERE o.status NOT IN ('Im Lager', 'Angekommen')
                             AND (
                               o.notizen LIKE CONCAT('%[inventar_consumable_id=', c.id, ']%')
                               OR o.beschreibung LIKE CONCAT('%[inventar_consumable_id=', c.id, ']%')
                               OR (
                                   (o.notizen IS NULL OR o.notizen NOT LIKE '%[inventar_consumable_id=%')
                                   AND (o.beschreibung IS NULL OR o.beschreibung NOT LIKE '%[inventar_consumable_id=%')
                                   AND (
                                       o.beschreibung = CONCAT('Mindestbestand: ', COALESCE(NULLIF(TRIM(c.bezeichnung), ''), 'Verbrauchsmaterial'))
                                       OR (
                                           c.artikelnummer IS NOT NULL AND TRIM(c.artikelnummer) <> ''
                                           AND o.beschreibung = CONCAT(
                                               'Mindestbestand: ',
                                               COALESCE(NULLIF(TRIM(c.bezeichnung), ''), 'Verbrauchsmaterial'),
                                               ' (Art. ',
                                               TRIM(c.artikelnummer),
                                               ')'
                                           )
                                       )
                                   )
                               )
                             )
                       ) AS has_open_order
                FROM consumables c
                LEFT JOIN shelves s ON s.id = c.shelf_id
                " . $delWhere . $companyWhere . "
                ORDER BY c.bezeichnung
            ";
            if ($listParams !== []) {
                $stmt = $pdo->prepare($listSql);
                $stmt->execute($listParams);
            } else {
                $stmt = $pdo->query($listSql);
            }
            $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($consumables as &$c) {
                $companyIdList = [];
                if (!empty($c['company_ids_csv'])) {
                    foreach (explode(',', (string)$c['company_ids_csv']) as $part) {
                        $pid = (int)trim($part);
                        if ($pid > 0) {
                            $companyIdList[] = $pid;
                        }
                    }
                }
                unset($c['company_ids_csv']);
                if ($companyIdList === [] && isset($c['company_id']) && (int)$c['company_id'] > 0) {
                    $companyIdList[] = (int)$c['company_id'];
                }
                $c['company_ids'] = $companyIdList;
                $c['lagerort_text'] = buildLagerortText($c['shelf_name'] ?? null, $c['spalte'] ?? null, $c['fach'] ?? null);
                $stmt = $pdo->prepare("SELECT id, hersteller, modell FROM consumable_device_models WHERE consumable_id = ? ORDER BY hersteller, modell");
                $stmt->execute([$c['id']]);
                $c['device_models'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("SELECT cc.id, cc.name FROM consumable_category_link ccl JOIN consumable_categories cc ON cc.id = ccl.category_id WHERE ccl.consumable_id = ? ORDER BY cc.name");
                $stmt->execute([$c['id']]);
                $c['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($c);
            echo json_encode([
                'success' => true,
                'consumables' => $consumables,
                'inventory_meta' => [
                    'can_edit' => $canEdit,
                    'can_adjust_stock' => $canAdjustStock,
                ],
            ]);
            exit;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            // Gelöschtes Verbrauchsmaterial wiederherstellen (nur Admin)
            if (isset($input['action']) && $input['action'] === 'restore') {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Admins können Artikel wiederherstellen']);
                    exit;
                }
                ensureConsumablesDeletedAtColumn($pdo);
                if (!consumablesHasDeletedAt($pdo)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Soft-Delete nicht aktiv']);
                    exit;
                }
                $restoreId = isset($input['id']) ? (int)$input['id'] : 0;
                if ($restoreId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE consumables SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
                $stmt->execute([$restoreId]);
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden oder nicht gelöscht']);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Verbrauchsmaterial wiederhergestellt']);
                exit;
            }

            // Soft-gelöschtes Verbrauchsmaterial endgültig löschen (nur Admin)
            if (isset($input['action']) && $input['action'] === 'purge') {
                if ($userRole !== 'Admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Nur Admins können Artikel endgültig löschen']);
                    exit;
                }
                if (!consumablesHasDeletedAt($pdo)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nur soft-gelöschte Artikel können endgültig gelöscht werden']);
                    exit;
                }
                $purgeId = isset($input['id']) ? (int)$input['id'] : 0;
                if ($purgeId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                    exit;
                }
                // Nur Zeilen mit deleted_at setzen endgültig löschen (echtes DELETE)
                $stmt = $pdo->prepare("DELETE FROM consumables WHERE id = ? AND deleted_at IS NOT NULL");
                $stmt->execute([$purgeId]);
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden oder nicht soft-gelöscht']);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Verbrauchsmaterial endgültig gelöscht']);
                exit;
            }

            // Bestand anpassen (Tablet Einlagern/Auslagern) per EAN, Artikelnummer oder consumable_id
            if (isset($input['action']) && $input['action'] === 'adjust_stock') {
                if (!$canAdjustStock) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung zum Anpassen des Bestands']);
                    exit;
                }
                $code = trim((string)($input['code'] ?? $input['ean'] ?? $input['artikelnummer'] ?? ''));
                if ($code === '') {
                    $code = trim((string)($input['ean'] ?? ''));
                    if ($code === '') {
                        $code = trim((string)($input['artikelnummer'] ?? ''));
                    }
                }
                $consumableId = isset($input['consumable_id']) ? (int)$input['consumable_id'] : 0;
                $delta = isset($input['delta']) ? (int)$input['delta'] : 0;
                if ($delta === 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'delta muss ungleich 0 sein (positiv = Einlagern, negativ = Auslagern)']);
                    exit;
                }
                if ($code === '' && $consumableId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'EAN, Artikelnummer oder Verbrauchsmaterial-ID erforderlich']);
                    exit;
                }
                $delFilter = consumablesHasDeletedAt($pdo) ? ' AND (c.deleted_at IS NULL)' : '';
                if ($consumableId > 0) {
                    // Direkt per ID (auch ohne EAN/Artikelnummer)
                    $stmt = $pdo->prepare("
                        SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.lagerbestand, c.mindestbestand, c.auto_nachbestellen, c.spalte, c.fach, s.name AS shelf_name
                        FROM consumables c
                        LEFT JOIN shelves s ON s.id = c.shelf_id
                        WHERE c.id = ?" . $delFilter . "
                        LIMIT 1
                    ");
                    $stmt->execute([$consumableId]);
                } else {
                    // Suche nach EAN oder Artikelnummer
                    $stmt = $pdo->prepare("
                        SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.lagerbestand, c.mindestbestand, c.auto_nachbestellen, c.spalte, c.fach, s.name AS shelf_name
                        FROM consumables c
                        LEFT JOIN shelves s ON s.id = c.shelf_id
                        WHERE (c.ean = ? OR c.artikelnummer = ?)" . $delFilter . "
                        LIMIT 1
                    ");
                    $stmt->execute([$code, $code]);
                }
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                // Erneuter Treffer mit normalisierter GTIN (Scanner mit Leerzeichen o. Ä.)
                if (!$row && $consumableId <= 0) {
                    require_once __DIR__ . '/ean_product_lookup.php';
                    $gtinRetry = inventory_normalize_gtin($code);
                    if ($gtinRetry !== null && inventory_is_normalized_gtin_valid($gtinRetry)) {
                        $stmt->execute([$gtinRetry, $gtinRetry]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
                $neuAngelegt = false;
                $produktQuelle = null;
                // Unbekannte, gültige EAN/GTIN: automatisch anlegen (nur interne Rollen)
                if ($canEdit && !$row && $consumableId <= 0) {
                    require_once __DIR__ . '/ean_product_lookup.php';
                    if (inventory_is_valid_gtin_for_autocreate($code)) {
                        $gtin = inventory_normalize_gtin($code);
                        if ($gtin !== null && inventory_is_normalized_gtin_valid($gtin)) {
                            $stmt->execute([$gtin, $gtin]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!$row) {
                                $meta = inventory_fetch_gtin_metadata($gtin);
                                $bezeichnungNeu = trim((string)($meta['bezeichnung'] ?? ''));
                                if ($bezeichnungNeu === '') {
                                    $bezeichnungNeu = 'Artikel EAN ' . $gtin;
                                }
                                $beschreibungNeu = isset($meta['beschreibung']) ? trim((string)$meta['beschreibung']) : null;
                                if ($beschreibungNeu === '') {
                                    $beschreibungNeu = null;
                                }
                                // Keine Firma beim automatischen Anlegen (Firma bei Bedarf manuell zuordnen)
                                if (consumablesHasScanAutoReview($pdo)) {
                                    $insNew = $pdo->prepare("INSERT INTO consumables (bezeichnung, artikelnummer, ean, shop_veroeffentlicht, auto_nachbestellen, beschreibung, mindestbestand, lagerbestand, shelf_id, spalte, fach, company_id, scan_auto_review, erstellt_von) VALUES (?, ?, ?, 0, 0, ?, NULL, 0, NULL, NULL, NULL, NULL, 1, ?)");
                                } else {
                                    $insNew = $pdo->prepare("INSERT INTO consumables (bezeichnung, artikelnummer, ean, shop_veroeffentlicht, auto_nachbestellen, beschreibung, mindestbestand, lagerbestand, shelf_id, spalte, fach, company_id, erstellt_von) VALUES (?, ?, ?, 0, 0, ?, NULL, 0, NULL, NULL, NULL, NULL, ?)");
                                }
                                $insNew->execute([$bezeichnungNeu, null, $gtin, $beschreibungNeu, $userId]);
                                $newCid = (int)$pdo->lastInsertId();
                                $stmtReload = $pdo->prepare("
                                    SELECT c.id, c.bezeichnung, c.artikelnummer, c.ean, c.lagerbestand, c.mindestbestand, c.auto_nachbestellen, c.spalte, c.fach, s.name AS shelf_name
                                    FROM consumables c
                                    LEFT JOIN shelves s ON s.id = c.shelf_id
                                    WHERE c.id = ?" . $delFilter . "
                                    LIMIT 1
                                ");
                                $stmtReload->execute([$newCid]);
                                $row = $stmtReload->fetch(PDO::FETCH_ASSOC);
                                if ($row) {
                                    $neuAngelegt = true;
                                    $produktQuelle = (string)($meta['source'] ?? 'fallback');
                                }
                            }
                        }
                    }
                }
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden (EAN/Artikelnummer prüfen)']);
                    exit;
                }
                if (!empty($isCompanyInventoryUser) && $userCompanyId && !inventory_consumable_visible_for_company($pdo, (int)$row['id'], (int)$userCompanyId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diesen Artikel']);
                    exit;
                }
                $newStock = max(0, (int)$row['lagerbestand'] + $delta);
                $upd = $pdo->prepare("UPDATE consumables SET lagerbestand = ? WHERE id = ?");
                $upd->execute([$newStock, (int)$row['id']]);
                consumableClearPendingStockinAfterEinlagern($pdo, (int)$row['id'], $delta);
                $out = ['success' => true, 'lagerbestand' => $newStock];
                if ($delta < 0) {
                    $completedOrderId = inventory_complete_im_lager_order_after_auslagern($pdo, (int)$row['id'], $userId);
                    if ($completedOrderId !== null) {
                        $out['order_status_angekommen_id'] = $completedOrderId;
                    }
                }
                if ($neuAngelegt) {
                    $out['neu_angelegt'] = true;
                    $out['produkt_quelle'] = $produktQuelle;
                    $out['bezeichnung'] = (string)($row['bezeichnung'] ?? '');
                }
                $lagerortText = buildLagerortText($row['shelf_name'] ?? null, $row['spalte'] ?? null, $row['fach'] ?? null);
                if ($lagerortText !== null && $lagerortText !== '') {
                    $out['lagerort'] = $lagerortText;
                }
                $mindest = isset($row['mindestbestand']) && $row['mindestbestand'] !== null ? (int)$row['mindestbestand'] : null;
                if ($mindest !== null) {
                    $out['mindestbestand'] = $mindest;
                    $out['unter_mindestbestand'] = $newStock <= $mindest;
                    $out['bestellt'] = false;
                }
                // Bei Auslagern und Unterschreitung des Mindestbestands: nur eine Bestellung anlegen (keine Duplikate) — nur intern
                $autoNachbestellen = !empty($row['auto_nachbestellen']);
                if ($canEdit && $delta < 0 && $autoNachbestellen && $mindest !== null && $newStock < $mindest) {
                    $orderCreated = false;
                    $createdBestellnummer = null;
                    $bezeichnung = trim((string)($row['bezeichnung'] ?? ''));
                    $artikelnummer = trim((string)($row['artikelnummer'] ?? ''));
                    $eanRaw = isset($row['ean']) ? trim((string)$row['ean']) : '';
                    $consumableIdForOrder = (int)$row['id'];
                    $oldStockBefore = $newStock - $delta;
                    $lagerortOrder = buildLagerortText($row['shelf_name'] ?? null, $row['spalte'] ?? null, $row['fach'] ?? null);
                    $artikelLabel = $bezeichnung !== '' ? $bezeichnung : 'Verbrauchsmaterial';
                    $beschreibung = $artikelLabel . ' · Lager';
                    $notizLines = [
                        'Artikelnummer: ' . ($artikelnummer !== '' ? $artikelnummer : '–'),
                        'EAN: ' . ($eanRaw !== '' ? $eanRaw : '–'),
                        'Bestand nach Auslagern: ' . $newStock . ' Stück (vorher: ' . $oldStockBefore . ' Stück)',
                        'Meldebestand: ' . $mindest . ' Stück',
                    ];
                    if ($lagerortOrder !== null && $lagerortOrder !== '') {
                        $notizLines[] = 'Lagerort: ' . $lagerortOrder;
                    }
                    $notizLines[] = 'Auslöser: Auslagern bei aktivierter automatischer Nachbestellung (Mindestbestand unterschritten).';
                    $notizLines[] = '[inventar_consumable_id=' . $consumableIdForOrder . ']';
                    $notizen = implode("\n", $notizLines);
                    // Offene Bestellung zu diesem Artikel (technischer Marker in der Notiz)
                    $markerLike = '%[inventar_consumable_id=' . $consumableIdForOrder . ']%';
                    $existStmt = $pdo->prepare("SELECT id, bestellnummer FROM orders WHERE status IN ('Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager') AND (notizen LIKE ? OR beschreibung LIKE ?) ORDER BY id DESC LIMIT 1");
                    $existStmt->execute([$markerLike, $markerLike]);
                    $existingOrder = $existStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existingOrder) {
                        $orderCreated = true;
                        $createdBestellnummer = $existingOrder['bestellnummer'];
                    } else {
                        try {
                            do {
                                $bestellnummer = 'Lager-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE bestellnummer = ?");
                                $checkStmt->execute([$bestellnummer]);
                            } while ($checkStmt->fetch());
                            $singleCompanyId = consumableResolveSingleCompanyId($pdo, $consumableIdForOrder);
                            $insOrder = $pdo->prepare("INSERT INTO orders (bestellnummer, beschreibung, notizen, status, company_id, customer_id, erstellt_von, erstellt_datum, geaendert_datum) VALUES (?, ?, ?, 'Neu', ?, NULL, ?, NOW(), NOW())");
                            $insOrder->execute([$bestellnummer, $beschreibung, $notizen, $singleCompanyId, $userId]);
                            $orderId = (int)$pdo->lastInsertId();
                            if ($orderId > 0) {
                                try {
                                    $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, geaendert_von) VALUES (?, 'Neu', ?)");
                                    $histStmt->execute([$orderId, $userId]);
                                } catch (PDOException $e) { /* ignore */ }
                                try {
                                    $logStmt = $pdo->prepare("INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, erstellt_datum) VALUES ('order', ?, ?, 'created', NULL, NULL, NULL, NOW())");
                                    $logStmt->execute([$orderId, $userId]);
                                } catch (PDOException $e) { /* ignore */ }
                                $orderCreated = true;
                                $createdBestellnummer = $bestellnummer;
                            }
                        } catch (PDOException $e) {
                            error_log('Lager Mindestbestand: Bestellung anlegen fehlgeschlagen: ' . $e->getMessage());
                            $out['order_error'] = $e->getMessage();
                        }
                    }
                    $out['bestellt'] = $orderCreated;
                    if ($createdBestellnummer !== null) {
                        $out['bestellnummer'] = $createdBestellnummer;
                    }
                }
                $out['has_open_order'] = inventoryConsumableHasOpenOrder($pdo, (int)$row['id'], $row['bezeichnung'] ?? '', $row['artikelnummer'] ?? null) ? 1 : 0;
                if (consumablesHasPendingStockinAfterDelivery($pdo)) {
                    $prStmt = $pdo->prepare("SELECT pending_stockin_after_delivery FROM consumables WHERE id = ? LIMIT 1");
                    $prStmt->execute([(int)$row['id']]);
                    $out['pending_stockin_after_delivery'] = (int)$prStmt->fetchColumn();
                }
                echo json_encode($out);
                exit;
            }

            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }

            // Neue Kategorie anlegen
            if (isset($input['action']) && $input['action'] === 'create_category') {
                $name = trim((string)($input['name'] ?? ''));
                if ($name === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Name ist erforderlich']);
                    exit;
                }
                try {
                    $pdo->prepare("INSERT INTO consumable_categories (name) VALUES (?)")->execute([$name]);
                    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        echo json_encode(['success' => false, 'error' => 'Kategorie existiert bereits']);
                    } else {
                        throw $e;
                    }
                }
                exit;
            }

            $bezeichnung = trim((string)($input['bezeichnung'] ?? ''));
            if ($bezeichnung === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bezeichnung ist erforderlich']);
                exit;
            }
            $artikelnummer = trim((string)($input['artikelnummer'] ?? '')) ?: null;
            $ean = trim((string)($input['ean'] ?? '')) ?: null;
            $shopVeroeffentlicht = !empty($input['shop_veroeffentlicht']);
            $autoNachbestellen = parseBoolFlag($input['auto_nachbestellen'] ?? false);
            $beschreibung = trim((string)($input['beschreibung'] ?? '')) ?: null;
            $mindestbestand = isset($input['mindestbestand']) ? (int)$input['mindestbestand'] : null;
            if ($mindestbestand !== null && $mindestbestand < 0) {
                $mindestbestand = null;
            }
            $lagerbestand = isset($input['lagerbestand']) ? (int)$input['lagerbestand'] : 0;
            if ($lagerbestand < 0) {
                $lagerbestand = 0;
            }
            $shelfId = isset($input['shelf_id']) && (int)$input['shelf_id'] > 0 ? (int)$input['shelf_id'] : null;
            $spalte = isset($input['spalte']) && $input['spalte'] !== '' && $input['spalte'] !== null ? (int)$input['spalte'] : null;
            $fach = isset($input['fach']) && $input['fach'] !== '' && $input['fach'] !== null ? (int)$input['fach'] : null;
            $companyIdsOrdered = consumablesParseCompanyIdsFromInput($input);
            $companyId = $companyIdsOrdered[0] ?? null;

            if ($ean !== null && $ean !== '') {
                $dupId = consumablesFindActiveIdByEan($pdo, $ean, null);
                if ($dupId !== null) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Diese EAN ist bereits einem anderen Artikel zugeordnet (ID ' . $dupId . ').']);
                    exit;
                }
            }
            if ($artikelnummer !== null && $artikelnummer !== '') {
                $dupArtId = consumablesFindActiveIdByArtikelnummer($pdo, $artikelnummer, null);
                if ($dupArtId !== null) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Diese Artikelnummer ist bereits einem anderen Artikel zugeordnet (ID ' . $dupArtId . ').']);
                    exit;
                }
            }

            $pdo->prepare("INSERT INTO consumables (bezeichnung, artikelnummer, ean, shop_veroeffentlicht, auto_nachbestellen, beschreibung, mindestbestand, lagerbestand, shelf_id, spalte, fach, company_id, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$bezeichnung, $artikelnummer, $ean, $shopVeroeffentlicht ? 1 : 0, $autoNachbestellen ? 1 : 0, $beschreibung, $mindestbestand, $lagerbestand, $shelfId, $spalte, $fach, $companyId, $userId]);
            $consumableId = (int)$pdo->lastInsertId();

            $deviceModels = $input['device_models'] ?? [];
            if (is_array($deviceModels)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO consumable_device_models (consumable_id, hersteller, modell) VALUES (?, ?, ?)");
                foreach ($deviceModels as $dm) {
                    $h = trim((string)($dm['hersteller'] ?? ''));
                    $m = trim((string)($dm['modell'] ?? ''));
                    if ($h !== '' || $m !== '') {
                        $ins->execute([$consumableId, $h ?: '', $m ?: '']);
                    }
                }
            }
            $categoryIds = $input['category_ids'] ?? [];
            if (is_array($categoryIds)) {
                $insCat = $pdo->prepare("INSERT IGNORE INTO consumable_category_link (consumable_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $insCat->execute([$consumableId, $cid]);
                }
            }

            consumableReplaceCompanyLinks($pdo, $consumableId, $companyIdsOrdered);

            echo json_encode(['success' => true, 'id' => $consumableId]);
            exit;

        case 'PUT':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $consumableId = isset($input['id']) ? (int)$input['id'] : 0;
            if ($consumableId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            $bezeichnung = trim((string)($input['bezeichnung'] ?? ''));
            if ($bezeichnung === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bezeichnung ist erforderlich']);
                exit;
            }
            $artikelnummer = trim((string)($input['artikelnummer'] ?? '')) ?: null;
            $ean = trim((string)($input['ean'] ?? '')) ?: null;
            $autoNachbestellen = parseBoolFlag($input['auto_nachbestellen'] ?? false);
            $beschreibung = trim((string)($input['beschreibung'] ?? '')) ?: null;
            $mindestbestand = isset($input['mindestbestand']) ? (int)$input['mindestbestand'] : null;
            if ($mindestbestand !== null && $mindestbestand < 0) {
                $mindestbestand = null;
            }
            $lagerbestand = isset($input['lagerbestand']) ? (int)$input['lagerbestand'] : 0;
            if ($lagerbestand < 0) {
                $lagerbestand = 0;
            }
            $shelfId = isset($input['shelf_id']) && (int)$input['shelf_id'] > 0 ? (int)$input['shelf_id'] : null;
            $spalte = isset($input['spalte']) && $input['spalte'] !== '' && $input['spalte'] !== null ? (int)$input['spalte'] : null;
            $fach = isset($input['fach']) && $input['fach'] !== '' && $input['fach'] !== null ? (int)$input['fach'] : null;
            $companyIdsOrdered = consumablesParseCompanyIdsFromInput($input);
            $companyId = $companyIdsOrdered[0] ?? null;

            if ($ean !== null && $ean !== '') {
                $dupId = consumablesFindActiveIdByEan($pdo, $ean, $consumableId);
                if ($dupId !== null) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Diese EAN ist bereits einem anderen Artikel zugeordnet (ID ' . $dupId . ').']);
                    exit;
                }
            }
            if ($artikelnummer !== null && $artikelnummer !== '') {
                $dupArtId = consumablesFindActiveIdByArtikelnummer($pdo, $artikelnummer, $consumableId);
                if ($dupArtId !== null) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Diese Artikelnummer ist bereits einem anderen Artikel zugeordnet (ID ' . $dupArtId . ').']);
                    exit;
                }
            }

            $delWhere = consumablesHasDeletedAt($pdo) ? ' AND (deleted_at IS NULL)' : '';
            $shopVeroeffentlichtVal = 0;
            if (array_key_exists('shop_veroeffentlicht', $input)) {
                $shopVeroeffentlichtVal = !empty($input['shop_veroeffentlicht']) ? 1 : 0;
            } else {
                $stmtShopCur = $pdo->prepare("SELECT shop_veroeffentlicht FROM consumables WHERE id = ?" . $delWhere);
                $stmtShopCur->execute([$consumableId]);
                $shopRow = $stmtShopCur->fetch(PDO::FETCH_ASSOC);
                $shopVeroeffentlichtVal = $shopRow ? (int)($shopRow['shop_veroeffentlicht'] ?? 0) : 0;
            }
            $stmtPrevLager = $pdo->prepare("SELECT lagerbestand FROM consumables WHERE id = ?" . $delWhere);
            $stmtPrevLager->execute([$consumableId]);
            $prevLagerRow = $stmtPrevLager->fetch(PDO::FETCH_ASSOC);
            $prevLagerbestand = $prevLagerRow ? (int)$prevLagerRow['lagerbestand'] : 0;

            $updVals = [$bezeichnung, $artikelnummer, $ean, $shopVeroeffentlichtVal, $autoNachbestellen ? 1 : 0, $beschreibung, $mindestbestand, $lagerbestand, $shelfId, $spalte, $fach, $companyId, $consumableId];
            if (consumablesHasScanAutoReview($pdo)) {
                $stmt = $pdo->prepare("UPDATE consumables SET bezeichnung = ?, artikelnummer = ?, ean = ?, shop_veroeffentlicht = ?, auto_nachbestellen = ?, beschreibung = ?, mindestbestand = ?, lagerbestand = ?, shelf_id = ?, spalte = ?, fach = ?, company_id = ?, scan_auto_review = 0 WHERE id = ?" . $delWhere);
            } else {
                $stmt = $pdo->prepare("UPDATE consumables SET bezeichnung = ?, artikelnummer = ?, ean = ?, shop_veroeffentlicht = ?, auto_nachbestellen = ?, beschreibung = ?, mindestbestand = ?, lagerbestand = ?, shelf_id = ?, spalte = ?, fach = ?, company_id = ? WHERE id = ?" . $delWhere);
            }
            $stmt->execute($updVals);
            if ($stmt->rowCount() === 0) {
                // rowCount() kann 0 sein, wenn Werte unverändert bleiben.
                // Deshalb separat prüfen, ob der Datensatz existiert.
                $existsStmt = $pdo->prepare("SELECT id FROM consumables WHERE id = ?" . $delWhere . " LIMIT 1");
                $existsStmt->execute([$consumableId]);
                if (!$existsStmt->fetchColumn()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden']);
                    exit;
                }
            }

            if ($lagerbestand > $prevLagerbestand) {
                consumableClearPendingStockinAfterEinlagern($pdo, $consumableId, $lagerbestand - $prevLagerbestand);
            }

            if (array_key_exists('device_models', $input) && is_array($input['device_models'])) {
                $pdo->prepare("DELETE FROM consumable_device_models WHERE consumable_id = ?")->execute([$consumableId]);
                $ins = $pdo->prepare("INSERT INTO consumable_device_models (consumable_id, hersteller, modell) VALUES (?, ?, ?)");
                foreach ($input['device_models'] as $dm) {
                    $h = trim((string)($dm['hersteller'] ?? ''));
                    $m = trim((string)($dm['modell'] ?? ''));
                    if ($h !== '' || $m !== '') {
                        $ins->execute([$consumableId, $h ?: '', $m ?: '']);
                    }
                }
            }
            if (array_key_exists('category_ids', $input) && is_array($input['category_ids'])) {
                $pdo->prepare("DELETE FROM consumable_category_link WHERE consumable_id = ?")->execute([$consumableId]);
                $insCat = $pdo->prepare("INSERT INTO consumable_category_link (consumable_id, category_id) VALUES (?, ?)");
                foreach ($input['category_ids'] as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $insCat->execute([$consumableId, $cid]);
                }
            }

            consumableReplaceCompanyLinks($pdo, $consumableId, $companyIdsOrdered);

            echo json_encode(['success' => true]);
            exit;

        case 'DELETE':
            if (!$canEdit) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID fehlt']);
                exit;
            }
            ensureConsumablesDeletedAtColumn($pdo);
            if (consumablesHasDeletedAt($pdo)) {
                $stmt = $pdo->prepare("UPDATE consumables SET deleted_at = NOW() WHERE id = ? AND (deleted_at IS NULL)");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM consumables WHERE id = ?");
                $stmt->execute([$id]);
            }
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Verbrauchsmaterial nicht gefunden']);
                exit;
            }
            echo json_encode(['success' => true]);
            exit;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
