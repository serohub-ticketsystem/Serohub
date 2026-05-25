<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

if (!isset($_GET['id']) || !$_GET['id']) {
    header('Location: ' . BASE_URL . 'orders/');
    exit;
}

$orderId = (int)$_GET['id'];

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

// Bestellung laden
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . BASE_URL . 'orders/');
    exit;
}

// Berechtigung prüfen
$hasPermission = false;
if ($userRole === 'Admin' || $userRole === 'Techniker') {
    $hasPermission = true;
} elseif ($userRole === 'Firmen-Admin' && $order['company_id'] == $userCompanyId) {
    $hasPermission = true;
}

if (!$hasPermission) {
    header('Location: ' . BASE_URL . 'orders/');
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
    $stmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE id = ? AND status = 'aktiv'");
    $stmt->execute([$userCustomerId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
                    <div class="mb-4 sm:mb-0">
                        <nav class="mb-4 flex" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                                <li class="inline-flex items-center">
                                    <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
                                        <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                                        </svg>
                                        Startseite
                                    </a>
                                </li>
                                <li class="inline-flex items-center">
                                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                    </svg>
                                    <a href="<?php echo BASE_URL; ?>orders/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 md:ms-2 dark:text-gray-400 dark:hover:text-white">Bestellungen</a>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Bestellung bearbeiten</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Bestellung bearbeiten</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bearbeiten Sie die Bestellung</p>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <form id="orderForm" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                            <input type="hidden" id="order_id" value="<?= $orderId ?>">
                            
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bestellnummer</label>
                                <input type="text" id="bestellnummer" name="bestellnummer" 
                                       value="<?= htmlspecialchars($order['bestellnummer'] ?? '') ?>"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung *</label>
                                <textarea id="beschreibung" name="beschreibung" rows="4" required
                                          class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($order['beschreibung'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tracking-Nummer</label>
                                <input type="text" id="tracking_nummer" name="tracking_nummer" 
                                       value="<?= htmlspecialchars($order['tracking_nummer'] ?? '') ?>"
                                       placeholder="z.B. 1234567890"
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional: Tracking-Nummer der Sendung</p>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tracking-Link</label>
                                <input type="url" id="tracking_link" name="tracking_link" 
                                       value="<?= htmlspecialchars($order['tracking_link'] ?? '') ?>"
                                       placeholder="https://..."
                                       class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional: Link zur Sendungsverfolgung</p>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status *</label>
                                <select id="status" name="status" required
                                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="Neu" <?= (in_array($order['status'] ?? '', ['Neu', 'Offen'], true)) ? 'selected' : '' ?>>Neu</option>
                                    <option value="Bestellt" <?= ($order['status'] === 'Bestellt') ? 'selected' : '' ?>>Bestellt</option>
                                    <option value="Unterwegs" <?= ($order['status'] === 'Unterwegs') ? 'selected' : '' ?>>Unterwegs</option>
                                    <option value="Beim Kunden" <?= ($order['status'] === 'Beim Kunden') ? 'selected' : '' ?>>Beim Kunden</option>
                                    <option value="Im Lager" <?= ($order['status'] === 'Im Lager') ? 'selected' : '' ?>>Im Lager</option>
                                    <option value="Angekommen" <?= ($order['status'] === 'Angekommen') ? 'selected' : '' ?>>Angekommen</option>
                                </select>
                            </div>

                            <?php if ($userRole === 'Admin' || $userRole === 'Techniker'): ?>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Firma</label>
                                <select id="company_id" name="company_id"
                                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Keine Firma</option>
                                    <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>" <?= ($order['company_id'] == $company['id']) ? 'selected' : '' ?>><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if (($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin' || $userRole === 'Firmen-User') && !empty($customers)): ?>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kunde</label>
                                <select id="customer_id" name="customer_id"
                                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Kein Kunde</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>" <?= ($order['customer_id'] == $customer['id']) ? 'selected' : '' ?>><?= htmlspecialchars($customer['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php elseif ($userRole === 'Kunde' && $userCustomerId): ?>
                            <input type="hidden" id="customer_id" name="customer_id" value="<?= $userCustomerId ?>">
                            <?php endif; ?>

                            <div class="mb-6">
                                <label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" id="garantie" name="garantie" value="1"
                                           <?= !empty($order['garantie']) ? 'checked' : '' ?>
                                           class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Bestellung läuft über <span class="font-medium">Garantie</span></span>
                                </label>
                            </div>

                            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <a href="<?php echo BASE_URL; ?>orders/" 
                                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                                    Abbrechen
                                </a>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-primary-900 rounded-lg hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
                                    Änderungen speichern
                                </button>
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
    
    const orderId = document.getElementById('order_id').value;
    const formData = {
        id: orderId,
        bestellnummer: document.getElementById('bestellnummer').value.trim(),
        beschreibung: document.getElementById('beschreibung').value.trim(),
        tracking_nummer: document.getElementById('tracking_nummer').value.trim() || null,
        tracking_link: document.getElementById('tracking_link').value.trim() || null,
        status: document.getElementById('status').value,
        company_id: document.getElementById('company_id') ? (document.getElementById('company_id').value || null) : null,
        customer_id: document.getElementById('customer_id') ? (document.getElementById('customer_id').value || null) : null,
        garantie: document.getElementById('garantie') ? document.getElementById('garantie').checked : false
    };
    
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Bestellung erfolgreich aktualisiert', 'success');
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
            showToast('Fehler beim Aktualisieren der Bestellung', 'error');
        } else {
            alert('Fehler beim Aktualisieren der Bestellung');
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
