<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = null;
try {
    $stmt = $pdo->prepare("SELECT rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'] ?? null;
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

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
          <nav class="mb-4 flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
              <li class="inline-flex items-center">
                <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">Startseite</a>
              </li>
              <li>
                <div class="flex items-center">
                  <svg class="mx-1 h-4 w-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                  <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">Administration</a>
                </div>
              </li>
              <li aria-current="page">
                <div class="flex items-center">
                  <svg class="mx-1 h-4 w-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                  <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400">Server & PHP-Konfiguration</span>
                </div>
              </li>
            </ol>
          </nav>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Server & PHP-Konfiguration</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Konfiguration einsehen und direkt aus dem System bearbeiten (.user.ini und .htaccess).</p>
        </div>

        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-4">
              <div class="text-xs text-gray-500 dark:text-primary-220">Server-Uptime</div>
              <div id="uptimeText" class="text-xl font-semibold text-gray-900 dark:text-white mt-1">-</div>
            </div>
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-4">
              <div class="text-xs text-gray-500 dark:text-primary-220">CPU-Auslastung (Load 1m)</div>
              <div id="load1Text" class="text-xl font-semibold text-gray-900 dark:text-white mt-1">-</div>
            </div>
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-4">
              <div class="text-xs text-gray-500 dark:text-primary-220">RAM-Verbrauch (System)</div>
              <div id="ramUsageText" class="text-xl font-semibold text-gray-900 dark:text-white mt-1">-</div>
            </div>
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-4">
              <div class="text-xs text-gray-500 dark:text-primary-220">Freier Speicher (Webapp-Disk)</div>
              <div id="diskFreeText" class="text-xl font-semibold text-gray-900 dark:text-white mt-1">-</div>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">System-Status</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div><dt class="text-gray-500 dark:text-primary-220">Server-Software</dt><dd id="serverSoftware" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              <div><dt class="text-gray-500 dark:text-primary-220">Hostname / Adresse</dt><dd id="serverHost" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              <div><dt class="text-gray-500 dark:text-primary-220">PHP-Version / SAPI</dt><dd id="phpVersionSapi" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              <div><dt class="text-gray-500 dark:text-primary-220">Server-Zeit / TZ</dt><dd id="serverTimeTz" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              <div><dt class="text-gray-500 dark:text-primary-220">Geladene php.ini</dt><dd id="loadedIni" class="font-medium text-gray-900 dark:text-primary-200 break-all">-</dd></div>
              <div><dt class="text-gray-500 dark:text-primary-220">Weitere ini-Dateien</dt><dd id="scannedIni" class="font-medium text-gray-900 dark:text-primary-200 break-all">-</dd></div>
              <div class="md:col-span-2"><dt class="text-gray-500 dark:text-primary-220">AllowOverride-Hinweis</dt><dd id="allowOverride" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
            </dl>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Performance & Ressourcen</h2>
              <dl class="grid grid-cols-1 gap-2 text-sm">
                <div><dt class="text-gray-500 dark:text-primary-220">CPU-Kerne</dt><dd id="cpuCores" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Load Average (1m / 5m / 15m)</dt><dd id="loadAvg" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">RAM (belegt / gesamt)</dt><dd id="memoryTotal" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Swap (belegt / gesamt)</dt><dd id="swapTotal" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Disk Root (frei / gesamt)</dt><dd id="diskRoot" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Disk Webapp (frei / gesamt)</dt><dd id="diskApp" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              </dl>
            </div>

            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">PHP-Limits & Laufzeit</h2>
              <dl class="grid grid-cols-1 gap-2 text-sm">
                <div><dt class="text-gray-500 dark:text-primary-220">memory_limit</dt><dd id="limitMemory" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">upload_max_filesize</dt><dd id="limitUpload" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">post_max_size</dt><dd id="limitPost" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">max_execution_time</dt><dd id="limitExecution" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">max_input_vars</dt><dd id="limitInputVars" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">PHP-RAM (aktuell / peak)</dt><dd id="phpRamRuntime" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
              </dl>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Module & Extensions</h2>
              <div class="text-sm mb-2 text-gray-600 dark:text-primary-210">Apache-Module:</div>
              <div id="apacheSummary" class="text-sm font-medium text-gray-900 dark:text-primary-200 mb-3">-</div>
              <div class="text-sm mb-2 text-gray-600 dark:text-primary-210">Fehlende kritische PHP-Extensions:</div>
              <ul id="missingCriticalExt" class="list-disc ps-5 text-sm text-gray-900 dark:text-primary-200 mb-3"></ul>
              <div class="text-sm mb-2 text-gray-600 dark:text-primary-210">Fehlende empfohlene PHP-Extensions:</div>
              <ul id="missingRecommendedExt" class="list-disc ps-5 text-sm text-gray-900 dark:text-primary-200"></ul>
            </div>

            <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Integrations-Checks</h2>
              <dl class="grid grid-cols-1 gap-2 text-sm">
                <div><dt class="text-gray-500 dark:text-primary-220">Datenbank</dt><dd id="dbStatus" class="font-medium text-gray-900 dark:text-primary-200">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Open Basedir</dt><dd id="openBasedir" class="font-medium text-gray-900 dark:text-primary-200 break-all">-</dd></div>
                <div><dt class="text-gray-500 dark:text-primary-220">Deaktivierte PHP-Funktionen</dt><dd id="disabledFunctions" class="font-medium text-gray-900 dark:text-primary-200 break-all">-</dd></div>
              </dl>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Pfad- & Dateirechte</h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-gray-500 dark:text-primary-220 border-b border-gray-200 dark:border-primary-120">
                    <th class="py-2 pe-3">Key</th>
                    <th class="py-2 pe-3">Pfad</th>
                    <th class="py-2 pe-3">Existiert</th>
                    <th class="py-2 pe-3">Lesbar</th>
                    <th class="py-2 pe-3">Schreibbar</th>
                  </tr>
                </thead>
                <tbody id="pathChecksBody" class="text-gray-900 dark:text-primary-200"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">.user.ini bearbeiten</h2>
              <span id="userIniPath" class="text-xs text-gray-500 dark:text-primary-220 break-all"></span>
            </div>
            <p class="text-sm text-gray-600 dark:text-primary-210 mb-3">Hier kannst du PHP-Einstellungen pro Verzeichnis anpassen (z. B. `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`).</p>
            <textarea id="userIniContent" class="w-full h-56 px-3 py-2 text-sm font-mono border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200"></textarea>
            <div class="mt-3 flex gap-2">
              <button id="saveUserIniBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-420 rounded-lg hover:bg-primary-440">.user.ini speichern</button>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div class="bg-white dark:bg-primary-100 rounded-lg shadow-sm border border-gray-200 dark:border-primary-120 p-6">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">.htaccess bearbeiten</h2>
              <span id="htaccessPath" class="text-xs text-gray-500 dark:text-primary-220 break-all"></span>
            </div>
            <p class="text-sm text-gray-600 dark:text-primary-210 mb-3">Volltext-Bearbeitung der Webapp-`.htaccess` (nur wenn schreibbar).</p>
            <textarea id="htaccessContent" class="w-full h-64 px-3 py-2 text-sm font-mono border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200"></textarea>
            <div class="mt-3 flex gap-2">
              <button id="saveHtaccessBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary-420 rounded-lg hover:bg-primary-440">.htaccess speichern</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function() {
  const apiUrl = '<?php echo BASE_URL; ?>admin/api/server-config.php';

  function formatBytes(value) {
    if (value === null || value === undefined || value < 0 || Number.isNaN(value)) return '-';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let num = Number(value);
    let idx = 0;
    while (num >= 1024 && idx < units.length - 1) {
      num /= 1024;
      idx += 1;
    }
    return num.toFixed(idx === 0 ? 0 : 1) + ' ' + units[idx];
  }

  function formatDuration(totalSeconds) {
    if (totalSeconds === null || totalSeconds === undefined) return '-';
    const seconds = Math.max(0, Number(totalSeconds));
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return days + 'd ' + hours + 'h ' + minutes + 'm';
  }

  function setList(id, items, emptyText) {
    const el = document.getElementById(id);
    if (!el) return;
    if (!Array.isArray(items) || items.length === 0) {
      el.innerHTML = '<li>' + (emptyText || '-') + '</li>';
      return;
    }
    el.innerHTML = items.map(function(item) { return '<li>' + item + '</li>'; }).join('');
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
  }

  function loadData() {
    fetch(apiUrl)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success) {
          if (typeof showToast === 'function') showToast(data.message || 'Fehler beim Laden', 'error');
          return;
        }
        setText('serverSoftware', data.server_software || '-');
        setText('serverHost', (data.server_name || '-') + ' / ' + (data.server_addr || '-'));
        setText('phpVersionSapi', (data.php_version || '-') + ' / ' + (data.php_sapi || '-'));
        setText('serverTimeTz', (data.server_time || '-') + ' / ' + (data.default_timezone || '-'));
        setText('loadedIni', data.loaded_php_ini || '(keine)');
        setText('scannedIni', data.scanned_php_ini || '(keine)');
        setText('allowOverride', data.allow_override_message || '-');
        setText('userIniPath', (data.user_ini_display_path || data.user_ini_path || '') + (data.user_ini_source ? ' [' + data.user_ini_source + ']' : ''));
        setText('htaccessPath', (data.htaccess_display_path || data.htaccess_path || '') + (data.htaccess_source ? ' [' + data.htaccess_source + ']' : ''));
        setText('uptimeText', formatDuration(data.uptime_seconds));

        const load = Array.isArray(data.loadavg) ? data.loadavg : [];
        const load1 = load.length ? Number(load[0]).toFixed(2) : '-';
        const load5 = load.length > 1 ? Number(load[1]).toFixed(2) : '-';
        const load15 = load.length > 2 ? Number(load[2]).toFixed(2) : '-';
        setText('load1Text', load1);
        setText('cpuCores', String(data.cpu_cores || '-'));
        setText('loadAvg', load1 + ' / ' + load5 + ' / ' + load15);

        const mem = data.memory || {};
        setText('memoryTotal', formatBytes(mem.used_bytes) + ' / ' + formatBytes(mem.total_bytes));
        setText('ramUsageText', formatBytes(mem.used_bytes));
        setText('swapTotal', formatBytes(mem.swap_used_bytes) + ' / ' + formatBytes(mem.swap_total_bytes));

        const disk = data.disk || {};
        setText('diskRoot', formatBytes(disk.root_free_bytes) + ' / ' + formatBytes(disk.root_total_bytes));
        setText('diskApp', formatBytes(disk.app_free_bytes) + ' / ' + formatBytes(disk.app_total_bytes));
        setText('diskFreeText', formatBytes(disk.app_free_bytes));

        const limits = data.php_limits || {};
        setText('limitMemory', (limits.memory_limit || '-') + ' (' + formatBytes(limits.memory_limit_bytes) + ')');
        setText('limitUpload', (limits.upload_max_filesize || '-') + ' (' + formatBytes(limits.upload_max_filesize_bytes) + ')');
        setText('limitPost', (limits.post_max_size || '-') + ' (' + formatBytes(limits.post_max_size_bytes) + ')');
        setText('limitExecution', limits.max_execution_time || '-');
        setText('limitInputVars', limits.max_input_vars || '-');

        const runtimeMem = data.memory_usage || {};
        setText('phpRamRuntime', formatBytes(runtimeMem.current_bytes) + ' / ' + formatBytes(runtimeMem.peak_bytes));

        const apacheModules = data.apache_modules || {};
        if (apacheModules.available) {
          const enabledCount = Array.isArray(apacheModules.enabled) ? apacheModules.enabled.length : 0;
          const missing = Array.isArray(apacheModules.missing_important) ? apacheModules.missing_important : [];
          setText('apacheSummary', 'Aktiv: ' + enabledCount + ' Module; Fehlend (wichtig): ' + (missing.length ? missing.join(', ') : 'keine'));
        } else {
          setText('apacheSummary', apacheModules.message || 'Keine Daten');
        }

        const ext = data.php_extensions || {};
        setList('missingCriticalExt', ext.missing_critical || [], 'Keine');
        setList('missingRecommendedExt', ext.missing_recommended || [], 'Keine');

        const db = data.db_health || {};
        if (db.ok) {
          setText('dbStatus', 'OK - ' + (db.database || '-') + ' (' + (db.version || '-') + ')');
        } else {
          setText('dbStatus', 'Fehler - ' + (db.error || 'unbekannt'));
        }

        setText('openBasedir', data.open_basedir || '(nicht gesetzt)');
        setText('disabledFunctions', Array.isArray(data.disable_functions) && data.disable_functions.length ? data.disable_functions.join(', ') : '(keine)');

        const pathChecksBody = document.getElementById('pathChecksBody');
        if (pathChecksBody) {
          const checks = data.path_checks || {};
          const rows = Object.keys(checks).map(function(key) {
            const row = checks[key] || {};
            function yesNo(v) { return v ? 'Ja' : 'Nein'; }
            return '<tr class="border-b border-gray-100 dark:border-primary-120">' +
              '<td class="py-2 pe-3">' + key + '</td>' +
              '<td class="py-2 pe-3 break-all">' + (row.path || '-') + '</td>' +
              '<td class="py-2 pe-3">' + yesNo(row.exists) + '</td>' +
              '<td class="py-2 pe-3">' + yesNo(row.readable) + '</td>' +
              '<td class="py-2 pe-3">' + yesNo(row.writable) + '</td>' +
              '</tr>';
          });
          pathChecksBody.innerHTML = rows.join('');
        }

        document.getElementById('userIniContent').value = data.user_ini_content || '';
        document.getElementById('htaccessContent').value = data.htaccess_content || '';

        const userIniBtn = document.getElementById('saveUserIniBtn');
        const htaccessBtn = document.getElementById('saveHtaccessBtn');
        userIniBtn.disabled = false;
        htaccessBtn.disabled = false;
        if (!data.user_ini_writable) {
          userIniBtn.title = 'Globaler Pfad ist voraussichtlich nicht schreibbar. Speichern kann trotzdem versucht werden.';
          if (typeof showToast === 'function') showToast('Hinweis: globale php.ini ist vermutlich nicht schreibbar', 'warning');
        } else {
          userIniBtn.title = '';
        }
        if (!data.htaccess_writable) {
          htaccessBtn.title = 'Globaler Pfad ist voraussichtlich nicht schreibbar. Speichern kann trotzdem versucht werden.';
          if (typeof showToast === 'function') showToast('Hinweis: globale .htaccess ist vermutlich nicht schreibbar', 'warning');
        } else {
          htaccessBtn.title = '';
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Laden der Server-Konfiguration', 'error');
      });
  }

  document.getElementById('saveUserIniBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_user_ini',
        scope: 'global',
        content: document.getElementById('userIniContent').value
      })
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (typeof showToast === 'function') showToast(data.message || (data.success ? 'Gespeichert' : 'Fehler'), data.success ? 'success' : 'error');
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern der .user.ini', 'error');
      })
      .finally(function() { btn.disabled = false; });
  });

  document.getElementById('saveHtaccessBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_htaccess',
        scope: 'global',
        content: document.getElementById('htaccessContent').value
      })
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (typeof showToast === 'function') showToast(data.message || (data.success ? 'Gespeichert' : 'Fehler'), data.success ? 'success' : 'error');
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern der .htaccess', 'error');
      })
      .finally(function() { btn.disabled = false; });
  });

  loadData();
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
