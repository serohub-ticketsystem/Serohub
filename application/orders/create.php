<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/customers/helper/encryption.php';
requireLogin();

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id, customer_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
    $userCustomerId = $user['customer_id'] ?? null;
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Kunden dürfen die Bestellungsseite nicht sehen
if ($userRole === 'Kunde') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Firmen und Kunden für Dropdown laden
$companies = [];
$customers = [];

if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT id, name, email, company_id FROM customers WHERE status = 'aktiv' ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (($userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && $userCompanyId) {
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCompanyId]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE (company_id = ? OR company_id IS NULL) AND status = 'aktiv' ORDER BY name");
    $stmt->execute([$userCompanyId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Kunde' && $userCustomerId) {
    // Kunde kann nur für sich selbst erstellen
    $stmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCustomerId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($customers as &$c) { decrypt_customer_row($c); }
unset($c);

// Projekte für Admin/Techniker laden (nur aktive, gefiltert nach Firma falls Nav-Filter aktiv)
$projects = [];
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] !== '' && $_SESSION['selected_company_id'] !== null ? (int)$_SESSION['selected_company_id'] : null;
    if ($selectedCompanyId) {
        $stmt = $pdo->prepare("SELECT id, bezeichnung FROM projects WHERE company_id = ? AND status NOT IN ('Archiviert') ORDER BY bezeichnung");
        $stmt->execute([$selectedCompanyId]);
    } else {
        $stmt = $pdo->query("SELECT id, bezeichnung FROM projects WHERE status NOT IN ('Archiviert') ORDER BY bezeichnung");
    }
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
    <main>
        <div class="pr-4">
            <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
                <div class="col-span-full mx-4 mt-4">
                    <nav class="mb-4 flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                            <li class="inline-flex items-center">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-primary-210 dark:hover:text-white">
                                    <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                                    </svg>
                                    Startseite
                                </a>
                            </li>
                            <li class="inline-flex items-center">
                                <a href="<?php echo BASE_URL; ?>orders/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-primary-210 dark:hover:text-white">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                    </svg>
                                    <span class="ms-1 md:ms-2">Bestellungen</span>
                                </a>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                    </svg>
                                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-primary-210 md:ms-2">Neue Bestellung</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-primary-200">Neue Bestellung</h1>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <form id="orderForm" class="flex flex-col bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden">
                            <div class="p-4 md:p-6 border-b border-gray-200 dark:border-primary-120">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-primary-200">Bestellung anlegen</h2>
                                <p class="text-sm text-gray-500 dark:text-primary-240 mt-0.5">Manuelle Bestellung ohne Ticket</p>
                            </div>
                            <div class="p-4 md:p-6 space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Beschreibung -->
                                    <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-gray-50/50 dark:bg-primary-50/50 p-4">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Beschreibung</h3>
                                        <div class="space-y-3">
                                            <div>
                                                <label for="beschreibung" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Beschreibung *</label>
                                                <textarea id="beschreibung" name="beschreibung" rows="4" required
                                                          placeholder="Beschreibung der Bestellung..."
                                                          class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent"></textarea>
                                            </div>
                                            <div>
                                                <label for="bestellnummer" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Bestellnummer</label>
                                                <input type="text" id="bestellnummer" name="bestellnummer"
                                                       placeholder="Leer lassen für automatische Vergabe"
                                                       class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Sendungsverfolgung -->
                                    <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-gray-50/50 dark:bg-primary-50/50 p-4">
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Sendungsverfolgung</h3>
                                        <div class="space-y-3">
                                            <div>
                                                <label for="tracking_nummer" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Tracking-Nummer</label>
                                                <input type="text" id="tracking_nummer" name="tracking_nummer"
                                                       placeholder="z. B. 1234567890"
                                                       class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                            </div>
                                            <div>
                                                <label for="tracking_link" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Tracking-Link</label>
                                                <input type="url" id="tracking_link" name="tracking_link"
                                                       placeholder="https://..."
                                                       class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Status & Auftraggeber -->
                                <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-gray-50/50 dark:bg-primary-50/50 p-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Status & Auftraggeber</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label for="status" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Status *</label>
                                            <select id="status" name="status" required
                                                    class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                                <option value="Neu" selected>Neu</option>
                                                <option value="Bestellt">Bestellt</option>
                                                <option value="Unterwegs">Unterwegs</option>
                                                <option value="Beim Kunden">Beim Kunden</option>
                                                <option value="Im Lager">Im Lager</option>
                                                <option value="Angekommen">Angekommen</option>
                                            </select>
                                        </div>
                                        <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                                        <div>
                                            <label for="company_id" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Firma</label>
                                            <select id="company_id" name="company_id"
                                                    class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                                <option value="">Keine Firma</option>
                                                <?php foreach ($companies as $company): ?>
                                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && !empty($customers)): ?>
                                        <div>
                                            <label for="customer_id" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Kunde</label>
                                            <select id="customer_id" name="customer_id"
                                                    class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                                <option value="">Kein Kunde</option>
                                                <?php foreach ($customers as $customer): ?>
                                                <option value="<?= $customer['id'] ?>" <?= ($userRole === 'Kunde' && $customer['id'] == $userCustomerId) ? 'selected' : '' ?>><?= htmlspecialchars($customer['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php elseif ($userRole === 'Kunde' && $userCustomerId): ?>
                                        <input type="hidden" id="customer_id" name="customer_id" value="<?= $userCustomerId ?>">
                                        <?php endif; ?>
                                        <?php if (($userRole === 'Admin' || $userRole === 'Techniker') && !empty($projects)): ?>
                                        <div>
                                            <label for="project_id" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Projekt</label>
                                            <select id="project_id" name="project_id"
                                                    class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent">
                                                <option value="">Kein Projekt</option>
                                                <?php foreach ($projects as $proj): ?>
                                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['bezeichnung']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4">
                                        <label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                                            <input type="checkbox" id="garantie" name="garantie" value="1"
                                                   class="w-4 h-4 rounded border-gray-300 dark:border-primary-320 text-primaryLight-420 dark:text-primary-420 focus:ring-primaryLight-420 dark:focus:ring-primary-420">
                                            <span class="text-sm text-gray-700 dark:text-primary-210">Bestellung läuft über <span class="font-medium">Garantie</span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-primary-120">
                                    <a href="<?php echo BASE_URL; ?>orders/"
                                       class="px-4 py-2 text-sm font-medium rounded-base border border-gray-300 dark:border-primary-120 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">
                                        Abbrechen
                                    </a>
                                    <button type="submit"
                                            class="px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primaryLight-420 dark:focus:ring-primary-420">
                                        Bestellung erstellen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const ordersApiUrl = '<?php echo BASE_URL; ?>orders/api/orders.php';

document.getElementById('orderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        bestellnummer: document.getElementById('bestellnummer').value.trim() || null,
        beschreibung: document.getElementById('beschreibung').value.trim(),
        tracking_nummer: document.getElementById('tracking_nummer').value.trim() || null,
        tracking_link: document.getElementById('tracking_link').value.trim() || null,
        status: document.getElementById('status').value,
        company_id: document.getElementById('company_id') ? (document.getElementById('company_id').value || null) : null,
        customer_id: document.getElementById('customer_id') ? (document.getElementById('customer_id').value || null) : null,
        project_id: document.getElementById('project_id') ? (document.getElementById('project_id').value || null) : null,
        garantie: document.getElementById('garantie') ? document.getElementById('garantie').checked : false
    };
    
    fetch(ordersApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Bestellung erfolgreich erstellt', 'success');
            }
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>orders/';
            }, 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Erstellen der Bestellung', 'error');
        } else {
            alert('Fehler beim Erstellen der Bestellung');
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
