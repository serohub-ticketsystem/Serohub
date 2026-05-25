<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Firmen-Admin, Admin und Techniker können Kunden verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker' && $userRole !== 'Firmen-Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
  
<div id="main-content" class="kalender-page relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
            </svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Kundenliste</span>
          </div>
        </li>
      </ol>
    </nav>
  <div class="relative col-span-full">
    <div class="">
    <div class="relative">
      <div class="flex flex-col-reverse items-stretch justify-between pb-4 space-y-3 md:flex-row md:items-center md:space-y-0">
        <div class="flex flex-col w-full space-y-3 lg:w-2/3 md:space-y-0 md:flex-row md:items-center">
          <form class="flex-1 w-full md:max-w-sm md:mr-2">
            <label for="default-search"
                   class="text-sm font-medium text-gray-900 sr-only dark:text-white">Suche</label>
            <div class="relative" id="search-wrapper">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg aria-hidden="true" class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none"
                     stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
              </div>
              <input type="search" id="search"
       class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:placeholder-primary-210 dark:text-primary-200 dark:focus:ring-primary-820 dark:focus:border-primary-820 transition-colors"
                     placeholder="Suchen..."> 
            </div>
          </form>
          <button type="button" id="reset-customers-filters-btn" class="inline-flex items-center justify-center p-2 text-sm font-medium text-gray-600 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none ml-1" title="Filter zurücksetzen (Aktiv)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
          </button>
        </div>
        <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full pb-4 md:pb-0 md:w-auto md:flex-row md:items-center md:space-x-3">
          <div class="flex items-center gap-2 mr-3">
            <button type="button" id="viewTable" class="view-toggle p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700" title="Tabellenansicht">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
            <button type="button" id="viewCards" class="view-toggle p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700" title="Card-Ansicht">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
              </svg>
            </button>
          </div>
          <a href="<?php echo BASE_URL; ?>customers/create.php"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Kunde hinzufügen
          </a>
        </div>
      </div>
      <div class="flex flex-wrap pt-1 pb-4 border-t dark:border-gray-700">
        <div class="items-center hidden mt-3 mr-4 text-sm font-medium text-gray-900 md:flex dark:text-white">
          Status:
        </div>
        <div class="flex flex-wrap">
          <div class="flex items-center mt-3 mr-4">
            <input id="aktiv" type="radio" value="aktiv" checked name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600  dark:bg-gray-700 dark:border-gray-600">
            <label for="aktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Aktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="inaktiv" type="radio" value="inaktiv" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600  dark:bg-gray-700 dark:border-gray-600">
            <label for="inaktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Inaktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="gesperrt" type="radio" value="gesperrt" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600  dark:bg-gray-700 dark:border-gray-600">
            <label for="gesperrt" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Gesperrt
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="all" type="radio" value="" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600  dark:bg-gray-700 dark:border-gray-600">
            <label for="all" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Alle
            </label>
          </div>
        </div>
      </div>     

    <!-- Tabellenansicht -->
    <div id="tableView" class="overflow-x-auto">
      <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
        <tr>
<th id="sort-name" data-sort="name" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Name
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-status" data-sort="status" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Status
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-kundennummer" data-sort="kundennummer" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Kundennummer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th class="px-4 py-3 font-semibold">Kontakt</th>
<th class="px-4 py-3 font-semibold">Adresse</th>
<th id="sort-anzahl_benutzer" data-sort="anzahl_benutzer" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Benutzer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
            <th scope="col" class="px-4 py-3 font-semibold">Aktionen</th>
        </tr>
    </thead>
    <tbody id="customersList">
        <tr>
            <td colspan="7" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                <svg class="animate-spin h-8 w-8 text-gray-400 dark:text-gray-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2">Lade Kunden...</p>
            </td>
        </tr>
    </tbody>
      </table>
    </div>
    
    <!-- Card-Ansicht -->
    <div id="cardsView" class="hidden">
      <div id="customerCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">
            <i class="fas fa-spinner fa-spin mr-2"></i> Lade Kunden...
        </div>
      </div>
    </div>
    </div>
    </div>
  </div>
</div>

<style>
#search-wrapper.search-active input {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
.dark #search-wrapper.search-active input {
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}
</style>

<script>
const customersApiUrl = '<?php echo BASE_URL; ?>customers/api/customers.php';
const userRole = '<?php echo addslashes($userRole); ?>';
let selectedCompanyId = null;
let allCustomers = [];
let filteredCustomers = [];
let currentView = 'table'; // 'table' oder 'cards'
let sortColumn = null;
let sortDirection = 'asc'; // 'asc' oder 'desc'

const CUSTOMERS_FILTER_STORAGE_KEY = 'customersIndexFilters';

function getCustomersFiltersState() {
    const searchEl = document.getElementById('search');
    const statusRadio = document.querySelector('input[name="status"]:checked');
    return {
        search: searchEl ? searchEl.value : '',
        status: statusRadio ? statusRadio.value : 'aktiv'
    };
}

function saveCustomersFiltersState() {
    try {
        localStorage.setItem(CUSTOMERS_FILTER_STORAGE_KEY, JSON.stringify(getCustomersFiltersState()));
    } catch (e) {
        console.error('Fehler beim Speichern der Kunden-Filter', e);
    }
}

function restoreCustomersFiltersState() {
    try {
        const raw = localStorage.getItem(CUSTOMERS_FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        const searchEl = document.getElementById('search');
        if (state.search !== undefined && searchEl) searchEl.value = state.search || '';
        if (state.status !== undefined) {
            const radio = document.querySelector(`input[name="status"][value="${state.status}"]`);
            if (radio) radio.checked = true;
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Kunden-Filter', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function updateSearchActiveState() {
        const wrapper = document.getElementById('search-wrapper');
        const searchEl = document.getElementById('search');
        if (!wrapper || !searchEl) return;
        wrapper.classList.toggle('search-active', searchEl.value.trim() !== '');
    }

    restoreCustomersFiltersState();
    updateSearchActiveState();

    // Gespeicherte Ansicht aus localStorage laden
    const savedView = localStorage.getItem('customersView');
    if (savedView === 'table' || savedView === 'cards') {
        currentView = savedView;
    }
    
    const savedSelection = localStorage.getItem('selectedUserOption');
    if (savedSelection) {
        try {
            const data = JSON.parse(savedSelection);
            selectedCompanyId = data.id && data.id !== '0' ? parseInt(data.id) : null;
        } catch (e) {
            console.error('Fehler beim Laden der Firmenauswahl', e);
        }
    }
    
    // View-Toggle Event Listener
    document.getElementById('viewTable').addEventListener('click', function() {
        currentView = 'table';
        localStorage.setItem('customersView', 'table');
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardsView').classList.add('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewCards').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayCustomers(filteredCustomers);
    });
    
    document.getElementById('viewCards').addEventListener('click', function() {
        currentView = 'cards';
        localStorage.setItem('customersView', 'cards');
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardsView').classList.remove('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewTable').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayCustomers(filteredCustomers);
    });
    
    // Ansicht wiederherstellen
    if (currentView === 'cards') {
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardsView').classList.remove('hidden');
        document.getElementById('viewCards').classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewTable').classList.remove('bg-primary-100', 'dark:bg-primary-800');
    } else {
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardsView').classList.add('hidden');
        document.getElementById('viewTable').classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewCards').classList.remove('bg-primary-100', 'dark:bg-primary-800');
    }
    
    window.addEventListener('companyChanged', function(e) {
        selectedCompanyId = e.detail.companyId;
        loadCustomers();
    });
    
    // Suche Event Listener
    document.getElementById('search').addEventListener('input', function() {
        updateSearchActiveState();
        filterCustomers();
        saveCustomersFiltersState();
    });
    
    // Status Radio-Buttons Event Listener
    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            filterCustomers();
            saveCustomersFiltersState();
        });
    });

    // Filter zurücksetzen: Aktiv, Suche leer
    const resetCustomersFiltersBtn = document.getElementById('reset-customers-filters-btn');
    if (resetCustomersFiltersBtn) {
        resetCustomersFiltersBtn.addEventListener('click', function() {
            const aktivRadio = document.querySelector('input[name="status"][value="aktiv"]');
            if (aktivRadio) aktivRadio.checked = true;
            const searchEl = document.getElementById('search');
            if (searchEl) searchEl.value = '';
            updateSearchActiveState();
            saveCustomersFiltersState();
            filterCustomers();
        });
    }
    
    // Sortierung Event Listener für alle sortierbaren Spalten
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortCustomers(column);
        });
    });

    saveCustomersFiltersState();
    
    loadCustomers();
});

function loadCustomers() {
    let url = customersApiUrl;
    const params = [];
    if (selectedCompanyId) {
        params.push('company_id=' + selectedCompanyId);
    }
    
    const statusFilter = document.querySelector('input[name="status"]:checked');
    if (statusFilter && statusFilter.value) {
        params.push('status=' + statusFilter.value);
    }
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(async response => {
            const raw = await response.text();
            if (!raw || !raw.trim()) {
                throw new Error('Leere API-Antwort von customers/api/customers.php');
            }
            try {
                return JSON.parse(raw);
            } catch (parseError) {
                console.error('Ungültige JSON-Antwort (erste 300 Zeichen):', raw.slice(0, 300));
                throw parseError;
            }
        })
        .then(data => {
            if (data.success) {
                allCustomers = data.customers;
                filterCustomers();
            } else {
                console.error('Fehler:', data.error);
                showError('Fehler beim Laden der Kunden');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Kunden');
        });
}

function filterCustomers() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const statusRadio = document.querySelector('input[name="status"]:checked');
    const statusFilter = statusRadio ? statusRadio.value : '';
    
    filteredCustomers = allCustomers.filter(customer => {
        // Suchfilter
        if (searchTerm) {
            const adresseParts = [];
            if (customer.adresse) adresseParts.push(customer.adresse);
            if (customer.plz) adresseParts.push(customer.plz);
            if (customer.ort) adresseParts.push(customer.ort);
            
            const lieferadresseParts = [];
            if (customer.lieferadresse) lieferadresseParts.push(customer.lieferadresse);
            if (customer.liefer_plz) lieferadresseParts.push(customer.liefer_plz);
            if (customer.liefer_ort) lieferadresseParts.push(customer.liefer_ort);
            
            const searchableText = [
                customer.name,
                customer.email,
                customer.telefon,
                customer.company_name,
                customer.status,
                customer.kundennummer,
                customer.anzahl_benutzer ? customer.anzahl_benutzer.toString() : '',
                adresseParts.join(' '),
                lieferadresseParts.join(' ')
            ].filter(Boolean).join(' ').toLowerCase();
            
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        // Status-Filter
        if (statusFilter) {
            if (customer.status !== statusFilter) {
                return false;
            }
        }
        
        return true;
    });
    
    // Sortierung anwenden, falls gesetzt
    if (sortColumn) {
        sortCustomers(sortColumn, false); // false = keine UI-Aktualisierung
    } else {
        // Standard: alphabetisch nach Kundenname (deutsch, Umlaute korrekt)
        filteredCustomers.sort((a, b) => (a.name || '').localeCompare((b.name || ''), 'de', { sensitivity: 'base' }));
    }
    
    displayCustomers(filteredCustomers);
}

function sortCustomers(column, updateUI = true) {
    // Sortierrichtung umschalten, wenn bereits nach dieser Spalte sortiert wird
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }
    
    filteredCustomers.sort((a, b) => {
        let comparison = 0;
        switch (column) {
            case 'name':
                comparison = (a.name || '').localeCompare((b.name || ''), 'de', { sensitivity: 'base' });
                break;
            case 'company_name':
                comparison = (a.company_name || '').localeCompare((b.company_name || ''), 'de', { sensitivity: 'base' });
                break;
            case 'status':
                comparison = (a.status || '').localeCompare((b.status || ''), 'de', { sensitivity: 'base' });
                break;
            case 'anzahl_benutzer': {
                const aNum = parseInt(a.anzahl_benutzer || 0, 10);
                const bNum = parseInt(b.anzahl_benutzer || 0, 10);
                comparison = aNum - bNum;
                break;
            }
            case 'kundennummer':
                comparison = String(a.kundennummer || '').localeCompare(String(b.kundennummer || ''), 'de', {
                    numeric: true,
                    sensitivity: 'base'
                });
                break;
            default:
                return 0;
        }
        return sortDirection === 'asc' ? comparison : -comparison;
    });
    
    if (updateUI) {
        updateSortIcons();
        displayCustomers(filteredCustomers);
    }
}

function updateSortIcons() {
    // Alle Sortier-Icons zurücksetzen
    document.querySelectorAll('[data-sort] .sort-icon').forEach(icon => {
        icon.style.display = 'none';
    });
    
    // Aktuelles Sortier-Icon anzeigen
    if (sortColumn) {
        const th = document.querySelector(`[data-sort="${sortColumn}"]`);
        if (th) {
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.style.display = 'block';
                // Richtung des Icons ändern
                if (sortDirection === 'asc') {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
                }
            }
        }
    }
}

function showError(message) {
    const tbody = document.getElementById('customersList');
    const cardsContainer = document.getElementById('customerCards');
    
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-4 text-center text-red-500">${message}</td></tr>`;
    }
    if (cardsContainer) {
        cardsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 py-8">${message}</div>`;
    }
}

function getLogoUrl(logo) {
    if (!logo || logo.trim() === '') {
        return '<?php echo BASE_URL; ?>assets/images/default-avatar.png';
    }
    if (logo.startsWith('http://') || logo.startsWith('https://')) {
        return logo;
    }
    const baseUrl = '<?php echo BASE_URL; ?>';
    return baseUrl + logo.replace(/^\//, '');
}

function displayCustomers(customers) {
    if (currentView === 'table') {
        displayTableView(customers);
    } else {
        displayCardsView(customers);
    }
}

function displayTableView(customers) {
    const tbody = document.getElementById('customersList');
    
    if (customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Keine Kunden gefunden</td></tr>';
        return;
    }
    
    tbody.innerHTML = customers.map(customer => {
        const logoUrl = getLogoUrl(customer.logo);
        const statusBadge = getStatusBadge(customer.status || 'aktiv');
        
        // Adresse zusammenbauen
        const adresseParts = [];
        if (customer.adresse) adresseParts.push(customer.adresse);
        if (customer.plz) adresseParts.push(customer.plz);
        if (customer.ort) adresseParts.push(customer.ort);
        const adresseText = adresseParts.length > 0 ? adresseParts.join(', ') : '-';
        
        // Lieferadresse zusammenbauen
        const lieferadresseParts = [];
        if (customer.lieferadresse) lieferadresseParts.push(customer.lieferadresse);
        if (customer.liefer_plz) lieferadresseParts.push(customer.liefer_plz);
        if (customer.liefer_ort) lieferadresseParts.push(customer.liefer_ort);
        const lieferadresseText = lieferadresseParts.length > 0 ? lieferadresseParts.join(', ') : null;
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>customers/detail.php?id=${customer.id}'">
                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <div class="flex items-center">
                        <img src="${logoUrl}" alt="${escapeHtml(customer.name)}" class="h-10 w-10 rounded-full object-cover mr-3">
                        <div class="flex flex-col">
                            <span class="text-primary-600 dark:text-primary-400 font-medium">${escapeHtml(customer.name)}</span>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    ${statusBadge}
                </td>
                <td class="px-4 py-3">
                    ${escapeHtml(customer.kundennummer || '-')}
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col text-sm">
                        ${customer.email ? `<span class="text-gray-900 dark:text-white">${escapeHtml(customer.email)}</span>` : '<span class="text-gray-400">-</span>'}
                        ${customer.telefon ? `
                            <span class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.427 14.768 17.2 13.542a1.733 1.733 0 0 0-2.45 0l-.613.613a1.732 1.732 0 0 1-2.45 0l-1.838-1.84a1.735 1.735 0 0 1 0-2.452l.612-.613a1.735 1.735 0 0 0 0-2.452L9.237 5.572a1.6 1.6 0 0 0-2.45 0c-3.223 3.2-1.702 6.896 1.519 10.117 3.22 3.221 6.914 4.745 10.12 1.535a1.601 1.601 0 0 0 0-2.456Z"/>
                                </svg>
                                ${escapeHtml(customer.telefon)}
                            </span>
                        ` : ''}
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col text-sm">
                        <span class="text-gray-900 dark:text-white">${escapeHtml(adresseText)}</span>
                        ${lieferadresseText ? `
                            <span class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h6l2 4m-8-4v8m0-8V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v9h2m8 0H9m4 0h2m4 0h2v-4m0 0h-5m3.5 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm-10 0a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                                </svg>
                                ${escapeHtml(lieferadresseText)}
                            </span>
                        ` : ''}
                    </div>
                </td>
                <td class="px-4 py-3">
                    ${customer.anzahl_benutzer || 0}
                </td>
                <td class="px-4 py-3" onclick="event.stopPropagation()">
                    <div class="flex items-center gap-2">
                        ${userRole !== 'Techniker' && userRole !== 'Firmen-Admin' ? (
                            (customer.status === 'gesperrt') ? 
                                `<button onclick="toggleLockCustomer(${customer.id}, true)" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Entsperren">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                </button>` :
                                `<button onclick="toggleLockCustomer(${customer.id}, false)" class="text-orange-600 hover:text-orange-900 dark:text-orange-400" title="Sperren">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </button>`
                        ) : ''}
                        <a href="<?php echo BASE_URL; ?>customers/detail.php?id=${customer.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details anzeigen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        ${userRole !== 'Techniker' ? `<a href="<?php echo BASE_URL; ?>customers/edit.php?id=${customer.id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="Bearbeiten">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>` : ''}
                        ${userRole !== 'Firmen-Admin' ? `<button onclick="deleteCustomer(${customer.id})" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Löschen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function displayCardsView(customers) {
    const cardsContainer = document.getElementById('customerCards');
    
    if (customers.length === 0) {
        cardsContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">Keine Kunden gefunden</div>';
        return;
    }
    
    cardsContainer.innerHTML = customers.map(customer => {
        const statusBadge = getStatusBadge(customer.status || 'aktiv');
        const logoUrl = getLogoUrl(customer.logo);
        
        // Adresse zusammenbauen
        const adresseParts = [];
        if (customer.adresse) adresseParts.push(customer.adresse);
        if (customer.plz) adresseParts.push(customer.plz);
        if (customer.ort) adresseParts.push(customer.ort);
        const adresseText = adresseParts.length > 0 ? adresseParts.join(', ') : '-';
        
        const isLocked = customer.status === 'gesperrt';
        
        return `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>customers/detail.php?id=${customer.id}'">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <img src="${logoUrl}" alt="${escapeHtml(customer.name)}" class="h-10 w-10 rounded-full object-cover flex-shrink-0">
                        <div class="flex flex-col min-w-0 flex-1 overflow-hidden">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate" title="${escapeHtml(customer.name)}">
                                ${escapeHtml(customer.name)}
                            </h3>
                            ${customer.company_name ? `<span class="text-xs text-gray-500 dark:text-gray-400 truncate" title="${escapeHtml(customer.company_name)}">${escapeHtml(customer.company_name)}</span>` : ''}
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0" onclick="event.stopPropagation()">
                            ${statusBadge}
                            ${userRole !== 'Techniker' && userRole !== 'Firmen-Admin' ? `
                                <a href="#" onclick="event.stopPropagation(); toggleLockCustomer(${customer.id}, ${isLocked}); return false;" class="text-xs ${isLocked ? 'text-green-600 hover:text-green-800 dark:text-green-400' : 'text-orange-600 hover:text-orange-800 dark:text-orange-400'}">
                                    ${isLocked ? 'Entsperren' : 'Sperren'}
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-4 gap-3">
                            <div class="col-span-3">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Kontakt</label>
                                <div class="text-gray-900 dark:text-white text-xs">
                                    ${customer.email ? `<div>${escapeHtml(customer.email)}</div>` : '<span class="text-gray-400">-</span>'}
                                    ${customer.telefon ? `
                                        <div class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.427 14.768 17.2 13.542a1.733 1.733 0 0 0-2.45 0l-.613.613a1.732 1.732 0 0 1-2.45 0l-1.838-1.84a1.735 1.735 0 0 1 0-2.452l.612-.613a1.735 1.735 0 0 0 0-2.452L9.237 5.572a1.6 1.6 0 0 0-2.45 0c-3.223 3.2-1.702 6.896 1.519 10.117 3.22 3.221 6.914 4.745 10.12 1.535a1.601 1.601 0 0 0 0-2.456Z"/>
                                            </svg>
                                            ${escapeHtml(customer.telefon)}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="col-span-1">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Knd-Nr.</label>
                                <div class="text-gray-900 dark:text-white text-xs">${escapeHtml(customer.kundennummer || '-')}</div>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Adresse</label>
                            <div class="text-gray-900 dark:text-white text-xs">${escapeHtml(adresseText)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'aktiv': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aktiv</span>',
        'inaktiv': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Inaktiv</span>',
        'gesperrt': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Gesperrt</span>'
    };
    return badges[status] || badges['aktiv'];
}

function toggleLockCustomer(customerId, isLocked) {
    if (event) {
        event.stopPropagation();
    }
    
    const action = isLocked ? 'entsperren' : 'sperren';
    if (!confirm(`Möchten Sie diesen Kunden wirklich ${action}?`)) {
        return;
    }
    
    const newStatus = isLocked ? 'aktiv' : 'gesperrt';
    
    fetch(customersApiUrl + '?id=' + customerId, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            customer_id: customerId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = newStatus === 'gesperrt' ? 'Kunde wurde gesperrt' : 'Kunde wurde entsperrt';
            if (typeof showToast === 'function') {
                showToast(message, 'success');
            }
            loadCustomers();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Fehler beim Ändern des Status');
    });
}

function deleteCustomer(customerId) {
    event.stopPropagation();
    
    if (!confirm('Möchten Sie diesen Kunden wirklich löschen?')) {
        return;
    }
    
    fetch(customersApiUrl + '?id=' + customerId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Kunde wurde gelöscht', 'success');
            }
            loadCustomers();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Fehler beim Löschen des Kunden');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
