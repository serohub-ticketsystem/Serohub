<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$userId = $_SESSION['user_id'];
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$filterCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

$rows = [];
$total = 0;
$tableMissing = false;
$categories = [];

try {
    $countSql = 'SELECT COUNT(*) FROM mail_log';
    $params = [];
    if ($filterCategory !== '') {
        $countSql .= ' WHERE category = ?';
        $params[] = $filterCategory;
    }
    $cstmt = $pdo->prepare($countSql);
    $cstmt->execute($params);
    $total = (int) $cstmt->fetchColumn();

    $sql = 'SELECT id, sent_at, recipients, subject, from_email, category, status, error_message FROM mail_log';
    if ($filterCategory !== '') {
        $sql .= ' WHERE category = ?';
    }
    $sql .= ' ORDER BY sent_at DESC, id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    if ($filterCategory !== '') {
        $stmt->execute([$filterCategory]);
    } else {
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catStmt = $pdo->query('SELECT DISTINCT category FROM mail_log ORDER BY category ASC');
    if ($catStmt) {
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'mail_log') !== false) {
        $tableMissing = true;
    } else {
        error_log('mail-log: ' . $e->getMessage());
    }
}

$totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
$baseQuery = ['category' => $filterCategory];
$buildPageUrl = function ($p) use ($baseQuery) {
    $q = array_filter($baseQuery, function ($v) {
        return $v !== '' && $v !== null;
    });
    $q['page'] = $p;
    return '?' . http_build_query($q);
};

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main>
    <div class="px-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Mail-Log</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ausgehende E-Mails</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Welche System-E-Mail ging wann an wen – mit Bereich/Kategorie</p>
              </div>
              <form method="get" class="flex flex-wrap items-end gap-2">
                <div>
                  <label for="category" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Kategorie</label>
                  <select name="category" id="category" class="block min-w-[200px] p-2 text-sm border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">Alle</option>
                    <?php foreach ($categories as $c) : ?>
                      <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterCategory === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">Filtern</button>
                <?php if ($filterCategory !== '') : ?>
                  <a href="<?php echo htmlspecialchars(BASE_URL); ?>admin/mail-log.php" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500">Zurücksetzen</a>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4 mb-8">
          <?php if ($tableMissing) : ?>
            <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-100">
              <p class="font-medium">Tabelle <code class="text-sm">mail_log</code> ist noch nicht angelegt.</p>
              <p class="mt-2 text-sm">Bitte die Migration ausführen: <code class="text-sm">mysql -u … -p serviceportal &lt; migrations/118_mail_log.sql</code></p>
            </div>
          <?php else : ?>
            <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400">
                  <tr>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">Zeit</th>
                    <th scope="col" class="px-4 py-3">Kategorie</th>
                    <th scope="col" class="px-4 py-3">An (Empfänger)</th>
                    <th scope="col" class="px-4 py-3">Von</th>
                    <th scope="col" class="px-4 py-3 min-w-[200px]">Betreff</th>
                    <th scope="col" class="px-4 py-3 whitespace-nowrap">Status</th>
                    <th scope="col" class="px-4 py-3 max-w-xs">Fehler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($rows)) : ?>
                    <tr>
                      <td colspan="7" class="px-4 py-8 text-center text-gray-500">Keine Einträge<?php echo $filterCategory !== '' ? ' für diese Kategorie' : ''; ?>.</td>
                    </tr>
                  <?php else : ?>
                    <?php foreach ($rows as $r) : ?>
                      <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50/80 dark:hover:bg-gray-700/40">
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs"><?php echo htmlspecialchars($r['sent_at']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['category']); ?></td>
                        <td class="px-4 py-3 break-all max-w-[220px]"><?php echo htmlspecialchars($r['recipients']); ?></td>
                        <td class="px-4 py-3 break-all"><?php echo htmlspecialchars($r['from_email'] ?? '–'); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($r['subject']); ?></td>
                        <td class="px-4 py-3">
                          <?php if ($r['status'] === 'success') : ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">OK</span>
                          <?php else : ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">Fehler</span>
                          <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-red-600 dark:text-red-400 break-words"><?php echo $r['error_message'] ? htmlspecialchars($r['error_message']) : '–'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <?php if ($totalPages > 1) : ?>
              <nav class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-400" aria-label="Seiten">
                <span><?php echo (int) $total; ?> Einträge, Seite <?php echo $page; ?> von <?php echo $totalPages; ?></span>
                <div class="flex gap-2">
                  <?php if ($page > 1) : ?>
                    <a class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" href="<?php echo htmlspecialchars($buildPageUrl($page - 1)); ?>">Zurück</a>
                  <?php endif; ?>
                  <?php if ($page < $totalPages) : ?>
                    <a class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" href="<?php echo htmlspecialchars($buildPageUrl($page + 1)); ?>">Weiter</a>
                  <?php endif; ?>
                </div>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
