<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/inventory_permissions.php';
require_once dirname(__DIR__) . '/companies/helper/encryption.php';
requireLogin();
inventory_permissions_ensure_columns($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
try {
    $stmt = $pdo->prepare('SELECT id, rolle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'] ?? '';
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$filterRole = isset($_GET['role']) ? (string) $_GET['role'] : '';
$filterStatus = isset($_GET['status']) ? (string) $_GET['status'] : '';

$allRoles = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT rolle FROM users WHERE rolle IS NOT NULL AND rolle != '' ORDER BY rolle ASC");
    $allRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $allRoles = [];
}

$users = [];
try {
    $sql = "
        SELECT u.id, u.email, u.vorname, u.nachname, u.rolle, u.status, u.company_id,
               u.erstellt_datum, u.letzte_anmeldung, u.gesperrt_bis, c.name AS company_name,
               (SELECT COUNT(*) FROM user_settings us WHERE us.user_id = u.id) AS settings_count
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE 1=1
    ";
    $params = [];
    if ($filterRole !== '') {
        $sql .= ' AND u.rolle = ?';
        $params[] = $filterRole;
    }
    if ($filterStatus !== '') {
        $sql .= ' AND u.status = ?';
        $params[] = $filterStatus;
    }
    $sql .= ' ORDER BY u.nachname, u.vorname';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$u) {
        if (!empty($u['company_name'])) {
            $u['company_name'] = decrypt_from_db($u['company_name']);
        }
    }
    unset($u);
} catch (PDOException $e) {
    error_log('admin/users.php: ' . $e->getMessage());
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
    <main>
        <div class="px-4">
            <div class="col-span-full mx-4 mt-4">
                <nav class="mb-4 flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                        <li class="inline-flex items-center">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                                <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd"/></svg>
                                Startseite
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                            </div>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Benutzer</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Benutzerverwaltung</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Alle Benutzer im Überblick – Klick öffnet die Detailseite mit Einstellungen und Auswertung.</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>admin/user_create.php" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600">
                        Neuer Benutzer
                    </a>
                </div>
            </div>

            <div class="relative col-span-full px-4 pb-8">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative flex-1 md:max-w-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="search" id="userSearch" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2 pl-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Suchen…">
                        </div>
                        <select id="filterRole" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Alle Rollen</option>
                            <?php foreach ($allRoles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $filterRole === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap gap-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Status:</span>
                    <?php
                    $statusOptions = ['' => 'Alle', 'aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'gesperrt' => 'Gesperrt'];
                    foreach ($statusOptions as $val => $label):
                    ?>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" name="status" value="<?php echo htmlspecialchars($val); ?>" class="text-primary-600" <?php echo $filterStatus === $val ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">E-Mail</th>
                                    <th class="px-4 py-3">Rolle</th>
                                    <th class="px-4 py-3">Firma</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Einstellungen</th>
                                    <th class="px-4 py-3">Letzte Anmeldung</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Keine Benutzer gefunden</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u):
                                        $name = trim(($u['vorname'] ?? '') . ' ' . ($u['nachname'] ?? '')) ?: $u['email'];
                                        $status = $u['status'] ?? 'aktiv';
                                        $statusClass = match ($status) {
                                            'aktiv' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'inaktiv' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                            'gesperrt' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            default => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        };
                                        $detailUrl = BASE_URL . 'admin/user.php?id=' . (int) $u['id'];
                                    ?>
                                    <tr class="user-row border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                        data-company-id="<?php echo htmlspecialchars((string) ($u['company_id'] ?? '')); ?>"
                                        data-role="<?php echo htmlspecialchars($u['rolle'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($name . ' ' . $u['email'])); ?>">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="hover:text-primary-600 dark:hover:text-primary-400"><?php echo htmlspecialchars($name); ?></a>
                                        </td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200"><?php echo htmlspecialchars($u['rolle'] ?? '-'); ?></span>
                                        </td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($u['company_name'] ?? '-'); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"><?php echo (int) ($u['settings_count'] ?? 0); ?></span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php
                                            if (!empty($u['letzte_anmeldung'])) {
                                                echo (new DateTime($u['letzte_anmeldung']))->format('d.m.Y H:i');
                                            } else {
                                                echo 'Nie';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Details</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.user-row');
    function applyFilters() {
        let companyFilter = '';
        const saved = localStorage.getItem('selectedUserOption');
        if (saved) {
            try {
                const data = JSON.parse(saved);
                if (data.id && data.id !== '0') companyFilter = String(data.id);
            } catch (e) {}
        }
        const roleFilter = document.getElementById('filterRole')?.value || '';
        const statusFilter = document.querySelector('input[name="status"]:checked')?.value ?? '';
        const search = (document.getElementById('userSearch')?.value || '').toLowerCase();
        rows.forEach(row => {
            let ok = true;
            if (companyFilter && row.dataset.companyId !== companyFilter) ok = false;
            if (roleFilter && row.dataset.role !== roleFilter) ok = false;
            if (statusFilter && row.dataset.status !== statusFilter) ok = false;
            if (search && !(row.dataset.search || '').includes(search)) ok = false;
            row.style.display = ok ? '' : 'none';
        });
    }
    document.getElementById('filterRole')?.addEventListener('change', applyFilters);
    document.querySelectorAll('input[name="status"]').forEach(r => r.addEventListener('change', applyFilters));
    document.getElementById('userSearch')?.addEventListener('input', applyFilters);
    applyFilters();
});
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
