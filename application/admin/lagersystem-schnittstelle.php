<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = null;
$settings = ['lagersystem_user_id' => '', 'lagersystem_api_key_set' => false];
$users = [];

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

if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('lagersystem_api_key', 'lagersystem_user_id')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'lagersystem_user_id') {
            $settings['lagersystem_user_id'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'lagersystem_api_key') {
            $settings['lagersystem_api_key_set'] = $row['setting_value'] !== '' && $row['setting_value'] !== null;
        }
    }
    $stmt = $pdo->query("SELECT id, vorname, nachname, email, rolle FROM users WHERE status = 'aktiv' OR status IS NULL ORDER BY nachname, vorname");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lagersystem-Settings: " . $e->getMessage());
}

$basePath = rtrim(BASE_URL, '/');
$path = ($basePath === '' ? '/' : $basePath . '/') . 'orders/api/lagersystem-order.php';
$endpointUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
$endpointUrlEnc = htmlspecialchars($endpointUrl);

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
                <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
              </li>
              <li aria-current="page">
                <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Lagersystem-Schnittstelle</span>
              </li>
            </ol>
          </nav>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Lagersystem-Schnittstelle</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Bestellungen automatisch anlegen, wenn das Lagersystem Mindestbestände meldet. Anforderer: <strong>Lagersystem</strong>.</p>
        </div>

        <!-- Anleitung: So richten Sie es ein -->
        <div class="col-span-full mx-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">So richten Sie die Schnittstelle ein</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Folgen Sie den Schritten unten. Jeder Schritt ist eine Karte – von oben nach unten durcharbeiten.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Card 1 -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-primary-200 dark:border-primary-700 shadow-sm p-5 flex flex-col">
              <div class="flex items-center gap-3 mb-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-bold text-lg">1</span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Einstellungen ausfüllen</h3>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">
                <strong>Unten auf dieser Seite</strong> im Bereich „Einstellungen“: Wählen Sie im Dropdown einen <strong>Benutzer</strong> (z. B. „Lagersystem“ oder einen Sachbearbeiter). Dann auf <strong>„Generieren“</strong> klicken, um einen API-Schlüssel zu erzeugen, und danach auf <strong>„Einstellungen speichern“</strong>.
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Ohne diesen Schritt funktioniert die Schnittstelle nicht.</p>
            </div>
            <!-- Card 2 -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-primary-200 dark:border-primary-700 shadow-sm p-5 flex flex-col">
              <div class="flex items-center gap-3 mb-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-bold text-lg">2</span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">URL kopieren</h3>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">
                Die <strong>Endpoint-URL</strong> steht gleich unter diesen Karten. Auf <strong>„Kopieren“</strong> klicken. Diese Adresse brauchen Sie im Lagersystem – dort trägt man sie ein als „Ziel-URL“, „Webhook“ oder „API-Adresse“, je nach Software.
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Beispiel: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">https://ihre-domain.de/orders/api/lagersystem-order.php</code></p>
            </div>
            <!-- Card 3 -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-primary-200 dark:border-primary-700 shadow-sm p-5 flex flex-col">
              <div class="flex items-center gap-3 mb-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-bold text-lg">3</span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Im Lagersystem eintragen</h3>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">
                Im <strong>Lagersystem</strong> (der Software, die den Mindestbestand meldet): Die kopierte <strong>URL</strong> eintragen und den <strong>API-Schlüssel</strong> (den Sie in Schritt 1 generiert haben) an der vorgesehenen Stelle eintragen – z. B. „API-Key“, „Passwort“ oder „Token“. Das System muss bei Unterschreitung des Mindestbestands einen Aufruf an diese URL senden, mit mindestens einer <strong>Beschreibung</strong> (z. B. welche Artikel fehlen).
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Welche Felder das Lagersystem anbietet, steht in dessen Handbuch.</p>
            </div>
            <!-- Card 4 -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-primary-200 dark:border-primary-700 shadow-sm p-5 flex flex-col">
              <div class="flex items-center gap-3 mb-3">
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 font-bold text-lg">4</span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Testen</h3>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">
                Ganz unten auf der Seite: Auf <strong>„Test-Bestellung auslösen“</strong> klicken und Ihren API-Schlüssel eingeben. Wenn eine Bestellung erstellt wird, ist die Einrichtung korrekt. Anschließend können Sie im Lagersystem einen echten Test auslösen (z. B. Mindestbestand simulieren) und in der Bestellübersicht prüfen, ob eine neue Bestellung mit Anforderer <strong>„Lagersystem“</strong> erscheint.
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Bei Problemen: URL und API-Schlüssel nochmals prüfen.</p>
            </div>
          </div>
          <!-- Kurz-Checkliste -->
          <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Kurz-Checkliste – haben Sie alles?</h3>
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1 list-disc list-inside">
              <li>Ein Benutzer im Dropdown „Erstellt von“ ausgewählt und gespeichert</li>
              <li>Einen API-Schlüssel generiert und gespeichert (und notiert, falls Sie ihn im Lagersystem eintragen)</li>
              <li>Die Endpoint-URL kopiert und im Lagersystem als Ziel-URL eingetragen</li>
              <li>Im Lagersystem den gleichen API-Schlüssel eingetragen und gespeichert</li>
              <li>Test-Bestellung ausgelöst und in der Bestellübersicht geprüft</li>
            </ul>
          </div>
        </div>

        <!-- Endpoint-URL (prominent) -->
        <div class="col-span-full mx-4">
          <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg p-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Endpoint-URL</h2>
            <div class="flex flex-wrap items-center gap-2">
              <code id="endpointUrlDisplay" class="flex-1 min-w-0 bg-white dark:bg-gray-800 rounded px-3 py-2 text-sm break-all border border-gray-200 dark:border-gray-600"><?php echo $endpointUrlEnc; ?></code>
              <button type="button" id="btnCopyEndpoint" class="shrink-0 px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600">
                Kopieren
              </button>
            </div>
          </div>
        </div>

        <!-- Einstellungen (kompakt) -->
        <div class="col-span-full lg:col-span-5 mx-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">Einstellungen</h2>
            <form id="lagersystemForm" class="space-y-4">
              <div>
                <label for="lagersystem_user_id" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Benutzer „Erstellt von“</label>
                <select id="lagersystem_user_id" name="lagersystem_user_id" class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                  <option value="">— Bitte wählen (Pflicht) —</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo ($settings['lagersystem_user_id'] !== '' && (int)$settings['lagersystem_user_id'] === (int)$u['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars(trim(($u['nachname'] ?? '') . ', ' . ($u['vorname'] ?? '') . ' – ' . ($u['email'] ?? '') . ' (' . ($u['rolle'] ?? '') . ')')); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="lagersystem_api_key" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">API-Schlüssel</label>
                <div class="flex gap-2">
                  <input type="password" id="lagersystem_api_key" name="lagersystem_api_key" autocomplete="off"
                         class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                         placeholder="<?php echo $settings['lagersystem_api_key_set'] ? 'Leer lassen = unverändert' : 'Eingeben oder generieren'; ?>">
                  <button type="button" id="btnGenerateKey" class="shrink-0 px-3 py-2.5 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500">Generieren</button>
                </div>
              </div>
              <div class="pt-2">
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700">
                  Einstellungen speichern
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Übersicht Empfangsarten (Tabelle) -->
        <div class="col-span-full lg:col-span-7 mx-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">Empfangsarten (Übersicht)</h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                  <tr>
                    <th class="px-3 py-2 rounded-tl-lg">Methode</th>
                    <th class="px-3 py-2">Authentifizierung</th>
                    <th class="px-3 py-2 rounded-tr-lg">Daten</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-2 font-medium">GET</td>
                    <td class="px-3 py-2">Query <code class="text-xs">api_key=…</code> oder Header</td>
                    <td class="px-3 py-2">Query: <code class="text-xs">beschreibung</code>, <code class="text-xs">bestellnummer</code>, <code class="text-xs">company_id</code>, <code class="text-xs">customer_id</code></td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-2 font-medium">POST JSON</td>
                    <td class="px-3 py-2"><code class="text-xs">X-API-Key</code>, <code class="text-xs">Authorization: Bearer</code>, oder <code class="text-xs">api_key</code> im Body</td>
                    <td class="px-3 py-2">JSON-Body mit gleichen Feldern</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-2 font-medium">POST Form</td>
                    <td class="px-3 py-2">Query oder Header oder <code class="text-xs">api_key</code> im Formular</td>
                    <td class="px-3 py-2"><code class="text-xs">application/x-www-form-urlencoded</code></td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-2 font-medium">Basic Auth</td>
                    <td class="px-3 py-2"><code class="text-xs">Authorization: Basic</code> (Passwort = API-Key)</td>
                    <td class="px-3 py-2">Wie GET oder POST</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400"><strong>Pflichtfeld:</strong> <code>beschreibung</code>. Optional: bestellnummer, company_id, customer_id.</p>
          </div>
        </div>

        <!-- Empfangsarten im Detail (aufklappbar) -->
        <div class="col-span-full mx-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">Beispiele (zum Kopieren)</h2>
            <div class="space-y-2">
              <details class="group rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                <summary class="flex items-center justify-between cursor-pointer list-none px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-medium text-gray-900 dark:text-white">
                  <span>1. GET mit Query-Parametern</span>
                  <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Für Systeme, die nur GET unterstützen (z. B. einfache Skripte oder Links).</p>
                  <pre class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200 break-all">curl -G "<?php echo $endpointUrlEnc; ?>" \
  --data-urlencode "api_key=IHR_SCHLUESSEL" \
  --data-urlencode "beschreibung=Toner unter Mindestbestand"</pre>
                </div>
              </details>
              <details class="group rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                <summary class="flex items-center justify-between cursor-pointer list-none px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-medium text-gray-900 dark:text-white">
                  <span>2. POST mit JSON (Header X-API-Key)</span>
                  <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                  <pre class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200">curl -X POST "<?php echo $endpointUrlEnc; ?>" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: IHR_SCHLUESSEL" \
  -d '{"beschreibung":"Verbrauchsmaterial unter Mindestbestand: Toner, Papier"}'</pre>
                </div>
              </details>
              <details class="group rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                <summary class="flex items-center justify-between cursor-pointer list-none px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-medium text-gray-900 dark:text-white">
                  <span>3. POST mit Formular (application/x-www-form-urlencoded)</span>
                  <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Typisch für Webformulare oder ältere Lagersysteme.</p>
                  <pre class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200">curl -X POST "<?php echo $endpointUrlEnc; ?>" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "api_key=IHR_SCHLUESSEL" \
  -d "beschreibung=Nachbestellung Papier A4"</pre>
                </div>
              </details>
              <details class="group rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                <summary class="flex items-center justify-between cursor-pointer list-none px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-medium text-gray-900 dark:text-white">
                  <span>4. Authorization: Bearer</span>
                  <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                  <pre class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200">curl -X POST "<?php echo $endpointUrlEnc; ?>" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer IHR_SCHLUESSEL" \
  -d '{"beschreibung":"Mindestbestand erreicht"}'</pre>
                </div>
              </details>
              <details class="group rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                <summary class="flex items-center justify-between cursor-pointer list-none px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-medium text-gray-900 dark:text-white">
                  <span>5. HTTP Basic Auth</span>
                  <svg class="w-4 h-4 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Benutzername beliebig (z. B. <code>lagersystem</code>), Passwort = API-Schlüssel.</p>
                  <pre class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200">curl -X POST "<?php echo $endpointUrlEnc; ?>" \
  -u "lagersystem:IHR_SCHLUESSEL" \
  -H "Content-Type: application/json" \
  -d '{"beschreibung":"Bestellung aus Lagersystem"}'</pre>
                </div>
              </details>
            </div>
          </div>
        </div>

        <!-- Test -->
        <div class="col-span-full mx-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">Test</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Mit eigenen Testdaten eine Bestellung auslösen. So können Sie auch Notiz und optionale Felder prüfen.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="test_api_key" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">API-Schlüssel</label>
                <input type="password" id="test_api_key" autocomplete="off"
                       class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="API-Schlüssel eingeben">
              </div>
              <div>
                <label for="test_beschreibung" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Beschreibung (Pflicht)</label>
                <input type="text" id="test_beschreibung"
                       class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       value="Test-Bestellung von Admin (Lagersystem-Schnittstelle)">
              </div>
              <div>
                <label for="test_bestellnummer" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Bestellnummer (optional)</label>
                <input type="text" id="test_bestellnummer"
                       class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="z. B. TEST-20260220-001">
              </div>
              <div>
                <label for="test_company_id" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Company-ID (optional)</label>
                <input type="number" id="test_company_id" min="1" step="1"
                       class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="z. B. 12">
              </div>
              <div>
                <label for="test_customer_id" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Customer-ID (optional)</label>
                <input type="number" id="test_customer_id" min="1" step="1"
                       class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="z. B. 34">
              </div>
              <div class="md:col-span-2">
                <label for="test_notiz" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">Notiz (optional)</label>
                <textarea id="test_notiz" rows="3"
                          class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                          placeholder="Hier können Sie eine Notiz für die Testbestellung eintragen"></textarea>
              </div>
            </div>
            <div class="mt-4">
              <button type="button" id="btnTest" class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700">
                Test-Bestellung auslösen
              </button>
            </div>
            <p id="testResult" class="mt-3 text-sm hidden"></p>
          </div>
        </div>

        <!-- Live-Log -->
        <div class="col-span-full mx-4 mb-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between gap-2 mb-3 border-b border-gray-200 dark:border-gray-700 pb-3">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Schnittstellen-Log (Live)</h2>
              <button type="button" id="btnClearInterfaceLog" class="px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Log leeren
              </button>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Zeigt Aktionen und Antworten dieser Seite im aktuellen Browser-Tab.</p>
            <div id="interfaceLog" class="h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 p-3">
              <ul id="interfaceLogList" class="space-y-2 text-xs text-gray-700 dark:text-gray-300"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function() {
  const baseUrl = '<?php echo addslashes(BASE_URL); ?>';
  const endpointUrl = <?php echo json_encode($endpointUrl); ?>;
  const interfaceLogList = document.getElementById('interfaceLogList');

  function addInterfaceLog(message, type, details) {
    if (!interfaceLogList) return;
    const entryType = type || 'info';
    const now = new Date();
    const time = now.toLocaleTimeString('de-DE', { hour12: false });
    const li = document.createElement('li');
    const colorClass = entryType === 'error'
      ? 'text-red-700 dark:text-red-300'
      : (entryType === 'success' ? 'text-green-700 dark:text-green-300' : 'text-gray-700 dark:text-gray-300');

    li.className = 'rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2';
    li.innerHTML = '<div class="' + colorClass + '"><strong>[' + time + ']</strong> ' + message + '</div>';

    if (details !== undefined && details !== null && details !== '') {
      const pre = document.createElement('pre');
      pre.className = 'mt-1 whitespace-pre-wrap break-words text-[11px] text-gray-600 dark:text-gray-400';
      if (typeof details === 'string') {
        pre.textContent = details;
      } else {
        try {
          pre.textContent = JSON.stringify(details, null, 2);
        } catch (e) {
          pre.textContent = String(details);
        }
      }
      li.appendChild(pre);
    }

    interfaceLogList.prepend(li);
    while (interfaceLogList.children.length > 60) {
      interfaceLogList.removeChild(interfaceLogList.lastChild);
    }
  }

  function showToast(msg, type) {
    if (typeof window.showToast === 'function') window.showToast(msg, type);
    else alert(msg);
  }

  document.getElementById('btnCopyEndpoint').addEventListener('click', function() {
    function doCopy() {
      var ta = document.createElement('textarea');
      ta.value = endpointUrl;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        var ok = document.execCommand('copy');
        document.body.removeChild(ta);
        showToast(ok ? 'URL kopiert' : 'Kopieren fehlgeschlagen', ok ? 'success' : 'error');
        addInterfaceLog(ok ? 'Endpoint-URL kopiert.' : 'Kopieren der Endpoint-URL fehlgeschlagen.', ok ? 'success' : 'error');
      } catch (e) {
        document.body.removeChild(ta);
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(endpointUrl).then(function() {
            showToast('URL kopiert', 'success');
            addInterfaceLog('Endpoint-URL kopiert.', 'success');
          }).catch(function() {
            showToast('Kopieren fehlgeschlagen', 'error');
            addInterfaceLog('Kopieren der Endpoint-URL fehlgeschlagen.', 'error');
          });
        } else {
          showToast('Kopieren fehlgeschlagen', 'error');
          addInterfaceLog('Kopieren der Endpoint-URL fehlgeschlagen.', 'error');
        }
      }
    }
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
      navigator.clipboard.writeText(endpointUrl).then(function() {
        showToast('URL kopiert', 'success');
        addInterfaceLog('Endpoint-URL kopiert.', 'success');
      }).catch(doCopy);
    } else {
      doCopy();
    }
  });

  document.getElementById('btnGenerateKey').addEventListener('click', function() {
    const arr = new Uint8Array(32);
    crypto.getRandomValues(arr);
    const key = Array.from(arr, function(b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    document.getElementById('lagersystem_api_key').value = key;
    document.getElementById('lagersystem_api_key').type = 'text';
    setTimeout(function() { document.getElementById('lagersystem_api_key').type = 'password'; }, 3000);
    addInterfaceLog('Neuer API-Schlüssel wurde lokal generiert.', 'info');
  });

  document.getElementById('lagersystemForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const payload = {
      lagersystem_user_id: document.getElementById('lagersystem_user_id').value || null,
      lagersystem_api_key: document.getElementById('lagersystem_api_key').value || ''
    };
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Speichere…';
    addInterfaceLog('Speichere Lagersystem-Einstellungen …', 'info', payload);
    try {
      const r = await fetch(baseUrl + 'admin/api/lagersystem-settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await r.json();
      if (data.success) {
        showToast('Einstellungen gespeichert', 'success');
        addInterfaceLog('Lagersystem-Einstellungen gespeichert.', 'success', data);
        if (payload.lagersystem_api_key) {
          document.getElementById('lagersystem_api_key').value = '';
          document.getElementById('lagersystem_api_key').placeholder = 'Leer lassen = unverändert';
        }
      } else {
        showToast('Fehler: ' + (data.error || 'Unbekannt'), 'error');
        addInterfaceLog('Fehler beim Speichern der Einstellungen.', 'error', data);
      }
    } catch (err) {
      showToast('Netzwerkfehler', 'error');
      addInterfaceLog('Netzwerkfehler beim Speichern der Einstellungen.', 'error', err.message || String(err));
    }
    btn.disabled = false;
    btn.textContent = 'Einstellungen speichern';
  });

  document.getElementById('btnClearInterfaceLog').addEventListener('click', function() {
    if (!interfaceLogList) return;
    interfaceLogList.innerHTML = '';
    addInterfaceLog('Live-Log wurde geleert.', 'info');
  });

  document.getElementById('btnTest').addEventListener('click', async function() {
    const key = (document.getElementById('test_api_key').value || '').trim();
    const beschreibung = (document.getElementById('test_beschreibung').value || '').trim();
    const bestellnummer = (document.getElementById('test_bestellnummer').value || '').trim();
    const companyIdRaw = (document.getElementById('test_company_id').value || '').trim();
    const customerIdRaw = (document.getElementById('test_customer_id').value || '').trim();
    const notiz = (document.getElementById('test_notiz').value || '').trim();

    if (!key) {
      showToast('Bitte API-Schlüssel eingeben', 'error');
      addInterfaceLog('Testbestellung abgebrochen: API-Schlüssel fehlt.', 'error');
      return;
    }
    if (!beschreibung) {
      showToast('Bitte eine Beschreibung eingeben', 'error');
      addInterfaceLog('Testbestellung abgebrochen: Beschreibung fehlt.', 'error');
      return;
    }

    const payload = { beschreibung: beschreibung };
    if (bestellnummer) payload.bestellnummer = bestellnummer;
    if (companyIdRaw) payload.company_id = parseInt(companyIdRaw, 10);
    if (customerIdRaw) payload.customer_id = parseInt(customerIdRaw, 10);
    if (notiz) payload.notiz = notiz;

    const btn = document.getElementById('btnTest');
    const resultEl = document.getElementById('testResult');
    btn.disabled = true;
    resultEl.classList.add('hidden');
    addInterfaceLog('Sende Testbestellung an den Endpoint …', 'info', payload);
    try {
      const r = await fetch(endpointUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-Key': key },
        body: JSON.stringify(payload)
      });
      const responseText = await r.text();
      let data = null;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        data = { success: false, error: 'Ungültige API-Antwort', raw: responseText };
      }
      resultEl.classList.remove('hidden');
      resultEl.className = 'mt-3 text-sm ' + (data.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400');
      resultEl.textContent = data.success ? 'Bestellung erstellt: ID ' + data.order_id + ', ' + (data.bestellnummer || '') + '.' : 'Fehler: ' + (data.error || 'Unbekannt');
      addInterfaceLog(data.success ? 'Testbestellung erfolgreich erstellt.' : 'Testbestellung fehlgeschlagen.', data.success ? 'success' : 'error', data);
    } catch (err) {
      resultEl.classList.remove('hidden');
      resultEl.className = 'mt-3 text-sm text-red-600 dark:text-red-400';
      resultEl.textContent = 'Netzwerkfehler: ' + err.message;
      addInterfaceLog('Netzwerkfehler beim Senden der Testbestellung.', 'error', err.message || String(err));
    }
    btn.disabled = false;
  });

  addInterfaceLog('Lagersystem-Schnittstelle geladen.', 'info', { endpoint: endpointUrl });
})();
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
