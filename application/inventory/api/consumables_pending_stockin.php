<?php
/**
 * Hilfsfunktionen für consumables.pending_stockin_after_delivery (Migration 113).
 * Wird von inventory/api/consumables.php und orders/api/orders.php eingebunden.
 */

function consumablesHasPendingStockinAfterDelivery($pdo) {
    if (isset($GLOBALS['_consumables_has_pending_stockin'])) {
        return (bool)$GLOBALS['_consumables_has_pending_stockin'];
    }
    try {
        $pdo->query("SELECT pending_stockin_after_delivery FROM consumables LIMIT 0");
        $GLOBALS['_consumables_has_pending_stockin'] = true;
        return true;
    } catch (PDOException $e) {
        $GLOBALS['_consumables_has_pending_stockin'] = false;
        return false;
    }
}

function ensureConsumablesPendingStockinAfterDeliveryColumn($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'pending_stockin_after_delivery'");
        if ($stmt && $stmt->rowCount() > 0) {
            $GLOBALS['_consumables_has_pending_stockin'] = true;
            return;
        }
        $after = 'company_id';
        try {
            $sc = $pdo->query("SHOW COLUMNS FROM consumables LIKE 'scan_auto_review'");
            if ($sc && $sc->rowCount() > 0) {
                $after = 'scan_auto_review';
            }
        } catch (PDOException $e) { /* ignore */ }
        $pdo->exec("ALTER TABLE consumables ADD COLUMN pending_stockin_after_delivery tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Bestellung Im Lager/Angekommen, noch kein Einlagern' AFTER " . $after);
        $GLOBALS['_consumables_has_pending_stockin'] = true;
    } catch (PDOException $e) {
        error_log('Consumables pending_stockin_after_delivery Spalte: ' . $e->getMessage());
    }
}

/**
 * Setzt das Einlagerungs-Flag anhand von Notiz und/oder Beschreibung ([inventar_consumable_id=n]).
 */
function consumableApplyPendingStockinFlagFromOrderNotizen($pdo, $notizen, $setToOne, $beschreibung = null) {
    ensureConsumablesPendingStockinAfterDeliveryColumn($pdo);
    if (!consumablesHasPendingStockinAfterDelivery($pdo)) {
        return;
    }
    $combined = trim((string)$notizen) . "\n" . trim((string)$beschreibung);
    if (!preg_match('/\[inventar_consumable_id=(\d+)\]/', $combined, $m)) {
        return;
    }
    $cid = (int)$m[1];
    if ($cid <= 0) {
        return;
    }
    $val = $setToOne ? 1 : 0;
    $pdo->prepare("UPDATE consumables SET pending_stockin_after_delivery = ? WHERE id = ?")->execute([$val, $cid]);
}

/**
 * Nach Einlagern (positiver Bestandsdelta): Flag zurücksetzen.
 */
function consumableClearPendingStockinAfterEinlagern($pdo, $consumableId, $delta) {
    if ((int)$delta <= 0 || (int)$consumableId <= 0) {
        return;
    }
    ensureConsumablesPendingStockinAfterDeliveryColumn($pdo);
    if (!consumablesHasPendingStockinAfterDelivery($pdo)) {
        return;
    }
    $pdo->prepare("UPDATE consumables SET pending_stockin_after_delivery = 0 WHERE id = ?")->execute([(int)$consumableId]);
}
