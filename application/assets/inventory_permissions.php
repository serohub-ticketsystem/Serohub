<?php
/**
 * Lager-Berechtigungen: Techniker/Admin immer; Firmen nur wenn companies.lager_zugriff;
 * Bestand ändern: Tech/Admin immer; Firmen-Benutzer nur mit users.lager_bestand_anpassen.
 */

declare(strict_types=1);

function inventory_permissions_ensure_columns(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM companies LIKE 'lager_zugriff'");
        if ($chk && $chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN lager_zugriff tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Firma darf Lager einsehen' AFTER status");
        }
    } catch (PDOException $e) {
        error_log('inventory_permissions companies.lager_zugriff: ' . $e->getMessage());
    }
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'lager_bestand_anpassen'");
        if ($chk && $chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN lager_bestand_anpassen tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Darf Lagerbestand anpassen' AFTER company_id");
        }
    } catch (PDOException $e) {
        error_log('inventory_permissions users.lager_bestand_anpassen: ' . $e->getMessage());
    }
}

/**
 * @return array{id:int, rolle:string, company_id:?int, lager_zugriff:int, lager_bestand_anpassen:int}|null
 */
function inventory_permissions_load_user(PDO $pdo, int $userId): ?array {
    inventory_permissions_ensure_columns($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.rolle, u.company_id,
                   COALESCE(u.lager_bestand_anpassen, 0) AS lager_bestand_anpassen,
                   COALESCE(c.lager_zugriff, 0) AS lager_zugriff
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'rolle' => (string)$row['rolle'],
            'company_id' => isset($row['company_id']) && $row['company_id'] !== null ? (int)$row['company_id'] : null,
            'lager_zugriff' => (int)($row['lager_zugriff'] ?? 0),
            'lager_bestand_anpassen' => (int)($row['lager_bestand_anpassen'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('inventory_permissions_load_user: ' . $e->getMessage());
        return null;
    }
}

function inventory_user_can_full_edit(string $rolle): bool {
    return $rolle === 'Admin' || $rolle === 'Techniker';
}

/** @param array{id:int, rolle:string, company_id:?int, lager_zugriff:int, lager_bestand_anpassen:int} $u */
function inventory_user_can_view_from_row(array $u): bool {
    if (inventory_user_can_full_edit($u['rolle'])) {
        return true;
    }
    $cid = $u['company_id'];
    return $cid !== null && $cid > 0 && !empty($u['lager_zugriff']);
}

/** @param array{id:int, rolle:string, company_id:?int, lager_zugriff:int, lager_bestand_anpassen:int} $u */
function inventory_user_can_adjust_from_row(array $u): bool {
    if (inventory_user_can_full_edit($u['rolle'])) {
        return true;
    }
    return inventory_user_can_view_from_row($u) && !empty($u['lager_bestand_anpassen']);
}

function inventory_user_can_view_inventory(PDO $pdo, int $userId): bool {
    $u = inventory_permissions_load_user($pdo, $userId);
    return $u ? inventory_user_can_view_from_row($u) : false;
}

function inventory_user_can_adjust_stock(PDO $pdo, int $userId): bool {
    $u = inventory_permissions_load_user($pdo, $userId);
    return $u ? inventory_user_can_adjust_from_row($u) : false;
}

/**
 * Prüft, ob ein Artikel zur Firma gehört (Hauptfirma oder Verknüpfung).
 */
function inventory_consumable_visible_for_company(PDO $pdo, int $consumableId, int $companyId): bool {
    if ($consumableId <= 0 || $companyId <= 0) {
        return false;
    }
    try {
        $pdo->query('SELECT 1 FROM consumable_company_link LIMIT 0');
        $hasLink = true;
    } catch (PDOException $e) {
        $hasLink = false;
    }
    $del = '';
    try {
        $pdo->query('SELECT deleted_at FROM consumables LIMIT 0');
        $del = ' AND (deleted_at IS NULL)';
    } catch (PDOException $e) {
        $del = '';
    }
    if ($hasLink) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM consumables c
            WHERE c.id = ?
            $del
            AND (
                c.company_id = ?
                OR EXISTS (SELECT 1 FROM consumable_company_link x WHERE x.consumable_id = c.id AND x.company_id = ?)
            )
            LIMIT 1
        ");
        $stmt->execute([$consumableId, $companyId, $companyId]);
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM consumables c WHERE c.id = ? $del AND c.company_id = ? LIMIT 1");
        $stmt->execute([$consumableId, $companyId]);
    }
    return (bool)$stmt->fetchColumn();
}
