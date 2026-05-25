<?php
/**
 * Helper: Service-Aktionen in die Logs-Tabelle schreiben.
 * Alle Vorkommnisse im Ordner tickets/ werden geloggt (API + Seiten).
 *
 * @param PDO $pdo
 * @param int $userId
 * @param string $kategorie 'ticket' | 'sonstiges'
 * @param int $entityId z.B. ticket_id, 0 für übergreifend
 * @param string $action 'created' | 'updated' | 'deleted' | 'viewed'
 * @param string|null $fieldName
 * @param string|null $oldValue
 * @param string|null $newValue
 * @param string|null $beschreibung
 */
function service_log($pdo, $userId, $kategorie, $entityId, $action, $fieldName = null, $oldValue = null, $newValue = null, $beschreibung = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (kategorie, entity_id, user_id, action, field_name, old_value, new_value, beschreibung, erstellt_datum)
            VALUES (:kategorie, :entity_id, :user_id, :action, :field_name, :old_value, :new_value, :beschreibung, NOW())
        ");
        $stmt->bindValue(':kategorie', $kategorie, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', (int) $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int) $userId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':field_name', $fieldName, $fieldName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':old_value', $oldValue, $oldValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':new_value', $newValue, $newValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':beschreibung', $beschreibung, $beschreibung === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('service_log: ' . $e->getMessage());
    }
}
