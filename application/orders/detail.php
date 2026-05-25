<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: ' . BASE_URL . 'orders/');
    exit;
}

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

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="relative h-full w-full overflow-x-hidden bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0">
  <main>
    <div class="pr-4">
      <div class="grid grid-cols-12 gap-4 bg-gray-50 dark:bg-primary-50">
                <div class="col-span-full mx-4 mt-4 items-center justify-between sm:flex">
                    <div class="mb-4 sm:mb-0 flex-1">
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
                                        <svg class="me-2.5 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        Bestellungen
                                    </a>
                                </li>
                                <li aria-current="page">
                                    <div class="flex items-center">
                                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span class="ms-1 text-sm font-medium text-gray-500 dark:text-primary-210 md:ms-2">Bestelldetails</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <div class="flex items-center gap-2">
                            <h1 id="orderTitle" class="text-3xl font-bold text-gray-900 dark:text-primary-200">Bestelldetails</h1>
                        </div>
                    </div>
                </div>
                <div class="relative col-span-full">
                    <div class="px-4">
                        <div id="orderContent" class="flex flex-col bg-white dark:bg-primary-100 rounded-base shadow-card border border-gray-200 dark:border-primary-120 overflow-hidden min-h-[400px]">
                            <div class="flex-1 flex items-center justify-center p-8 min-h-[400px]">
                                <div class="text-center">
                                    <svg aria-hidden="true" class="mx-auto w-10 h-10 text-gray-400 dark:text-primary-210 animate-spin" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-primary-210">Lade Bestellung...</p>
                                    <span class="sr-only">Laden</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
      </div>
    </div>
  </main>
</div>

<script>
const orderId = <?php echo $orderId; ?>;
var baseUrl = '<?php echo addslashes(BASE_URL); ?>';
if (!baseUrl) baseUrl = '/';
if (baseUrl.slice(-1) !== '/') baseUrl += '/';
const ordersApiUrl = '<?php echo BASE_URL; ?>orders/api/orders.php';
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';
const logsApiUrl = '<?php echo BASE_URL; ?>logs/api/logs.php';
const currentUserRole = '<?php echo addslashes($userRole); ?>';
const currentUserId = <?php echo (int)$userId; ?>;
const userCompanyIdJs = <?php echo $userCompanyId !== null && $userCompanyId !== '' ? (int)$userCompanyId : 'null'; ?>;
const serviceViewBase = baseUrl + 'tickets/view.php?id=';
const devicesViewBase = baseUrl + 'devices/detail.php?id=';
const customersViewBase = baseUrl + 'customers/detail.php?id=';
const companiesViewBase = baseUrl + 'companies/detail.php?id=';
const projectsViewBase = baseUrl + 'projects/view.php?id=';
const inventoryDetailBase = baseUrl + 'inventory/detail.php?id=';

function getStatusBadge(status) {
    const neuHtml = '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Neu</span>';
    const badges = {
        'Neu': neuHtml,
        'Offen': neuHtml,
        'Bestellt': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Bestellt</span>',
        'Unterwegs': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Unterwegs</span>',
        'Beim Kunden': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">Beim Kunden</span>',
        'Im Lager': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Im Lager</span>',
        'Angekommen': '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Angekommen</span>'
    };
    const s = status === 'Offen' ? 'Neu' : status;
    return badges[s] || neuHtml;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadOrder() {
    fetch(ordersApiUrl + '?id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.order) {
                displayOrder(data.order);
            } else {
                showError('Bestellung nicht gefunden');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Bestellung');
        });
}

function getStatusIcon(status) {
    const neuOpenIcon = `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m8-2h3m-3 3h3m-4 3v6m4-3H8M19 4v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1ZM8 12v6h8v-6H8Z" />
        </svg>`;
    const icons = {
        'Neu': neuOpenIcon,
        'Offen': neuOpenIcon,
        'Bestellt': `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 14h2m3 0h5M3 7v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1Z" />
        </svg>`,
        'Unterwegs': `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h6l2 4m-8-4v8m0-8V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v9h2m8 0H9m4 0h2m4 0h2v-4m0 0h-5m3.5 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm-10 0a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" />
        </svg>`,
        'Beim Kunden': `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M5.535 7.677c.313-.98.687-2.023.926-2.677H17.46c.253.63.646 1.64.977 2.61.166.487.312.953.416 1.347.11.42.148.675.148.779 0 .18-.032.355-.09.515-.06.161-.144.3-.243.412-.1.111-.21.192-.324.245a.809.809 0 0 1-.686 0 1.004 1.004 0 0 1-.324-.245c-.1-.112-.183-.25-.242-.412a1.473 1.473 0 0 1-.091-.515 1 1 0 1 0-2 0 1.4 1.4 0 0 1-.333.927.896.896 0 0 1-.667.323.896.896 0 0 1-.667-.323A1.401 1.401 0 0 1 13 9.736a1 1 0 1 0-2 0 1.4 1.4 0 0 1-.333.927.896.896 0 0 1-.667.323.896.896 0 0 1-.667-.323A1.4 1.4 0 0 1 9 9.74v-.008a1 1 0 0 0-2 .003v.008a1.504 1.504 0 0 1-.18.712 1.22 1.22 0 0 1-.146.209l-.007.007a1.01 1.01 0 0 1-.325.248.82.82 0 0 1-.316.08.973.973 0 0 1-.563-.256 1.224 1.224 0 0 1-.102-.103A1.518 1.518 0 0 1 5 9.724v-.006a2.543 2.543 0 0 1 .029-.207c.024-.132.06-.296.11-.49.098-.385.237-.85.395-1.344ZM4 12.112a3.521 3.521 0 0 1-1-2.376c0-.349.098-.8.202-1.208.112-.441.264-.95.428-1.46.327-1.024.715-2.104.958-2.767A1.985 1.985 0 0 1 6.456 3h11.01c.803 0 1.539.481 1.844 1.243.258.641.67 1.697 1.019 2.72a22.3 22.3 0 0 1 .457 1.487c.114.433.214.903.214 1.286 0 .412-.072.821-.214 1.207A3.288 3.288 0 0 1 20 12.16V19a2 2 0 0 1-2 2h-6a1 1 0 0 1-1-1v-4H8v4a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2v-6.888ZM13 15a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-2Z" clip-rule="evenodd" />
        </svg>`,
        'Im Lager': `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
        </svg>`,
        'Angekommen': `<svg class="h-8 w-8 lg:mx-auto" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 6 2 2 4-4m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z" />
        </svg>`
    };
    const s = status === 'Offen' ? 'Neu' : status;
    return icons[s] || neuOpenIcon;
}

function getStatusLabel(status) {
    const labels = {
        'Neu': 'Bestellung aufgegeben',
        'Offen': 'Bestellung aufgegeben',
        'Bestellt': 'Bestellt',
        'Unterwegs': 'Unterwegs',
        'Beim Kunden': 'Beim Kunden',
        'Im Lager': 'Im Lager',
        'Angekommen': 'Zugestellt'
    };
    const s = status === 'Offen' ? 'Neu' : status;
    return labels[s] || status;
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = date.getDate().toString().padStart(2, '0');
    const month = date.toLocaleDateString('de-DE', { month: 'short' });
    const year = date.getFullYear();
    const time = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    return `${day} ${month} ${year}: ${time}`;
}

function formatDateLong(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric'
    });
}

function getDeviceTypLabel(typ) {
    const labels = { 'drucker': 'Drucker', 'computer': 'Computer', 'netzwerk': 'Netzwerk', 'smartphone': 'Smartphone', 'monitor': 'Monitor', 'divers': 'Divers' };
    return typ ? (labels[typ] || typ) : '';
}

function getAvailabilityBadge(isInStockNow) {
    if (isInStockNow) {
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/35 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800">Im Lager vorhanden</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-200 border border-slate-200 dark:border-slate-700">Nicht im Lager</span>';
}

function getWarehouseSourceBadge(isFromWarehouse) {
    if (isFromWarehouse) {
        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/35 dark:text-blue-200 border border-blue-200 dark:border-blue-800">Aus dem Lager bestellt</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/35 dark:text-amber-200 border border-amber-200 dark:border-amber-800">Extern bestellt</span>';
}

function displayOrder(order) {
    const orderContent = document.getElementById('orderContent');
    const orderTitle = document.getElementById('orderTitle');
    
    // Beschreibung für Titel und Cards (Prefix „Bestellung aus …“; technischen Marker nicht anzeigen)
    const beschreibungRaw = order.beschreibung ? String(order.beschreibung).trim() : '';
    let beschreibungText = beschreibungRaw.replace(/^Bestellung aus (Serviceauftrag|Ticket) #\d+:\s*/i, '').trim();
    beschreibungText = beschreibungText.replace(/\s*\[inventar_consumable_id=\d+\]\s*/g, '').trim();
    const beschreibungTitleLine = (function() {
        if (!beschreibungText) return '';
        const lines = beschreibungText.split(/\r?\n/).map(function(l) { return l.trim(); }).filter(Boolean);
        return lines.length ? lines[0] : beschreibungText;
    })();
    const notizenStr = order.notizen ? String(order.notizen) : '';
    const textForInvMarker = notizenStr + '\n' + beschreibungRaw;
    const invConsumableMatch = textForInvMarker.match(/\[inventar_consumable_id=(\d+)\]/);
    const invConsumableId = invConsumableMatch ? invConsumableMatch[1] : null;
    const manualLagerNotiz = notizenStr.indexOf('Diese Bestellung wurde manuell über das Lager erstellt') !== -1;
    const fromWarehouseByOrderType = String(order.bestellung_durch || '').trim().toLowerCase() === 'lagersystem';
    const isFromWarehouse = !!(invConsumableId || manualLagerNotiz || fromWarehouseByOrderType);
    const isInStockNow = String(order.status || '').trim() === 'Im Lager';
    const lagerHinweis = isInStockNow
        ? 'Der Artikel liegt aktuell im Lager.'
        : (isFromWarehouse ? 'Der Artikel wurde aus einem Lagerbedarf ausgelöst.' : 'Die Bestellung wurde nicht aus dem Lager ausgelöst.');
    const linkArtikelLager = invConsumableId
        ? '<a href="' + inventoryDetailBase + invConsumableId + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">Zum Artikel im Lager</a>'
        : '';
    let orderSourceLineHtml = '';
    if (order.ticket_id) {
        orderSourceLineHtml = '<p class="mt-1.5 text-xs text-gray-500 dark:text-primary-240">Aus <a href="' + serviceViewBase + order.ticket_id + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">Ticket #' + escapeHtml(order.ticket_nummer || order.ticket_id) + '</a></p>';
    } else if (manualLagerNotiz) {
        orderSourceLineHtml = '<p class="mt-1.5 text-xs text-gray-500 dark:text-primary-240">Manuelle Nachbestellung (Lager)' + (linkArtikelLager ? ' · ' + linkArtikelLager : '') + '</p>';
    } else if (invConsumableId) {
        const firstLineRaw = (beschreibungRaw.split(/\r?\n/)[0] || '').trim();
        const autoLagerTitle = /·\s*Lager\s*$/.test(firstLineRaw);
        const autoByDesc = beschreibungRaw.indexOf('Mindestbestand:') === 0
            || beschreibungRaw.indexOf('Automatische Nachbestellung') === 0
            || autoLagerTitle;
        if (autoByDesc) {
            orderSourceLineHtml = '<p class="mt-1.5 text-xs text-gray-500 dark:text-primary-240">Automatische Nachbestellung (Lager)' + (linkArtikelLager ? ' · ' + linkArtikelLager : '') + '</p>';
        } else {
            orderSourceLineHtml = '<p class="mt-1.5 text-xs text-gray-500 dark:text-primary-240">Lagerbezug' + (linkArtikelLager ? ' · ' + linkArtikelLager : '') + '</p>';
        }
    } else {
        orderSourceLineHtml = '<p class="mt-1.5 text-xs text-gray-500 dark:text-primary-240">Manuelle Bestellung</p>';
    }
    
    // Titel unter Breadcrumb: Bestellnummer
    const displayTitle = order.bestellnummer || ('Bestellung #' + order.id);
    orderTitle.textContent = displayTitle;
    // Gerät-Badges: Typ in einer Badge, Hersteller + Modell zusammen in einer Badge
    const deviceTypLabel = order.device_typ ? getDeviceTypLabel(order.device_typ) : '';
    const herstellerModell = [order.device_hersteller, order.device_modell].filter(Boolean).map(function(p) { return String(p).trim(); }).join(' ');
    const deviceBadgeParts = [];
    if (deviceTypLabel) deviceBadgeParts.push(deviceTypLabel);
    if (herstellerModell) deviceBadgeParts.push(herstellerModell);
    const deviceHeaderHtml = deviceBadgeParts.length ? '<div class="mt-2 flex flex-wrap items-center gap-2">' + deviceBadgeParts.map(function(p) { return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-primaryLight-140 dark:bg-primary-140 text-primaryLight-580 dark:text-primary-580 border border-primaryLight-560 dark:border-primary-560">' + escapeHtml(p) + '</span>'; }).join('') + '</div>' : '';
    const garantieOn = (order.garantie == 1 || order.garantie === true);
    const companyOrderMatch = (order.company_id == userCompanyIdJs);
    const canEditGarantie = currentUserRole === 'Admin' || currentUserRole === 'Techniker' ||
        (currentUserRole === 'Firmen-Admin' && companyOrderMatch) ||
        (currentUserRole === 'Firmen-User' && (companyOrderMatch || parseInt(String(order.erstellt_von || 0), 10) === currentUserId));
    const garantieBadgeHtml = garantieOn ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-amber-100 text-amber-900 dark:bg-amber-900/35 dark:text-amber-100 border border-amber-200 dark:border-amber-800 shrink-0" title="Bestellung über Garantie">Garantie</span>' : '';
    const garantieControlHtml = canEditGarantie ? `
                <div class="flex flex-col gap-1.5 shrink-0">
                    <span class="text-xs font-medium text-gray-500 dark:text-primary-240">Garantie</span>
                    <label class="inline-flex items-center gap-2.5 cursor-pointer select-none rounded-base border border-primaryLight-720 dark:border-primary-720 bg-primaryLight-700 dark:bg-primary-700 px-3 py-2.5">
                        <input type="checkbox" id="order-detail-garantie-cb" class="w-4 h-4 rounded border-gray-300 dark:border-primary-320 text-primaryLight-420 dark:text-primary-420 focus:ring-primaryLight-420 dark:focus:ring-primary-420 shrink-0" ${garantieOn ? 'checked' : ''} onchange="saveOrderGarantie(${order.id}, this)">
                        <span class="text-sm text-gray-900 dark:text-primary-200">Bestellung läuft über Garantie</span>
                    </label>
                </div>` : '';
    
    // Status-Änderungsrechte prüfen
    const canChangeStatus = currentUserRole === 'Admin' || currentUserRole === 'Techniker' || 
                   (currentUserRole === 'Firmen-Admin' && order.company_id == <?php echo $userCompanyId ? $userCompanyId : 'null'; ?>);
    
    const showCompany = <?php echo ($userRole === 'Admin' || $userRole === 'Techniker') ? 'true' : 'false'; ?>;
    const showCustomer = <?php echo ($userRole !== 'Kunde') ? 'true' : 'false'; ?>;
    const canAssignCompany = (currentUserRole === 'Admin' || currentUserRole === 'Techniker');
    
    // Status-Historie verarbeiten
    const statusOrder = ['Neu', 'Bestellt', 'Unterwegs', 'Beim Kunden', 'Im Lager', 'Angekommen'];
    const currentStatus = (function() {
        const raw = order.status || 'Neu';
        return raw === 'Offen' ? 'Neu' : raw;
    })();
    const currentStatusIndex = statusOrder.indexOf(currentStatus);
    const statusHistoryMap = {};
    
    if (order.status_history && order.status_history.length > 0) {
        order.status_history.forEach(history => {
            if (!statusHistoryMap[history.status]) {
                statusHistoryMap[history.status] = history;
            }
        });
    }
    
    // Tracking-Timeline: jeder Status einzeln sichtbar, aktueller Schritt klar hervorgehoben
    let trackingSteps = '';
    statusOrder.forEach((status, index) => {
        const isPast = index < currentStatusIndex;
        const isCurrent = currentStatus === status;
        const isActive = isPast || isCurrent;
        const historyEntry = statusHistoryMap[status] || (status === 'Neu' ? statusHistoryMap['Offen'] : null);
        const statusClass = isActive ? 'text-primaryLight-250 dark:text-primary-250' : 'text-gray-500 dark:text-gray-400';
        const currentStepClass = isCurrent ? ' ring-2 ring-primaryLight-420 dark:ring-primary-420 bg-primaryLight-140 dark:bg-primary-140 rounded-base font-semibold' : '';
        const clickable = canChangeStatus ? ' cursor-pointer hover:bg-gray-50 dark:hover:bg-primary-140' : '';
        const roleAttr = canChangeStatus ? ' role="button" tabindex="0"' : '';
        const clickAttr = canChangeStatus ? (status === 'Unterwegs' ? ' onclick="event.stopPropagation(); openTrackingModal(' + order.id + ')"' : ' onclick="event.stopPropagation(); updateOrderStatus(' + order.id + ', \'' + status + '\')"') : '';
        const currentAttr = isCurrent ? ' aria-current="step"' : '';
        
        let dateText = '';
        if (historyEntry && historyEntry.geaendert_datum) {
            dateText = formatDateTime(historyEntry.geaendert_datum);
        } else if (status === 'Neu' && order.erstellt_datum) {
            dateText = formatDateTime(order.erstellt_datum);
        } else {
            dateText = '<span class="font-normal">Noch nicht erreicht</span>';
        }
        const bemerkungHtml = (historyEntry && historyEntry.bemerkung) ? '<p class="mt-0.5 text-xs text-gray-500 dark:text-primary-240">' + escapeHtml(historyEntry.bemerkung) + '</p>' : '';
        
        trackingSteps += `
            <div class="py-4 lg:py-0 text-center p-2 rounded-base transition-colors${statusClass}${currentStepClass}${clickable}" data-status="${status}"${roleAttr}${clickAttr}${currentAttr}>
                ${getStatusIcon(status)}
                <p class="mt-2 text-sm">${dateText}</p>
                ${bemerkungHtml}
                <p class="mt-1 text-base font-medium leading-tight lg:text-sm xl:text-base">${getStatusLabel(status)}${isCurrent ? ' <span class="block text-xs font-semibold text-primaryLight-420 dark:text-primary-420 mt-0.5">Aktuell</span>' : ''}</p>
            </div>
        `;
    });
    
    // Progress-Berechnung (0-100%), jeder Status eigene Stufe
    const progress = currentStatusIndex >= 0 ? ((currentStatusIndex + 1) / statusOrder.length) * 100 : 0;
    
    // Lieferadresse zusammenstellen
    const shippingAddress = order.customer_lieferadresse 
        ? `${order.customer_lieferadresse}${order.customer_liefer_plz ? ', ' + order.customer_liefer_plz : ''}${order.customer_liefer_ort ? ' ' + order.customer_liefer_ort : ''}`
        : order.customer_adresse 
            ? `${order.customer_adresse}${order.customer_plz ? ', ' + order.customer_plz : ''}${order.customer_ort ? ' ' + order.customer_ort : ''}`
            : '-';
    
    const bestellungDurchValue = (order.bestellung_durch === 'kunde_firma' ? 'kunde' : order.bestellung_durch) || '';
    const companyName = (order.company_name || 'Firma').trim();
    const customerName = (order.customer_name || 'Kunde').trim();
    const hasCompany = !!(order.company_id || order.company_name);
    const hasOrderCustomer = !!(order.customer_id || order.customer_name);
    const showWirOption = currentUserRole === 'Admin' || currentUserRole === 'Techniker';
    // Branding-Farben (Erscheinungsbild): Primär-Button 420/440/480, Sekundär 540/560/580/600, Filter-Container 700/720
    const btnBase = 'inline-flex items-center justify-center px-4 py-2 text-sm font-medium border transition-colors focus:outline-none focus:ring-2 focus:ring-primary-250 focus:ring-offset-1 dark:focus:ring-offset-primary-100';
    const btnActive = 'bg-primaryLight-420 dark:bg-primary-420 hover:bg-primaryLight-440 dark:hover:bg-primary-440 text-primaryLight-480 dark:text-primary-480 border-primaryLight-420 dark:border-primary-420';
    const btnInactive = 'bg-primaryLight-540 dark:bg-primary-540 border-primaryLight-560 dark:border-primary-560 text-primaryLight-580 dark:text-primary-580 hover:bg-primaryLight-600 dark:hover:bg-primary-600';
    const parts = [];
    if (showWirOption) {
        const onlyWir = !hasCompany && !hasOrderCustomer;
        parts.push('<button type="button" onclick="setBestellungDurch(' + order.id + ', \'intern\')" class="bestellung-durch-btn ' + btnBase + ' rounded-l-md ' + (onlyWir ? 'rounded-r-md ' : '') + (bestellungDurchValue === 'intern' ? btnActive : btnInactive) + '">Wir</button>');
    }
    if (hasCompany) parts.push('<button type="button" onclick="setBestellungDurch(' + order.id + ', \'firma\')" class="bestellung-durch-btn ' + btnBase + (parts.length === 0 ? ' rounded-l-md ' : ' ') + (bestellungDurchValue === 'firma' ? btnActive : btnInactive) + (hasOrderCustomer ? '' : ' rounded-r-md') + '">' + escapeHtml(companyName) + '</button>');
    if (hasOrderCustomer) parts.push('<button type="button" onclick="setBestellungDurch(' + order.id + ', \'kunde\')" class="bestellung-durch-btn ' + btnBase + ' rounded-r-md ' + (bestellungDurchValue === 'kunde' ? btnActive : btnInactive) + '">' + escapeHtml(customerName) + '</button>');
    if (bestellungDurchValue === 'lagersystem') parts.push('<span class="bestellung-durch-btn ' + btnBase + ' rounded-r-md ' + btnActive + ' cursor-default">Lagersystem</span>');
    const bestellungDurchButtons = canChangeStatus && parts.length > 0 ? '<div class="inline-flex rounded-base overflow-hidden border border-primaryLight-720 dark:border-primary-720 bg-primaryLight-700 dark:bg-primary-700" role="group" aria-label="Bestellung durch">' + parts.join('') + '</div>' : '';
    const attachments = order.ticket_attachments || [];
    const attachmentsHtml = attachments.length > 0 ? attachments.map(function(a) {
        const fileUrl = baseUrl + (a.dateipfad || '').replace(/^\//, '');
        const fileName = a.dateiname || 'Unbekannt';
        const size = a.dateigroesse ? (a.dateigroesse < 1024 ? a.dateigroesse + ' B' : (a.dateigroesse < 1024*1024 ? (a.dateigroesse/1024).toFixed(1) + ' KB' : (a.dateigroesse/1024/1024).toFixed(1) + ' MB')) : '';
        return '<a href="' + escapeHtml(fileUrl) + '" target="_blank" download class="flex items-center gap-3 p-3 rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors"><svg class="w-5 h-5 text-gray-500 dark:text-primary-210 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span class="text-sm font-medium text-gray-900 dark:text-primary-200 truncate flex-1">' + escapeHtml(fileName) + '</span>' + (size ? '<span class="text-xs text-gray-500 dark:text-primary-210">' + size + '</span>' : '') + '</a>';
    }).join('') : '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Anhänge vom Ticket.</p>';
    const hasDevice = !!(order.device_id || order.device_name || order.device_hersteller || order.device_modell || order.device_seriennummer || order.device_beschreibung);
    const deviceBlockHtml = hasDevice ? `
        <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Gerät</h3>
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-gray-100 dark:bg-primary-200 rounded-base shrink-0">
                    <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    ${order.device_name ? '<p class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.device_name) + '</p>' : ''}
                    ${order.device_id ? '<p class="text-sm text-gray-500 dark:text-primary-210 mt-0.5"><a href="' + devicesViewBase + order.device_id + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">Zum Gerät</a></p>' : ''}
                </div>
            </div>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm">
                ${order.device_beschreibung ? '<div><dt class="text-gray-500 dark:text-primary-210">Gerätestandort</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.device_beschreibung) + '</dd></div>' : ''}
                ${order.device_hersteller ? '<div><dt class="text-gray-500 dark:text-primary-210">Hersteller</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.device_hersteller) + '</dd></div>' : ''}
                ${order.device_modell ? '<div><dt class="text-gray-500 dark:text-primary-210">Modell</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.device_modell) + '</dd></div>' : ''}
                ${order.device_seriennummer ? '<div><dt class="text-gray-500 dark:text-primary-210">Seriennummer</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.device_seriennummer) + '</dd></div>' : ''}
            </dl>
        </div>
    ` : '';
    
    const hasCustomer = (showCustomer && (order.customer_id || order.customer_name)) || (showCompany && (order.company_id || order.company_name));
    const auftraggeberTitle = bestellungDurchValue === 'intern' ? 'Wir bestellen' : (bestellungDurchValue === 'firma' ? companyName : (bestellungDurchValue === 'kunde' ? customerName : (bestellungDurchValue === 'lagersystem' ? 'Lagersystem' : 'Auftraggeber')));
    const customerBlockHtml = hasCustomer ? `
        <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm order-company-context-target" data-order-id="${order.id}">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">${escapeHtml(auftraggeberTitle)}</h3>
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-gray-100 dark:bg-primary-200 rounded-base shrink-0">
                    <svg class="w-5 h-5 text-gray-600 dark:text-primary-210" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    ${showCustomer && order.customer_name ? '<p class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.customer_name) + '</p>' : ''}
                    ${showCompany && order.company_name ? (showCustomer && order.customer_name ? '<p class="text-sm text-gray-500 dark:text-primary-210">' + escapeHtml(order.company_name) + '</p>' : '<p class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.company_name) + '</p>') : ''}
                    ${(showCustomer && order.customer_id) || (showCompany && order.company_id) ? '<p class="text-sm text-gray-500 dark:text-primary-210 mt-0.5">' + (showCustomer && order.customer_id ? '<a href="' + customersViewBase + order.customer_id + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">Zum Kunden</a>' : '') + (showCompany && order.company_id ? (showCustomer && order.customer_id ? ' · ' : '') + '<a href="' + companiesViewBase + order.company_id + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">Zur Firma</a>' : '') + '</p>' : ''}
                </div>
            </div>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm">
                ${showCompany && order.company_name ? '<div><dt class="text-gray-500 dark:text-primary-210">Firma</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.company_name) + '</dd></div>' : ''}
                ${showCustomer && order.customer_name ? '<div><dt class="text-gray-500 dark:text-primary-210">Kunde</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.customer_name) + '</dd></div>' : ''}
                ${shippingAddress && shippingAddress !== '-' ? '<div><dt class="text-gray-500 dark:text-primary-210">Lieferadresse</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(shippingAddress) + '</dd></div>' : ''}
                ${order.customer_email ? '<div><dt class="text-gray-500 dark:text-primary-210">E-Mail</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.customer_email) + '</dd></div>' : ''}
                ${order.customer_telefon ? '<div><dt class="text-gray-500 dark:text-primary-210">Telefon</dt><dd class="font-medium text-gray-900 dark:text-primary-200">' + escapeHtml(order.customer_telefon) + '</dd></div>' : ''}
            </dl>
        </div>
    ` : '';
    
    orderContent.innerHTML = `
        <div class="p-4 md:p-6 border-b border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-primary-200">${beschreibungTitleLine ? escapeHtml(beschreibungTitleLine) : (beschreibungText ? escapeHtml(beschreibungText) : 'Keine Beschreibung')}</h2>
                        ${garantieBadgeHtml}
                        ${getAvailabilityBadge(isInStockNow)}
                        ${getWarehouseSourceBadge(isFromWarehouse)}
                    </div>
                    ${deviceHeaderHtml}
                    ${orderSourceLineHtml}
                    ${order.project_id && order.project_name ? '<p class="mt-1 text-xs text-gray-500 dark:text-primary-240">Projekt: <a href="' + projectsViewBase + order.project_id + '" class="text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 hover:underline">' + escapeHtml(order.project_name) + '</a></p>' : ''}
                </div>
                <div class="flex flex-col sm:flex-row flex-wrap gap-4 sm:items-start sm:justify-end">
                    ${garantieControlHtml}
                    ${bestellungDurchButtons ? '<div class="flex flex-col gap-1.5 shrink-0"><span class="text-xs font-medium text-gray-500 dark:text-primary-240">Bestellung durch</span>' + bestellungDurchButtons + '</div>' : ''}
                </div>
            </div>
            
            <div class="mt-6 border-b border-gray-200 dark:border-primary-120 -mb-px">
                <nav class="flex gap-1 rounded-base" aria-label="Tabs">
                    <button type="button" data-tab="uebersicht" class="order-tab-btn px-4 py-2.5 text-sm font-medium rounded-t-base border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-primary-210 dark:hover:text-primary-200" aria-current="page">Übersicht</button>
                    <button type="button" data-tab="aktivitaet" class="order-tab-btn px-4 py-2.5 text-sm font-medium rounded-t-base border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-primary-210 dark:hover:text-primary-200">Aktivität</button>
                    <button type="button" data-tab="anhaenge" class="order-tab-btn px-4 py-2.5 text-sm font-medium rounded-t-base border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-primary-210 dark:hover:text-primary-200">Anhänge</button>
                </nav>
            </div>
            
            <div id="tab-uebersicht" class="order-tab-panel mt-6 p-4 md:p-6 bg-gray-50/50 dark:bg-primary-50/50 min-h-0 space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500 dark:text-primary-240">Aktueller Status</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-primary-200">${escapeHtml(getStatusLabel(currentStatus))}</p>
                        <div class="mt-2">${getStatusBadge(currentStatus)}</div>
                    </div>
                    <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500 dark:text-primary-240">Lager</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            ${getAvailabilityBadge(isInStockNow)}
                            ${getWarehouseSourceBadge(isFromWarehouse)}
                        </div>
                    </div>
                    <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500 dark:text-primary-240">Lieferadresse</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-primary-200">${escapeHtml(shippingAddress)}</p>
                    </div>
                </div>

                <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm w-full">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Statusverlauf</h3>
                    ${canChangeStatus ? '<p class="text-xs text-gray-500 dark:text-primary-240 mb-2">Klicken Sie auf einen Schritt, um den Status zu setzen – z. B. „Beim Kunden“ oder „Im Lager“ als getrennte Optionen.</p>' : ''}
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 text-center">${trackingSteps}</div>
                    <div class="mt-4 h-2 w-full rounded-full bg-gray-200 dark:bg-primary-120"><div class="h-2 rounded-full bg-primaryLight-420 dark:bg-primary-420 transition-all" style="width: ${progress}%"></div></div>
                </div>
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 space-y-6">
                        <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-2">Beschreibung & Notiz</h3>
                            ${beschreibungText ? '<p class="text-sm text-gray-900 dark:text-primary-200 whitespace-pre-wrap">' + escapeHtml(beschreibungText) + '</p>' : '<p class="text-sm text-gray-500 dark:text-primary-210">Keine Angabe</p>'}
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-primary-120">
                                <label for="notizenTextarea" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Interne Notiz</label>
                                <textarea id="notizenTextarea" rows="4" class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210" placeholder="Notizen zur Bestellung...">${escapeHtml(order.notizen || '')}</textarea>
                            </div>
                        </div>

                        <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-primaryLight-140 dark:bg-primary-140 text-primaryLight-580 dark:text-primary-580">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200">Sendungsverfolgung</h3>
                            </div>
                            ${(order.tracking_nummer || order.tracking_link) ? '<dl class="space-y-3 mb-4 pb-4 border-b border-gray-200 dark:border-primary-120"><div><dt class="text-xs font-medium text-gray-500 dark:text-primary-240 mb-0.5">Tracking-Nummer</dt><dd class="text-sm font-medium text-gray-900 dark:text-primary-200">' + (order.tracking_nummer ? escapeHtml(order.tracking_nummer) : '<span class="text-gray-400 dark:text-primary-220">—</span>') + '</dd></div><div><dt class="text-xs font-medium text-gray-500 dark:text-primary-240 mb-0.5">Link</dt><dd class="text-sm">' + (order.tracking_link ? '<a href="' + escapeHtml(order.tracking_link) + '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260 font-medium"><span>Sendung verfolgen</span><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>' : '<span class="text-gray-400 dark:text-primary-220">—</span>') + '</dd></div></dl>' : '<p class="text-sm text-gray-500 dark:text-primary-240 mb-4 pb-4 border-b border-gray-200 dark:border-primary-120">Noch keine Sendungsverfolgung hinterlegt.</p>'}
                            ${canChangeStatus ? '<div class="space-y-3"><p class="text-xs font-medium text-gray-500 dark:text-primary-240">Tracking bearbeiten</p><div class="grid gap-3"><div><label for="tracking_nummer_input" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Tracking-Nummer</label><input type="text" id="tracking_nummer_input" value="' + escapeHtml(order.tracking_nummer || '') + '" placeholder="z. B. 1234567890" class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent"/></div><div><label for="tracking_link_input" class="block text-xs font-medium text-gray-500 dark:text-primary-240 mb-1">Tracking-Link</label><input type="url" id="tracking_link_input" value="' + escapeHtml(order.tracking_link || '') + '" placeholder="https://..." class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent"/></div></div><button onclick="saveTracking(' + order.id + ')" class="mt-2 w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primaryLight-420 dark:focus:ring-primary-420">Speichern</button></div>' : ''}
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-3">Lager & Herkunft</h3>
                            <div class="flex flex-wrap gap-2">
                                ${getAvailabilityBadge(isInStockNow)}
                                ${getWarehouseSourceBadge(isFromWarehouse)}
                            </div>
                            <p class="mt-3 text-sm text-gray-600 dark:text-primary-220">${escapeHtml(lagerHinweis)}</p>
                            ${(isFromWarehouse && linkArtikelLager) ? '<p class="mt-2 text-sm">' + linkArtikelLager + '</p>' : ''}
                        </div>

                        ${deviceBlockHtml}
                        ${customerBlockHtml}
                    </div>
                </div>
            </div>
            
            <div id="tab-aktivitaet" class="order-tab-panel hidden mt-6 p-4 md:p-6 bg-gray-50/50 dark:bg-primary-50/50 min-h-0">
                <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-4">Aktivitätsübersicht</h3>
                    <div id="logsContainer" class="space-y-4">
                        <div class="flex items-center justify-center py-8"><svg class="animate-spin w-8 h-8 text-primary-250" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></div>
                    </div>
                </div>
            </div>
            
            <div id="tab-anhaenge" class="order-tab-panel hidden mt-6 p-4 md:p-6 bg-gray-50/50 dark:bg-primary-50/50 min-h-0">
                <div class="rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-primary-200 mb-4">Anhänge vom Ticket</h3>
                    <div class="space-y-2">${attachmentsHtml}</div>
                </div>
            </div>
            
            <!-- Modal: Tracking-Link bei Status Unterwegs -->
            <div id="trackingModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" data-order-id="">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50 dark:bg-black/70" onclick="closeTrackingModal()"></div>
                    <div class="relative rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg p-6 w-full max-w-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-3">Status „Unterwegs“ – Tracking (optional)</h3>
                        <p class="text-sm text-gray-500 dark:text-primary-240 mb-4">Sie können optional einen Tracking-Link und eine Tracking-Nummer angeben.</p>
                        <div class="space-y-3 mb-5">
                            <div>
                                <label for="tracking_modal_nummer" class="block text-xs font-medium text-gray-600 dark:text-primary-210 mb-1">Tracking-Nummer</label>
                                <input type="text" id="tracking_modal_nummer" placeholder="z. B. 1234567890" class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210"/>
                            </div>
                            <div>
                                <label for="tracking_modal_link" class="block text-xs font-medium text-gray-600 dark:text-primary-210 mb-1">Tracking-Link</label>
                                <input type="url" id="tracking_modal_link" placeholder="https://..." class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210"/>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button type="button" onclick="closeTrackingModal()" class="px-4 py-2 text-sm font-medium rounded-base border border-gray-300 dark:border-primary-120 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
                            <button type="button" onclick="submitTrackingModal(false)" class="px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-540 dark:bg-primary-540 text-primaryLight-580 dark:text-primary-580 hover:bg-primaryLight-600 dark:hover:bg-primary-600">Ohne Tracking</button>
                            <button type="button" onclick="submitTrackingModal(true)" class="px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440">Mit Tracking speichern</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="orderCompanyContextMenu" class="hidden fixed z-[80] min-w-[220px] rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg overflow-hidden">
                <button type="button" id="orderCompanyContextAssignBtn" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">
                    Firma hinzufügen
                </button>
            </div>
            <div id="orderCompanyAssignModal" class="hidden fixed inset-0 z-[90] overflow-y-auto" aria-modal="true" role="dialog">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50 dark:bg-black/70" id="orderCompanyAssignModalOverlay"></div>
                    <div class="relative rounded-base border border-gray-200 dark:border-primary-120 bg-white dark:bg-primary-100 shadow-lg p-6 w-full max-w-lg">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-3">Firma zur Bestellung zuweisen</h3>
                        <p class="text-sm text-gray-500 dark:text-primary-240 mb-4">Wählen Sie die Firma aus, die nachträglich zur Bestellung hinzugefügt werden soll.</p>
                        <div class="space-y-3 mb-5">
                            <input type="text" id="orderCompanySearchInput" placeholder="Firma suchen..." class="w-full px-3 py-2 text-sm rounded-base border border-gray-300 dark:border-primary-720 bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-210 focus:ring-2 focus:ring-primaryLight-420 dark:focus:ring-primary-420 focus:border-transparent"/>
                            <div id="orderCompanyList" class="max-h-72 overflow-y-auto rounded-base border border-gray-200 dark:border-primary-120 divide-y divide-gray-200 dark:divide-primary-120"></div>
                        </div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button type="button" id="orderCompanyAssignCancelBtn" class="px-4 py-2 text-sm font-medium rounded-base border border-gray-300 dark:border-primary-120 text-gray-700 dark:text-primary-210 hover:bg-gray-50 dark:hover:bg-primary-140">Abbrechen</button>
                            <button type="button" id="orderCompanyAssignSaveBtn" class="px-4 py-2 text-sm font-medium rounded-base bg-primaryLight-420 dark:bg-primary-420 text-primaryLight-480 dark:text-primary-480 hover:bg-primaryLight-440 dark:hover:bg-primary-440 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Firma speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    (function initTabs() {
        var btns = orderContent.querySelectorAll('.order-tab-btn');
        var panels = orderContent.querySelectorAll('.order-tab-panel');
        btns.forEach(function(btn, i) {
            btn.addEventListener('click', function() {
                var tab = btn.getAttribute('data-tab');
                btns.forEach(function(b) { b.classList.remove('border-primary-250', 'text-primary-250', 'dark:border-primary-250', 'dark:text-primary-250'); b.classList.add('border-transparent', 'text-gray-500', 'dark:text-primary-210'); });
                btn.classList.add('border-primary-250', 'text-primary-250'); btn.classList.remove('border-transparent', 'text-gray-500', 'dark:text-primary-210');
                panels.forEach(function(p) {
                    p.classList.toggle('hidden', p.id !== 'tab-' + tab);
                });
            });
        });
        if (btns[0]) { btns[0].classList.add('border-primary-250', 'text-primary-250'); btns[0].classList.remove('border-transparent', 'text-gray-500'); }
    })();
    
    // Logs nach dem Rendern laden (damit logsContainer existiert)
    setTimeout(() => {
        loadLogs();
    }, 100);

    initOrderCompanyContextMenu(order, canAssignCompany);
    initOrderCompanyModalHandlers();
}

function showError(message) {
    const orderContent = document.getElementById('orderContent');
    orderContent.innerHTML = `
        <div class="p-6 text-center">
            <p class="text-red-500 dark:text-red-400">${escapeHtml(message)}</p>
            <a href="<?php echo BASE_URL; ?>orders/" class="mt-4 inline-block text-primaryLight-250 dark:text-primary-250 hover:text-primaryLight-260 dark:hover:text-primary-260">
                Zurück zur Übersicht
            </a>
        </div>
    `;
}

function loadLogs() {
    const logsContainer = document.getElementById('logsContainer');
    if (!logsContainer) {
        return;
    }
    
    fetch(logsApiUrl + '?kategorie=order&entity_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs) {
                displayLogs(data.logs);
            } else {
                logsContainer.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Einträge vorhanden</div>';
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Logs:', error);
            const logsContainer = document.getElementById('logsContainer');
            if (logsContainer) {
                logsContainer.innerHTML = '<div class="text-center text-red-500 dark:text-red-400 py-4">Fehler beim Laden der Logs</div>';
            }
        });
}

function formatDateForTimeline(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}. ${month} ${year}, ${hours}:${minutes} Uhr`;
}

function displayLogs(logs) {
    const logsContainer = document.getElementById('logsContainer');
    if (!logsContainer) return;
    
    if (logs.length === 0) {
        logsContainer.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 py-4">Keine Einträge vorhanden</div>';
        return;
    }
    
    const actionText = {
        'created': 'Erstellt',
        'updated': 'Aktualisiert',
        'deleted': 'Gelöscht'
    };
    
    const actionIcons = {
        'created': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
        'updated': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
        'deleted': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
    };
    
    const fieldLabels = {
        'bestellnummer': 'Bestellnummer',
        'beschreibung': 'Beschreibung',
        'status': 'Status',
        'tracking_nummer': 'Tracking-Nummer',
        'tracking_link': 'Tracking-Link',
        'company_id': 'Firma',
        'customer_id': 'Kunde',
        'bestellung_durch': 'Bestellung durch'
    };
    
    logsContainer.innerHTML = `
        <ol class="relative border-s border-gray-200 dark:border-gray-700 ml-4">
            ${logs.map((log, index) => {
                // Bestimme den anzuzeigenden Namen basierend auf Rollen
                let userName;
                if (currentUserRole === 'Admin' || currentUserRole === 'Techniker') {
                    // Admin und Techniker sehen alle Namen
                    userName = log.user_vorname && log.user_nachname 
                        ? `${log.user_vorname} ${log.user_nachname}` 
                        : (log.user_email || 'Unbekannt');
                } else {
                    // Andere Benutzer sehen "Techniker" statt Namen von Admin/Techniker
                    if (log.user_rolle === 'Admin' || log.user_rolle === 'Techniker') {
                        userName = 'Techniker';
                    } else {
                        userName = log.user_vorname && log.user_nachname 
                            ? `${log.user_vorname} ${log.user_nachname}` 
                            : (log.user_email || 'Unbekannt');
                    }
                }
                const date = formatDateForTimeline(log.erstellt_datum);
                const actionTextLabel = actionText[log.action] || log.action;
                const actionIcon = actionIcons[log.action] || actionIcons['updated'];
                const isLatest = index === 0;
                
                let changeDetails = '';
                if (log.field_name) {
                    const fieldLabel = fieldLabels[log.field_name] || (log.field_name === 'notizen' ? 'Notizen' : log.field_name.charAt(0).toUpperCase() + log.field_name.slice(1).replace(/_/g, ' '));
                    const bestellungDurchLabels = { 'intern': 'Wir', 'kunde_firma': 'Kunde/Firma', 'firma': 'Firma', 'kunde': 'Kunde', 'lagersystem': 'Lagersystem' };
                    const formatVal = function(v) {
                        if (log.field_name === 'bestellung_durch' && v) return bestellungDurchLabels[v] || v;
                        return v;
                    };
                    changeDetails = '<p class="mb-4 text-gray-600 dark:text-gray-400">Feld <strong>' + escapeHtml(fieldLabel) + '</strong> wurde geändert';
                    if (log.old_value !== null && log.old_value !== '') {
                        changeDetails += ' von <span class="text-red-600 dark:text-red-400">"' + escapeHtml(String(formatVal(log.old_value))) + '"</span> zu <span class="text-green-600 dark:text-green-400">"' + escapeHtml(String(formatVal(log.new_value || ''))) + '"</span>';
                    } else if (log.new_value) {
                        changeDetails += ' auf <span class="text-green-600 dark:text-green-400">"' + escapeHtml(String(formatVal(log.new_value))) + '"</span>';
                    }
                    changeDetails += '.</p>';
                }
                
                if (log.beschreibung) {
                    changeDetails += `<p class="mb-4 text-gray-600 dark:text-gray-400">${escapeHtml(log.beschreibung)}</p>`;
                }
                
                if (!changeDetails && !log.beschreibung) {
                    changeDetails = `<p class="mb-4 text-gray-600 dark:text-gray-400">Bestellung wurde ${actionTextLabel.toLowerCase()}.</p>`;
                }
                
                return `
                    <li class="mb-10 ms-6">
                        <span class="absolute flex items-center justify-center w-6 h-6 bg-primary-100 dark:bg-primary-900 rounded-full -start-3 ring-8 ring-white dark:ring-gray-800">
                            <svg class="w-3 h-3 text-primary-200 dark:text-primary-200" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                ${actionIcon}
                            </svg>
                        </span>
                        <time class="bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-xs font-medium px-1.5 py-0.5 rounded">
                            ${date}
                        </time>
                        <h3 class="flex items-center mb-1 text-lg font-semibold text-gray-900 dark:text-white my-2">
                            ${actionTextLabel}
                            ${isLatest ? '<span class="ms-2 bg-primaryLight-140 dark:bg-primary-140 border border-primaryLight-250 dark:border-primary-250 text-primaryLight-250 dark:text-primary-250 text-xs font-medium px-1.5 py-0.5 rounded">Neueste</span>' : ''}
                        </h3>
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">von <strong>${escapeHtml(userName)}</strong></p>
                        ${changeDetails}
                    </li>
                `;
            }).join('')}
        </ol>
    `;
}

function saveOrderGarantie(orderId, checkboxEl) {
    if (!checkboxEl) return;
    const checked = !!checkboxEl.checked;
    checkboxEl.disabled = true;
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: orderId, garantie: checked })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        checkboxEl.disabled = false;
        if (data.success) {
            if (typeof showToast === 'function') showToast(checked ? 'Garantie aktiviert' : 'Garantie deaktiviert', 'success');
            loadOrder();
            loadLogs();
        } else {
            checkboxEl.checked = !checked;
            if (typeof showToast === 'function') showToast(data.error || 'Speichern fehlgeschlagen', 'error');
        }
    })
    .catch(function() {
        checkboxEl.disabled = false;
        checkboxEl.checked = !checked;
        if (typeof showToast === 'function') showToast('Speichern fehlgeschlagen', 'error');
    });
}

function setBestellungDurch(orderId, value) {
    const val = (value === 'intern' || value === 'firma' || value === 'kunde') ? value : null;
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: orderId, bestellung_durch: val || null })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (typeof showToast === 'function') showToast('Bestellung durch wurde gespeichert', 'success');
            loadOrder();
            loadLogs();
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
        }
    })
    .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
    });
}

let orderCompanyContextOrderId = null;
let orderCompanySelectionId = null;
let orderCompanySelectionName = '';
let orderCompanyListCache = [];

function initOrderCompanyContextMenu(order, canAssignCompany) {
    const target = document.querySelector('.order-company-context-target');
    const menu = document.getElementById('orderCompanyContextMenu');
    const assignBtn = document.getElementById('orderCompanyContextAssignBtn');
    if (!target || !menu || !assignBtn) return;

    if (!canAssignCompany) {
        menu.classList.add('hidden');
        return;
    }

    assignBtn.textContent = order.company_id ? 'Firma ändern' : 'Firma hinzufügen';
    target.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        orderCompanyContextOrderId = order.id;
        menu.classList.remove('hidden');
        let left = e.clientX;
        let top = e.clientY;
        const viewportPadding = 8;
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth - viewportPadding) {
            left = Math.max(viewportPadding, left - (rect.right - window.innerWidth + viewportPadding));
        }
        if (rect.bottom > window.innerHeight - viewportPadding) {
            top = Math.max(viewportPadding, top - (rect.bottom - window.innerHeight + viewportPadding));
        }
        if (rect.left < viewportPadding) left = viewportPadding;
        if (rect.top < viewportPadding) top = viewportPadding;
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    });
}

function hideOrderCompanyContextMenu() {
    const menu = document.getElementById('orderCompanyContextMenu');
    if (menu) menu.classList.add('hidden');
}

function openOrderCompanyAssignModal() {
    const modal = document.getElementById('orderCompanyAssignModal');
    const listEl = document.getElementById('orderCompanyList');
    const searchEl = document.getElementById('orderCompanySearchInput');
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (!modal || !listEl || !searchEl || !saveBtn) return;

    orderCompanySelectionId = null;
    orderCompanySelectionName = '';
    saveBtn.disabled = true;
    listEl.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500 dark:text-primary-210">Lade Firmen...</div>';
    modal.classList.remove('hidden');
    searchEl.value = '';

    fetch(companiesApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !Array.isArray(data.companies)) {
                throw new Error(data.error || 'Firmen konnten nicht geladen werden');
            }
            orderCompanyListCache = data.companies;
            renderOrderCompanyList('');
        })
        .catch(function() {
            listEl.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Firmen konnten nicht geladen werden.</div>';
        });
}

function closeOrderCompanyAssignModal() {
    const modal = document.getElementById('orderCompanyAssignModal');
    if (modal) modal.classList.add('hidden');
}

function renderOrderCompanyList(searchTerm) {
    const listEl = document.getElementById('orderCompanyList');
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (!listEl || !saveBtn) return;

    const term = (searchTerm || '').toLowerCase().trim();
    const entries = orderCompanyListCache.filter(function(c) {
        const name = String(c.name || '').toLowerCase();
        return term === '' || name.indexOf(term) !== -1;
    });

    if (entries.length === 0) {
        listEl.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500 dark:text-primary-210">Keine passende Firma gefunden.</div>';
        return;
    }

    listEl.innerHTML = entries.map(function(c) {
        const selected = Number(orderCompanySelectionId) === Number(c.id);
        return '<button type="button" class="w-full text-left px-4 py-2.5 text-sm transition-colors ' + (selected ? 'bg-primaryLight-140 dark:bg-primary-140 text-primaryLight-580 dark:text-primary-580' : 'text-gray-700 dark:text-primary-200 hover:bg-gray-50 dark:hover:bg-primary-140') + '" data-company-id="' + escapeHtml(String(c.id)) + '">' + escapeHtml(String(c.name || 'Ohne Namen')) + '</button>';
    }).join('');

    listEl.querySelectorAll('button[data-company-id]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            orderCompanySelectionId = Number(btn.getAttribute('data-company-id'));
            orderCompanySelectionName = (btn.textContent || '').trim();
            saveBtn.disabled = false;
            renderOrderCompanyList(term);
        });
    });
}

function saveOrderCompanyAssignment() {
    if (!orderCompanyContextOrderId || !orderCompanySelectionId) return;
    const saveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (saveBtn) saveBtn.disabled = true;

    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: orderCompanyContextOrderId,
            company_id: orderCompanySelectionId
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeOrderCompanyAssignModal();
            if (typeof showToast === 'function') {
                showToast('Firma wurde zugewiesen: ' + (orderCompanySelectionName || 'Unbekannt'), 'success');
            }
            loadOrder();
            loadLogs();
        } else {
            if (saveBtn) saveBtn.disabled = false;
            if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
        }
    })
    .catch(function() {
        if (saveBtn) saveBtn.disabled = false;
        if (typeof showToast === 'function') showToast('Fehler beim Speichern', 'error');
    });
}

function initOrderCompanyModalHandlers() {
    const contextAssignBtn = document.getElementById('orderCompanyContextAssignBtn');
    if (contextAssignBtn && contextAssignBtn.dataset.bound !== '1') {
        contextAssignBtn.dataset.bound = '1';
        contextAssignBtn.addEventListener('click', function() {
            hideOrderCompanyContextMenu();
            openOrderCompanyAssignModal();
        });
    }

    const companyAssignCancelBtn = document.getElementById('orderCompanyAssignCancelBtn');
    if (companyAssignCancelBtn && companyAssignCancelBtn.dataset.bound !== '1') {
        companyAssignCancelBtn.dataset.bound = '1';
        companyAssignCancelBtn.addEventListener('click', closeOrderCompanyAssignModal);
    }

    const companyAssignOverlay = document.getElementById('orderCompanyAssignModalOverlay');
    if (companyAssignOverlay && companyAssignOverlay.dataset.bound !== '1') {
        companyAssignOverlay.dataset.bound = '1';
        companyAssignOverlay.addEventListener('click', closeOrderCompanyAssignModal);
    }

    const companyAssignSaveBtn = document.getElementById('orderCompanyAssignSaveBtn');
    if (companyAssignSaveBtn && companyAssignSaveBtn.dataset.bound !== '1') {
        companyAssignSaveBtn.dataset.bound = '1';
        companyAssignSaveBtn.addEventListener('click', saveOrderCompanyAssignment);
    }

    const companySearchInput = document.getElementById('orderCompanySearchInput');
    if (companySearchInput && companySearchInput.dataset.bound !== '1') {
        companySearchInput.dataset.bound = '1';
        companySearchInput.addEventListener('input', function() {
            renderOrderCompanyList(companySearchInput.value || '');
        });
    }
}

function saveNotizen(orderId) {
    const textarea = document.getElementById('notizenTextarea');
    if (!textarea) return;
    
    const notizen = textarea.value.trim();
    
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: orderId,
            notizen: notizen
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Notiz gespeichert', 'success');
            }
            loadLogs();
        } else {
            if (typeof showToast === 'function') {
                showToast(data.error || 'Fehler beim Speichern der Notiz', 'error');
            } else {
                alert(data.error || 'Fehler beim Speichern der Notiz');
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der Notiz', 'error');
        } else {
            alert('Fehler beim Speichern der Notiz');
        }
    });
}

function saveTracking(orderId) {
    const trackingNummerInput = document.getElementById('tracking_nummer_input');
    const trackingLinkInput = document.getElementById('tracking_link_input');
    const button = event.target;
    
    if (!trackingNummerInput || !trackingLinkInput || !button) return;
    
    const trackingNummer = trackingNummerInput.value.trim();
    const trackingLink = trackingLinkInput.value.trim();
    const originalText = button.textContent;
    
    // Button während der Anfrage deaktivieren
    button.disabled = true;
    button.textContent = 'Wird gespeichert...';
    
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: orderId,
            tracking_nummer: trackingNummer || null,
            tracking_link: trackingLink || null
        })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = originalText;
        
        if (data.success) {
            // Toast-Benachrichtigung anzeigen
            if (typeof showToast === 'function') {
                showToast('Tracking-Informationen erfolgreich gespeichert', 'success');
            }
            // Bestellung neu laden, um aktualisierte Daten zu erhalten
            loadOrder();
        } else {
            // Fehler anzeigen
            if (typeof showToast === 'function') {
                showToast(data.error || 'Fehler beim Speichern der Tracking-Informationen', 'error');
            } else {
                alert(data.error || 'Fehler beim Speichern der Tracking-Informationen');
            }
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        button.disabled = false;
        button.textContent = originalText;
        
        if (typeof showToast === 'function') {
            showToast('Fehler beim Speichern der Tracking-Informationen', 'error');
        } else {
            alert('Fehler beim Speichern der Tracking-Informationen');
        }
    });
}

function openTrackingModal(orderId) {
    const modal = document.getElementById('trackingModal');
    if (!modal) return;
    modal.setAttribute('data-order-id', String(orderId));
    const nummerEl = document.getElementById('tracking_modal_nummer');
    const linkEl = document.getElementById('tracking_modal_link');
    if (nummerEl) nummerEl.value = '';
    if (linkEl) linkEl.value = '';
    modal.classList.remove('hidden');
}

function closeTrackingModal() {
    const modal = document.getElementById('trackingModal');
    if (modal) modal.classList.add('hidden');
}

function submitTrackingModal(withTracking) {
    const modal = document.getElementById('trackingModal');
    if (!modal) return;
    const orderId = modal.getAttribute('data-order-id');
    if (!orderId) return;
    closeTrackingModal();

    if (!withTracking) {
        updateOrderStatus(parseInt(orderId, 10), 'Unterwegs');
        return;
    }

    const nummerEl = document.getElementById('tracking_modal_nummer');
    const linkEl = document.getElementById('tracking_modal_link');
    const trackingNummer = nummerEl ? (nummerEl.value || '').trim() : '';
    const trackingLink = linkEl ? (linkEl.value || '').trim() : '';

    const payload = { id: parseInt(orderId, 10), status: 'Unterwegs' };
    if (trackingNummer) payload.tracking_nummer = trackingNummer;
    if (trackingLink) payload.tracking_link = trackingLink;

    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadOrder();
            if (typeof showToast === 'function') showToast('Status und Tracking gespeichert', 'success');
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'Fehler beim Speichern', 'error');
            else alert(data.error || 'Fehler beim Speichern');
        }
    })
    .catch(function() {
        if (typeof showToast === 'function') showToast('Fehler beim Speichern der Tracking-Informationen', 'error');
        else alert('Fehler beim Speichern der Tracking-Informationen');
    });
}

function updateOrderStatus(orderId, newStatus) {
    // Button deaktivieren während der Anfrage
    const buttons = document.querySelectorAll(`button[onclick*="updateOrderStatus(${orderId}"]`);
    buttons.forEach(btn => btn.disabled = true);
    
    fetch(ordersApiUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: orderId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Bestellung neu laden, um aktualisierte Daten zu erhalten
            // loadLogs() wird automatisch am Ende von displayOrder() aufgerufen
            loadOrder();
            
            // Toast-Benachrichtigung anzeigen, falls verfügbar
            if (typeof showToast === 'function') {
                showToast('Status erfolgreich geändert', 'success');
            }
        } else {
            alert('Fehler beim Ändern des Status: ' + (data.error || 'Unbekannter Fehler'));
            // Buttons wieder aktivieren
            buttons.forEach(btn => btn.disabled = false);
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Fehler beim Ändern des Status');
        // Buttons wieder aktivieren
        buttons.forEach(btn => btn.disabled = false);
    });
}

// Auto-Save Notiz: Debounce bei Eingabe, sofort bei Blur
let notizSaveTimeout;
document.addEventListener('input', function(e) {
    if (e.target.id === 'notizenTextarea') {
        clearTimeout(notizSaveTimeout);
        notizSaveTimeout = setTimeout(function() { saveNotizen(orderId); }, 800);
    }
});
document.addEventListener('blur', function(e) {
    if (e.target.id === 'notizenTextarea') {
        clearTimeout(notizSaveTimeout);
        saveNotizen(orderId);
    }
}, true);

// Beim Laden der Seite
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('orderCompanyContextMenu');
        if (!menu) return;
        if (!menu.contains(e.target)) {
            hideOrderCompanyContextMenu();
        }
    });
    document.addEventListener('contextmenu', function(e) {
        const menu = document.getElementById('orderCompanyContextMenu');
        const target = e.target.closest('.order-company-context-target');
        if (menu && !target) {
            hideOrderCompanyContextMenu();
        }
    });

    loadOrder();
    // loadLogs() wird am Ende von displayOrder() aufgerufen
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
