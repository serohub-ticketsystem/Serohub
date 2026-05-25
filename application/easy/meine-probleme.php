<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';

// Sidebar und Navigation für Easy Mode ausblenden
echo '<style>
.lg\\:ms-64 { margin-left: 0 !important; }
nav.fixed { display: none !important; }
nav.fixed + div { display: none !important; }
#sidebar { display: none !important; }
.fixed.top-16 { display: none !important; }
</style>';
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 pt-8 overflow-hidden">
  <main class="pt-8 pb-8 px-4">
    <div class="max-w-5xl mx-auto">
      <!-- Zurück Button -->
      <div class="mb-6">
        <a href="<?php echo BASE_URL; ?>easy/" class="inline-flex items-center text-primary-660 hover:text-primary-680 dark:text-primary-660 dark:hover:text-primary-680">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Zurück zur Übersicht
        </a>
      </div>

      <!-- Header -->
      <div class="mb-8 text-center">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-primary-200 mb-2">
          Meine Probleme
        </h1>
        <p class="text-lg text-gray-600 dark:text-primary-210">
          Alle Ihre gemeldeten Probleme im Überblick
        </p>
      </div>

      <!-- Neue Problem melden Button -->
      <div class="mb-6 text-center">
        <a href="<?php echo BASE_URL; ?>easy/problem-melden.php" class="inline-block bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors text-lg">
          Neues Problem melden
        </a>
      </div>

      <!-- Probleme Liste -->
      <div id="problemeContainer" class="space-y-4">
        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-420 mx-auto"></div>
          <p class="mt-4 text-gray-600 dark:text-primary-210">Lade Probleme...</p>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('problemeContainer');
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    function getStatusBadge(status) {
        const statusMap = {
            'neu': { text: 'Neu', class: 'bg-primary-1100/20 text-primary-1100 dark:bg-primary-1100/30 dark:text-primary-1100' },
            'in Bearbeitung': { text: 'In Bearbeitung', class: 'bg-primary-1060/20 text-primary-1060 dark:bg-primary-1060/30 dark:text-primary-1060' },
            'Warten auf Kunde': { text: 'Warten auf Kunde', class: 'bg-primary-1060/20 text-primary-1060 dark:bg-primary-1060/30 dark:text-primary-1060' },
            'Gelöst': { text: 'Gelöst', class: 'bg-primary-1040/20 text-primary-1040 dark:bg-primary-1040/30 dark:text-primary-1040' },
            'Geschlossen': { text: 'Geschlossen', class: 'bg-gray-100 text-gray-800 dark:bg-primary-120 dark:text-primary-210' },
            'Geplant': { text: 'Geplant', class: 'bg-primary-250/20 text-primary-250 dark:bg-primary-250/30 dark:text-primary-250' }
        };
        
        const statusInfo = statusMap[status] || { text: status, class: 'bg-gray-100 text-gray-800 dark:bg-primary-120 dark:text-primary-210' };
        return `<span class="px-3 py-1 rounded-full text-sm font-semibold ${statusInfo.class}">${statusInfo.text}</span>`;
    }
    
    function formatDate(dateString) {
        if (!dateString) return null;
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function formatDateShort(dateString) {
        if (!dateString) return null;
        const date = new Date(dateString);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        // Heute
        if (date.toDateString() === today.toDateString()) {
            return 'Heute, ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }
        // Morgen
        if (date.toDateString() === tomorrow.toDateString()) {
            return 'Morgen, ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }
        
        return date.toLocaleDateString('de-DE', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async function loadProbleme() {
        try {
            const response = await fetch(baseUrl + 'tickets/api/tickets.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (response.ok && data.success && data.tickets) {
                const tickets = data.tickets;
                
                if (tickets.length === 0) {
                    container.innerHTML = `
                        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-12 border border-gray-200 dark:border-primary-120 text-center">
                            <svg class="w-16 h-16 text-gray-400 dark:text-primary-210 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-primary-200 mb-2">Keine Probleme gefunden</h3>
                            <p class="text-gray-600 dark:text-primary-210 mb-6">Sie haben noch keine Probleme gemeldet.</p>
                            <a href="${baseUrl}easy/problem-melden.php" class="inline-block bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
                                Erstes Problem melden
                            </a>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = tickets.map(ticket => {
                    const erstelltDatum = formatDate(ticket.erstellt_datum);
                    const geplantDatum = formatDateShort(ticket.geplant_datum);
                    const faelligDatum = formatDateShort(ticket.faellig_datum);
                    const zugewiesenName = ticket.zugewiesen_vorname && ticket.zugewiesen_nachname 
                        ? `${escapeHtml(ticket.zugewiesen_vorname)} ${escapeHtml(ticket.zugewiesen_nachname)}`
                        : ticket.zugewiesen_vorname || ticket.zugewiesen_nachname || null;
                    
                    return `
                        <div class="block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-6 border border-gray-200 dark:border-primary-120">
                            <!-- Header mit Titel und Badges -->
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4 gap-3">
                                <div class="flex-1">
                                    <div class="flex items-start gap-3 mb-2">
                                        <h3 class="text-2xl font-bold text-gray-900 dark:text-primary-200 flex-1">
                                            ${escapeHtml(ticket.titel || 'Ohne Titel')}
                                        </h3>
                                        <div class="flex flex-wrap gap-2">
                                            ${getStatusBadge(ticket.status || 'neu')}
                                        </div>
                                    </div>
                                    <p class="text-gray-600 dark:text-primary-210 text-lg mb-4 line-clamp-3">
                                        ${escapeHtml(ticket.beschreibung || 'Keine Beschreibung')}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Informationen Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 p-4 bg-gray-50 dark:bg-primary-140 rounded-lg">
                                <!-- Ticket Nummer -->
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-primary-250/20 dark:bg-primary-250/30 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-250 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-primary-210 font-medium">Ticket-Nummer</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-primary-200">${escapeHtml(ticket.ticket_nummer || '-')}</div>
                                    </div>
                                </div>
                                
                                <!-- Erstellt am -->
                                ${erstelltDatum ? `
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-primary-1040/20 dark:bg-primary-1040/30 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-1040 dark:text-primary-1040" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-primary-210 font-medium">Erstellt am</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-primary-200">${erstelltDatum}</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Geplant für -->
                                ${geplantDatum ? `
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-primary-250/20 dark:bg-primary-250/30 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-250 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-primary-210 font-medium">Geplant für</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-primary-200">${geplantDatum}</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Fällig am -->
                                ${faelligDatum ? `
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-primary-1080/20 dark:bg-primary-1080/30 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-1080 dark:text-primary-1080" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-primary-210 font-medium">Fällig am</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-primary-200">${faelligDatum}</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Kommt vorbei -->
                                ${zugewiesenName ? `
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-primary-1100/20 dark:bg-primary-1100/30 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-1100 dark:text-primary-1100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-primary-210 font-medium">Kommt vorbei</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-primary-200">${zugewiesenName}</div>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                throw new Error(data.error || 'Fehler beim Laden der Probleme');
            }
        } catch (error) {
            console.error('Fehler:', error);
            container.innerHTML = `
                <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
                    <p class="text-primary-1080 dark:text-primary-1080">Fehler beim Laden der Probleme. Bitte versuchen Sie es später erneut.</p>
                </div>
            `;
        }
    }
    
    loadProbleme();
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
