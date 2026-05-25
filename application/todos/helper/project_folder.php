<?php
/**
 * Liefert die ID des einen globalen Systemordners "Projektaufgaben" (company_id = NULL).
 * Dort landen alle Aufgaben mit project_id, wenn die Einstellung aktiv ist. Ordner kann nicht gelöscht werden.
 * Gibt null zurück, wenn die Spalte is_project_system_folder fehlt.
 */
function getOrCreateProjectTasksFolder(PDO $pdo, $companyId, $userId) {
    try {
        $pdo->query("SELECT is_project_system_folder FROM todo_folders LIMIT 1");
    } catch (PDOException $e) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT id, name FROM todo_folders
        WHERE COALESCE(is_project_system_folder, 0) = 1
        AND company_id IS NULL
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }
    try {
        $ins = $pdo->prepare("
            INSERT INTO todo_folders (name, company_id, is_private, is_ticket_system_folder, is_project_system_folder, erstellt_von, erstellt_datum)
            VALUES ('Projektaufgaben', NULL, 0, 0, 1, ?, NOW())
        ");
        $ins->execute([$userId]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'is_project_system_folder') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            return null;
        }
        throw $e;
    }
}
