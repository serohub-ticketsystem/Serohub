<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/database-backup-helper.php';
requireLogin();

$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare('SELECT id, rolle FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'] ?? null;
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

$flash = null;
if (!empty($_SESSION['db_import_flash']) && is_array($_SESSION['db_import_flash'])) {
    $flash = $_SESSION['db_import_flash'];
    unset($_SESSION['db_import_flash']);
}

$mysqldumpOk = db_backup_find_cli('mysqldump') !== null;
$mysqlCliOk = db_backup_find_cli('mysql') !== null;

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
                      <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Datenbank sichern</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Datenbank exportieren &amp; importieren</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Vollständige Sicherung für Serverumzug oder Wiederherstellung</p>
              </div>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
        <div class="col-span-full mx-4">
          <div class="rounded-lg border p-4 <?php echo ($flash['type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200'; ?>">
            <p class="text-sm font-medium"><?php echo htmlspecialchars($flash['text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
        <?php endif; ?>

        <div class="col-span-full mx-4 space-y-4">
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Verschlüsselung &amp; neuer Server</h2>
            <p class="mt-2 text-sm text-amber-900/80 dark:text-amber-100/90">
              Der SQL-Export enthält die Daten <strong>genau so</strong>, wie sie in MySQL stehen – also auch Felder mit Anwendungsverschlüsselung (Präfix <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">ENC:</code> bei Aufgaben, Firmen, Kunden usw.).
              Nach dem Import auf einem neuen System müssen die Werte weiterhin mit dem <strong>gleichen Schlüssel</strong> entschlüsselt werden können:
            </p>
            <ul class="mt-2 list-inside list-disc text-sm text-amber-900/80 dark:text-amber-100/90">
              <li>Wenn in <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">config.php</code> <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">ENCRYPTION_KEY</code> gesetzt ist: denselben Wert auf dem Zielserver übernehmen.</li>
              <li>Wenn kein <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">ENCRYPTION_KEY</code> gesetzt ist: die Verschlüsselung hängt von <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">DB_PASS</code> ab – dann muss die Ableitung auf dem Zielsystem übereinstimmen (typisch: gleiches <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">DB_PASS</code> wie auf der Quelle oder explizit gesetzter <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">ENCRYPTION_KEY</code>).</li>
              <li>CalDAV- und ähnliche in der DB gespeicherte Geheimnisse können zusätzlich von <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">DB_PASS</code> abhängen – bei Problemen Quell-<code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">config.php</code> und Ziel-<code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">config.php</code> vergleichen.</li>
            </ul>
            <p class="mt-2 text-sm text-amber-900/80 dark:text-amber-100/90">
              Empfehlung für Umzüge: Auf dem neuen Server zuerst eine <strong>leere</strong> Datenbank anlegen, <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">config.php</code> mit passenden Zugangsdaten und <strong>identischem</strong> <code class="rounded bg-amber-100/80 px-1 dark:bg-amber-900/50">ENCRYPTION_KEY</code> (bzw. konsistentem Schlüsselkonzept) einrichten, dann diesen Import ausführen.
            </p>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Export</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              Es wird eine <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">.sql</code>-Datei erzeugt (Standard-SQL, inkl. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">DROP TABLE</code> / <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">CREATE</code> / <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">INSERT</code>).
              <?php if ($mysqldumpOk): ?>
                Auf diesem Server wird <strong>mysqldump</strong> genutzt (inkl. Trigger; gespeicherte Routinen, falls erlaubt).
              <?php else: ?>
                <strong>mysqldump</strong> wurde nicht gefunden – der Export läuft rein über PHP (langsamer, speicherintensiver bei sehr großen Tabellen). Alternativ auf der Shell: <code class="mt-1 block rounded bg-gray-100 p-2 text-xs dark:bg-gray-700">mysqldump …</code>
              <?php endif; ?>
            </p>
            <a href="<?php echo BASE_URL; ?>admin/api/database-export.php" class="mt-4 inline-flex items-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-500 dark:hover:bg-primary-600 dark:focus:ring-primary-800">
              <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              SQL-Export herunterladen
            </a>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Import</h2>
            <?php if (!$mysqlCliOk): ?>
            <div class="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
              Das Kommandozeilenprogramm <strong>mysql</strong> wurde auf diesem Server nicht gefunden. Der Web-Import ist so nicht möglich. Bitte die SQL-Datei per SSH einspielen, z.&nbsp;B. <code class="rounded bg-red-100/80 px-1 dark:bg-red-900/40">mysql -u … -p datenbank &lt; backup.sql</code>.
            </div>
            <?php endif; ?>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              Der Import <strong>ersetzt Inhalte</strong> entsprechend der SQL-Datei (bei typischen Exporten werden Tabellen zuvor gelöscht und neu angelegt). Vorher unbedingt ein Backup der Ziel-Datenbank erstellen.
              Erlaubte Dateien: <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">.sql</code> oder <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">.sql.gz</code>.
              Bei großen Dateien ggf. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">upload_max_filesize</code> und <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">post_max_size</code> in der PHP-Konfiguration erhöhen.
            </p>
            <form method="post" action="<?php echo BASE_URL; ?>admin/api/database-import.php" enctype="multipart/form-data" class="mt-4 space-y-4 max-w-xl">
              <div>
                <label for="sql_file" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">SQL-Datei</label>
                <input type="file" name="sql_file" id="sql_file" accept=".sql,.gz,.sql.gz" required
                  class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-900 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400 dark:placeholder-gray-400"
                  <?php echo $mysqlCliOk ? '' : 'disabled'; ?>>
              </div>
              <div>
                <label for="import_confirm" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Bestätigung</label>
                <input type="text" name="import_confirm" id="import_confirm" autocomplete="off" placeholder="IMPORTIEREN"
                  class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                  <?php echo $mysqlCliOk ? 'required' : 'disabled'; ?>>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Zur Sicherheit exakt <strong>IMPORTIEREN</strong> eintippen.</p>
              </div>
              <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-800"
                <?php echo $mysqlCliOk ? '' : 'disabled'; ?>>
                Import starten
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
