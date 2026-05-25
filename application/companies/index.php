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

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Nur Admin und Techniker können Firmen verwalten
if ($userRole !== 'Admin' && $userRole !== 'Techniker') {
    // Sicherstellen, dass keine Ausgabe vorher stattgefunden hat
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    // Absoluten Pfad verwenden
    $redirectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '500.html';
    header('Location: ' . $redirectUrl);
    exit();
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
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Firmen</span>
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
          <button type="button" id="reset-companies-filters-btn" class="inline-flex items-center justify-center p-2 text-sm font-medium text-gray-600 dark:text-primary-210 bg-white dark:bg-primary-50 border border-gray-300 dark:border-primary-120 rounded-lg hover:bg-gray-100 dark:hover:bg-primary-140 focus:outline-none ml-1" title="Filter zurücksetzen (Aktiv)">
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
          <a href="<?php echo BASE_URL; ?>companies/create.php"
                  class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-lg bg-primary-900 hover:bg-primary-950 focus:ring-4 focus:ring-primary-950 focus:outline-none">
            <svg class="h-3.5 w-3.5 mr-1.5 -ml-1" fill="currentColor" viewbox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Firma hinzufügen
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
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="aktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Aktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="inaktiv" type="radio" value="inaktiv" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="inaktiv" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Inaktiv
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="gesperrt" type="radio" value="gesperrt" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
            <label for="gesperrt" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">
              Gesperrt
            </label>
          </div>
          <div class="flex items-center mt-3 mr-4">
            <input id="all" type="radio" value="" name="status"
                   class="w-4 h-4 bg-gray-100 border-gray-300 text-primary-600 dark:bg-gray-700 dark:border-gray-600">
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
    Firmenname
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
<th class="px-4 py-3 font-semibold text-center" title="Hat Wartungsvertrag">
  <span class="inline-flex items-center gap-1">
    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
    <span class="hidden sm:inline">Wartungsvertrag</span>
  </span>
</th>
<th class="px-4 py-3 font-semibold">
  Kontakt
</th>
<th class="px-4 py-3 font-semibold">
  Adresse
</th>
<th id="sort-anzahl_benutzer" data-sort="anzahl_benutzer" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Benutzer
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
<th id="sort-anzahl_kunden" data-sort="anzahl_kunden" class="px-4 py-3 font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
  <div class="flex items-center">
    Kunden
    <svg class="ml-1 w-3 h-3 sort-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
  </div>
</th>
            <th scope="col" class="px-4 py-3 font-semibold">Aktionen</th>
        </tr>
    </thead>
    <tbody id="companiesList">
        <tr>
            <td colspan="9" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mr-2"></i> Lade Firmen...
            </td>
        </tr>
    </tbody>
</table>     
    </div>
    
    <!-- Card-Ansicht -->
    <div id="cardsView" class="hidden">
      <div id="companyCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">
            <i class="fas fa-spinner fa-spin mr-2"></i> Lade Firmen...
        </div>
      </div>
    </div>
    </div>
    </div>
  </div>
</div>
        </div>
      </main>
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
const companiesApiUrl = '<?php echo BASE_URL; ?>companies/api/companies.php';
const userRole = '<?php echo addslashes($userRole); ?>';
const userCompanyId = <?php echo $userCompanyId !== null && $userCompanyId !== '' ? (int)$userCompanyId : 'null'; ?>;
let allCompanies = [];
let filteredCompanies = [];
let currentView = 'table'; // 'table' oder 'cards'
let sortColumn = null;
let sortDirection = 'asc'; // 'asc' oder 'desc'

const COMPANIES_FILTER_STORAGE_KEY = 'companiesIndexFilters';

function getCompaniesFiltersState() {
    const searchEl = document.getElementById('search');
    const statusRadio = document.querySelector('input[name="status"]:checked');
    return {
        search: searchEl ? searchEl.value : '',
        status: statusRadio ? statusRadio.value : 'aktiv'
    };
}

function saveCompaniesFiltersState() {
    try {
        localStorage.setItem(COMPANIES_FILTER_STORAGE_KEY, JSON.stringify(getCompaniesFiltersState()));
    } catch (e) {
        console.error('Fehler beim Speichern der Firmen-Filter', e);
    }
}

function restoreCompaniesFiltersState() {
    try {
        const raw = localStorage.getItem(COMPANIES_FILTER_STORAGE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        const searchEl = document.getElementById('search');
        if (state.search !== undefined && searchEl) searchEl.value = state.search || '';
        if (state.status !== undefined) {
            const radio = document.querySelector(`input[name="status"][value="${state.status}"]`);
            if (radio) radio.checked = true;
        }
    } catch (e) {
        console.error('Fehler beim Wiederherstellen der Firmen-Filter', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function updateSearchActiveState() {
        const wrapper = document.getElementById('search-wrapper');
        const searchEl = document.getElementById('search');
        if (!wrapper || !searchEl) return;
        wrapper.classList.toggle('search-active', searchEl.value.trim() !== '');
    }

    restoreCompaniesFiltersState();
    updateSearchActiveState();

    // Gespeicherte Ansicht aus localStorage laden
    const savedView = localStorage.getItem('companiesView');
    if (savedView === 'table' || savedView === 'cards') {
        currentView = savedView;
    }
    
    // View-Toggle Event Listener
    document.getElementById('viewTable').addEventListener('click', function() {
        currentView = 'table';
        localStorage.setItem('companiesView', 'table');
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardsView').classList.add('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewCards').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayCompanies(filteredCompanies);
    });
    
    document.getElementById('viewCards').addEventListener('click', function() {
        currentView = 'cards';
        localStorage.setItem('companiesView', 'cards');
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardsView').classList.remove('hidden');
        this.classList.add('bg-primary-100', 'dark:bg-primary-800');
        document.getElementById('viewTable').classList.remove('bg-primary-100', 'dark:bg-primary-800');
        displayCompanies(filteredCompanies);
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
    
    // Suche Event Listener
    document.getElementById('search').addEventListener('input', function() {
        updateSearchActiveState();
        filterCompanies();
        saveCompaniesFiltersState();
    });
    
    // Status Radio-Buttons Event Listener
    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            filterCompanies();
            saveCompaniesFiltersState();
        });
    });

    // Filter zurücksetzen: Aktiv, Suche leer
    const resetCompaniesFiltersBtn = document.getElementById('reset-companies-filters-btn');
    if (resetCompaniesFiltersBtn) {
        resetCompaniesFiltersBtn.addEventListener('click', function() {
            const aktivRadio = document.querySelector('input[name="status"][value="aktiv"]');
            if (aktivRadio) aktivRadio.checked = true;
            const searchEl = document.getElementById('search');
            if (searchEl) searchEl.value = '';
            updateSearchActiveState();
            saveCompaniesFiltersState();
            filterCompanies();
        });
    }
    
    // Sortierung Event Listener für alle sortierbaren Spalten
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            sortCompanies(column);
        });
    });

    saveCompaniesFiltersState();
    
    loadCompanies();
});

function loadCompanies() {
    fetch(companiesApiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allCompanies = data.companies;
                filterCompanies();
            } else {
                console.error('Fehler beim Laden der Firmen:', data.error);
                showError('Fehler beim Laden der Firmen');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showError('Fehler beim Laden der Firmen');
        });
}

function filterCompanies() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const statusRadio = document.querySelector('input[name="status"]:checked');
    const statusFilter = statusRadio ? statusRadio.value : '';
    
    filteredCompanies = allCompanies.filter(company => {
        // Suchfilter
        if (searchTerm) {
            const searchableText = [
                company.name,
                company.domain,
                company.kundennummer,
                company.status
            ].filter(Boolean).join(' ').toLowerCase();
            
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }
        
        // Status-Filter
        if (statusFilter) {
            if (company.status !== statusFilter) {
                return false;
            }
        }
        
        return true;
    });
    
    // Sortierung anwenden, falls gesetzt
    if (sortColumn) {
        sortCompanies(sortColumn, false); // false = keine UI-Aktualisierung
    }
    
    displayCompanies(filteredCompanies);
}

function sortCompanies(column, updateUI = true) {
    // Sortierrichtung umschalten, wenn bereits nach dieser Spalte sortiert wird
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }
    
    filteredCompanies.sort((a, b) => {
        let aValue, bValue;
        
        // Werte für Sortierung extrahieren
        switch(column) {
            case 'name':
                aValue = (a.name || '').toLowerCase();
                bValue = (b.name || '').toLowerCase();
                break;
            case 'domain':
                aValue = (a.domain || '').toLowerCase();
                bValue = (b.domain || '').toLowerCase();
                break;
            case 'kundennummer':
                aValue = (a.kundennummer || '').toLowerCase();
                bValue = (b.kundennummer || '').toLowerCase();
                break;
            case 'status':
                aValue = (a.status || '').toLowerCase();
                bValue = (b.status || '').toLowerCase();
                break;
            case 'anzahl_benutzer':
                aValue = parseInt(a.anzahl_benutzer || 0);
                bValue = parseInt(b.anzahl_benutzer || 0);
                break;
            case 'anzahl_kunden':
                aValue = parseInt(a.anzahl_kunden || 0);
                bValue = parseInt(b.anzahl_kunden || 0);
                break;
            case 'erstellt_datum':
                aValue = new Date(a.erstellt_datum || 0);
                bValue = new Date(b.erstellt_datum || 0);
                break;
            default:
                return 0;
        }
        
        // Vergleich
        let comparison = 0;
        if (aValue < bValue) {
            comparison = -1;
        } else if (aValue > bValue) {
            comparison = 1;
        }
        
        return sortDirection === 'asc' ? comparison : -comparison;
    });
    
    if (updateUI) {
        updateSortIcons();
        displayCompanies(filteredCompanies);
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
    const tbody = document.getElementById('companiesList');
    const cardsContainer = document.getElementById('companyCards');
    
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-4 text-center text-red-500">${message}</td></tr>`;
    }
    if (cardsContainer) {
        cardsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 py-8">${message}</div>`;
    }
}

function displayCompanies(companies) {
    if (currentView === 'table') {
        displayTableView(companies);
    } else {
        displayCardsView(companies);
    }
}

function displayTableView(companies) {
    const tbody = document.getElementById('companiesList');
    
    if (companies.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Keine Firmen gefunden</td></tr>`;
        return;
    }
    
    tbody.innerHTML = companies.map(company => {
        const statusBadge = getStatusBadge(company.status || 'aktiv');
        const logoUrl = company.logo 
            ? (company.logo.startsWith('http') ? company.logo : '<?php echo BASE_URL; ?>' + company.logo)
            : '<?php echo BASE_URL; ?>assets/images/default-avatar.png';
        
        // Adresse zusammenbauen
        const adresseParts = [];
        if (company.adresse) adresseParts.push(company.adresse);
        if (company.plz) adresseParts.push(company.plz);
        if (company.ort) adresseParts.push(company.ort);
        const adresseText = adresseParts.length > 0 ? adresseParts.join(', ') : '-';
        
        // Lieferadresse zusammenbauen
        const lieferadresseParts = [];
        if (company.lieferadresse) lieferadresseParts.push(company.lieferadresse);
        if (company.liefer_plz) lieferadresseParts.push(company.liefer_plz);
        if (company.liefer_ort) lieferadresseParts.push(company.liefer_ort);
        const lieferadresseText = lieferadresseParts.length > 0 ? lieferadresseParts.join(', ') : null;
        
        const isLocked = company.status === 'gesperrt';
        const isPrimary = company.is_primary == 1 || company.is_primary === true;
        
        return `
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>companies/detail.php?id=${company.id}'">
                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                    <div class="flex items-center">
                        <img src="${logoUrl}" alt="${escapeHtml(company.name)}" class="h-10 w-10 rounded-full object-cover mr-3">
                        <div class="flex flex-col">
                            <span class="text-primary-600 dark:text-primary-400 font-medium">${escapeHtml(company.name)}</span>
                            ${isPrimary ? `<span class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold">Primärfirma</span>` : ''}
                            ${company.domain ? `<span class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(company.domain)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    ${statusBadge}
                </td>
                <td class="px-4 py-3">
                    ${escapeHtml(company.kundennummer || '-')}
                </td>
                <td class="px-4 py-3 text-center">
                    ${(company.hat_wartungsvertrag == 1 || company.hat_wartungsvertrag === true) ? `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" title="Hat Wartungsvertrag">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="hidden sm:inline">Ja</span>
                    </span>
                    ` : `
                    <span class="inline-flex items-center justify-center w-8 h-6 text-gray-400 dark:text-gray-500" title="Kein Wartungsvertrag">—</span>
                    `}
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col text-sm">
                        ${company.email ? `<span class="text-gray-900 dark:text-white">${escapeHtml(company.email)}</span>` : '<span class="text-gray-400">-</span>'}
                        ${company.telefonnummer ? `
                            <span class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.427 14.768 17.2 13.542a1.733 1.733 0 0 0-2.45 0l-.613.613a1.732 1.732 0 0 1-2.45 0l-1.838-1.84a1.735 1.735 0 0 1 0-2.452l.612-.613a1.735 1.735 0 0 0 0-2.452L9.237 5.572a1.6 1.6 0 0 0-2.45 0c-3.223 3.2-1.702 6.896 1.519 10.117 3.22 3.221 6.914 4.745 10.12 1.535a1.601 1.601 0 0 0 0-2.456Z"/>
                                </svg>
                                ${escapeHtml(company.telefonnummer)}
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
                    ${company.anzahl_benutzer || 0}
                </td>
                <td class="px-4 py-3">
                    ${company.anzahl_kunden || 0}
                </td>
                <td class="px-4 py-3" onclick="event.stopPropagation()">
                    <div class="flex items-center space-x-2">
                        ${userRole !== 'Techniker' && company.id != userCompanyId && !isPrimary ? (
                            isLocked ? 
                                `<button onclick="toggleLockCompany(${company.id}, true)" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Entsperren">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                </button>` :
                                `<button onclick="toggleLockCompany(${company.id}, false)" class="text-orange-600 hover:text-orange-900 dark:text-orange-400" title="Sperren">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </button>`
                        ) : ''}
                        <a href="<?php echo BASE_URL; ?>companies/detail.php?id=${company.id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Details anzeigen">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        ${userRole !== 'Techniker' ? `<a href="<?php echo BASE_URL; ?>companies/edit.php?id=${company.id}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400" title="Bearbeiten">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>` : ''}
                        ${company.id != userCompanyId && !isPrimary ? `<button onclick="deleteCompany(${company.id})" class="text-red-600 hover:text-red-900 dark:text-red-400" title="${userRole === 'Techniker' ? 'Auf inaktiv setzen' : 'Löschen'}">
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

function displayCardsView(companies) {
    const cardsContainer = document.getElementById('companyCards');
    
    if (companies.length === 0) {
        cardsContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-8">Keine Firmen gefunden</div>';
        return;
    }
    
    cardsContainer.innerHTML = companies.map(company => {
        const statusBadge = getStatusBadge(company.status || 'aktiv');
        const logoUrl = company.logo 
            ? (company.logo.startsWith('http') ? company.logo : '<?php echo BASE_URL; ?>' + company.logo)
            : '<?php echo BASE_URL; ?>assets/images/default-avatar.png';
        
        // Adresse zusammenbauen
        const adresseParts = [];
        if (company.adresse) adresseParts.push(company.adresse);
        if (company.plz) adresseParts.push(company.plz);
        if (company.ort) adresseParts.push(company.ort);
        const adresseText = adresseParts.length > 0 ? adresseParts.join(', ') : '-';
        
        // Lieferadresse zusammenbauen
        const lieferadresseParts = [];
        if (company.lieferadresse) lieferadresseParts.push(company.lieferadresse);
        if (company.liefer_plz) lieferadresseParts.push(company.liefer_plz);
        if (company.liefer_ort) lieferadresseParts.push(company.liefer_ort);
        const lieferadresseText = lieferadresseParts.length > 0 ? lieferadresseParts.join(', ') : null;
        
        const isLocked = company.status === 'gesperrt';
        const isPrimary = company.is_primary == 1 || company.is_primary === true;
        
        return `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='<?php echo BASE_URL; ?>companies/detail.php?id=${company.id}'">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <img src="${logoUrl}" alt="${escapeHtml(company.name)}" class="h-10 w-10 rounded-full object-cover flex-shrink-0">
                        <div class="flex flex-col min-w-0 flex-1 overflow-hidden">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate" title="${escapeHtml(company.name)}">
                                ${escapeHtml(company.name)}
                            </h3>
                            ${isPrimary ? `<span class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold">Primärfirma</span>` : ''}
                            ${company.domain ? `<span class="text-xs text-gray-500 dark:text-gray-400 truncate" title="${escapeHtml(company.domain)}">${escapeHtml(company.domain)}</span>` : ''}
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0" onclick="event.stopPropagation()">
                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                ${statusBadge}
                                ${(company.hat_wartungsvertrag == 1 || company.hat_wartungsvertrag === true) ? `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" title="Hat Wartungsvertrag">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Wartungsvertrag
                                </span>
                                ` : ''}
                            </div>
                            ${userRole !== 'Techniker' && company.id != userCompanyId && !isPrimary ? `
                                <a href="#" onclick="event.stopPropagation(); toggleLockCompany(${company.id}, ${isLocked}); return false;" class="text-xs ${isLocked ? 'text-green-600 hover:text-green-800 dark:text-green-400' : 'text-orange-600 hover:text-orange-800 dark:text-orange-400'}">
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
                                    ${company.email ? `<div>${escapeHtml(company.email)}</div>` : '<span class="text-gray-400">-</span>'}
                                    ${company.telefonnummer ? `
                                        <div class="text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.427 14.768 17.2 13.542a1.733 1.733 0 0 0-2.45 0l-.613.613a1.732 1.732 0 0 1-2.45 0l-1.838-1.84a1.735 1.735 0 0 1 0-2.452l.612-.613a1.735 1.735 0 0 0 0-2.452L9.237 5.572a1.6 1.6 0 0 0-2.45 0c-3.223 3.2-1.702 6.896 1.519 10.117 3.22 3.221 6.914 4.745 10.12 1.535a1.601 1.601 0 0 0 0-2.456Z"/>
                                            </svg>
                                            ${escapeHtml(company.telefonnummer)}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="col-span-1">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Knd-Nr.</label>
                                <div class="text-gray-900 dark:text-white text-xs">${escapeHtml(company.kundennummer || '-')}</div>
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

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric'
    });
}

function toggleLockCompany(companyId, isLocked) {
    event.stopPropagation();
    
    const action = isLocked ? 'entsperren' : 'sperren';
    if (!confirm(`Möchten Sie diese Firma wirklich ${action}?`)) {
        return;
    }
    
    const newStatus = isLocked ? 'aktiv' : 'gesperrt';
    
    fetch(companiesApiUrl + '?id=' + companyId, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            company_id: companyId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = newStatus === 'gesperrt' ? 'Firma wurde gesperrt' : 'Firma wurde entsperrt';
            if (typeof showToast === 'function') {
                showToast(message, 'success');
            }
            loadCompanies();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        alert('Fehler beim Ändern des Status');
    });
}

function deleteCompany(companyId) {
    const confirmMessage = userRole === 'Techniker' 
        ? 'Möchten Sie diese Firma wirklich auf inaktiv setzen?' 
        : 'Möchten Sie diese Firma wirklich löschen?';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(companiesApiUrl + '?id=' + companyId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Firma wurde gelöscht', 'success');
            }
            loadCompanies();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        const errorMessage = userRole === 'Techniker' 
            ? 'Fehler beim Setzen der Firma auf inaktiv' 
            : 'Fehler beim Löschen der Firma';
        alert(errorMessage);
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
