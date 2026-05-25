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
    $stmt = $pdo->prepare('SELECT id, rolle FROM users WHERE id = ? LIMIT 1');
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
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Web-Push (VAPID)</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Web-Push (VAPID)</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Schlüssel für Smartphone-Benachrichtigungen – ohne Shell auf dem Server</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-span-full mx-4">
          <div id="pushConfigBanner" class="hidden mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100"></div>
          <div id="pushDbStaleBanner" class="hidden mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"></div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6 max-w-3xl">
            <h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Kontakt (Subject)</h2>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
              Vorgabe der Spezifikation: eine Kontakt-URL, z.&nbsp;B. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">mailto:admin@ihre-domain.de</code> oder eine <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">https://</code>-URL Ihrer Organisation.
            </p>
            <label for="vapidSubject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
            <input type="text" id="vapidSubject" autocomplete="off" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="mailto:admin@ihre-domain.de" />

            <h2 class="mt-8 mb-2 text-lg font-semibold text-gray-900 dark:text-white">Öffentlicher Schlüssel</h2>
            <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">Wird an Browser übermittelt (kein Geheimnis). Der private Schlüssel wird nur serverseitig in der Datenbank gespeichert und nicht angezeigt.</p>
            <textarea id="vapidPublicKey" readonly rows="3" class="w-full font-mono text-xs rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"></textarea>

            <div class="mt-6 flex flex-wrap gap-3">
              <button type="button" id="btnGenerateKeys" class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 disabled:opacity-50">
                Schlüsselpaar erzeugen und speichern
              </button>
              <button type="button" id="btnSaveSubject" class="px-4 py-2.5 text-sm font-medium text-gray-800 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 disabled:opacity-50">
                Nur Subject speichern
              </button>
              <button type="button" id="btnClearDb" class="px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 dark:bg-red-900/30 dark:text-red-200 dark:hover:bg-red-900/50 disabled:opacity-50">
                Datenbank-Einträge entfernen
              </button>
            </div>
            <p id="pushStatusLine" class="mt-4 text-sm text-gray-600 dark:text-gray-400" role="status"></p>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var apiUrl = '<?php echo BASE_URL; ?>admin/api/push-settings.php';
    var subjectEl = document.getElementById('vapidSubject');
    var publicEl = document.getElementById('vapidPublicKey');
    var bannerConfig = document.getElementById('pushConfigBanner');
    var bannerDb = document.getElementById('pushDbStaleBanner');
    var btnGen = document.getElementById('btnGenerateKeys');
    var btnSaveSub = document.getElementById('btnSaveSubject');
    var btnClear = document.getElementById('btnClearDb');
    var statusEl = document.getElementById('pushStatusLine');

    function setUiState(d) {
        var cfg = d.config_file_active;
        var canManage = d.can_manage_in_ui;
        var dbKeys = d.database_has_keys;

        subjectEl.value = d.subject || '';
        publicEl.value = d.public_key || '';

        bannerConfig.classList.toggle('hidden', !cfg);
        if (cfg) {
            bannerConfig.textContent = 'Es sind VAPID-Werte in der Datei assets/config.php gesetzt – diese haben Vorrang vor der Datenbank. Ändern Sie Push dort oder entfernen Sie die drei WEBPUSH_-Zeilen, um die Verwaltung hier zu nutzen.';
        }

        var showStale = cfg && dbKeys;
        bannerDb.classList.toggle('hidden', !showStale);
        if (showStale) {
            bannerDb.textContent = 'Hinweis: In der Datenbank liegen noch VAPID-Daten. Sie werden derzeit nicht genutzt. Sie können sie unten entfernen, um aufzuräumen.';
        }

        subjectEl.readOnly = !!cfg;
        subjectEl.classList.toggle('opacity-70', !!cfg);

        btnGen.disabled = !canManage;
        btnSaveSub.disabled = !canManage || !dbKeys;
        btnClear.disabled = !dbKeys;

        var parts = [];
        if (d.configured) {
            parts.push('Status: aktiv (Quelle: ' + (d.active_source === 'config' ? 'Konfigurationsdatei' : 'Datenbank') + ').');
        } else {
            parts.push('Status: nicht konfiguriert – Push ist deaktiviert, bis ein vollständiges Schlüsselpaar vorliegt.');
        }
        statusEl.textContent = parts.join(' ');
    }

    async function load() {
        try {
            var r = await fetch(apiUrl, { credentials: 'same-origin' });
            var d = await r.json();
            if (!d.success) throw new Error(d.message || 'Laden fehlgeschlagen');
            setUiState(d);
        } catch (e) {
            statusEl.textContent = 'Fehler beim Laden: ' + (e.message || '');
        }
    }

    btnGen.addEventListener('click', async function() {
        var sub = (subjectEl.value || '').trim();
        if (!sub || (sub.indexOf('mailto:') !== 0 && sub.indexOf('https://') !== 0)) {
            if (typeof showToast === 'function') showToast('Subject muss mit mailto: oder https:// beginnen.', 'error');
            else alert('Subject muss mit mailto: oder https:// beginnen.');
            return;
        }
        btnGen.disabled = true;
        try {
            var r = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'generate_keys', subject: sub })
            });
            var d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.message || 'Fehler');
            if (d.public_key) publicEl.value = d.public_key;
            if (typeof showToast === 'function') showToast(d.message || 'Gespeichert', 'success');
            else alert(d.message || 'Gespeichert');
            await load();
        } catch (e) {
            if (typeof showToast === 'function') showToast(e.message || 'Fehler', 'error');
            else alert(e.message || 'Fehler');
        } finally {
            btnGen.disabled = false;
        }
    });

    btnSaveSub.addEventListener('click', async function() {
        var sub = (subjectEl.value || '').trim();
        if (!sub || (sub.indexOf('mailto:') !== 0 && sub.indexOf('https://') !== 0)) {
            if (typeof showToast === 'function') showToast('Subject muss mit mailto: oder https:// beginnen.', 'error');
            else alert('Subject muss mit mailto: oder https:// beginnen.');
            return;
        }
        btnSaveSub.disabled = true;
        try {
            var r = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'save_subject', subject: sub })
            });
            var d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.message || 'Fehler');
            if (typeof showToast === 'function') showToast(d.message || 'Gespeichert', 'success');
            else alert(d.message || 'Gespeichert');
            await load();
        } catch (e) {
            if (typeof showToast === 'function') showToast(e.message || 'Fehler', 'error');
            else alert(e.message || 'Fehler');
        } finally {
            btnSaveSub.disabled = false;
        }
    });

    btnClear.addEventListener('click', async function() {
        if (!confirm('VAPID-Einträge in der Datenbank wirklich löschen? Bereits angemeldete Geräte können Push verlieren, bis Nutzer erneut aktivieren.')) return;
        btnClear.disabled = true;
        try {
            var r = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'clear_database' })
            });
            var d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.message || 'Fehler');
            if (typeof showToast === 'function') showToast(d.message || 'OK', 'success');
            else alert(d.message || 'OK');
            await load();
        } catch (e) {
            if (typeof showToast === 'function') showToast(e.message || 'Fehler', 'error');
            else alert(e.message || 'Fehler');
        } finally {
            btnClear.disabled = false;
        }
    });

    load();
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
