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
          Meine Geräte
        </h1>
        <p class="text-lg text-gray-600 dark:text-primary-210">
          Alle Ihre Geräte im Überblick
        </p>
      </div>

      <!-- Geräte Liste -->
      <div id="geraeteContainer" class="space-y-4">
        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-420 mx-auto"></div>
          <p class="mt-4 text-gray-600 dark:text-primary-210">Lade Geräte...</p>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('geraeteContainer');
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    function getTypeIcon(type) {
        const icons = {
            'computer': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
            'drucker': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>',
            'smartphone': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
            'monitor': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
            'netzwerk': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
            'divers': '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'
        };
        return icons[type] || icons['divers'];
    }
    
    function getTypeLabel(type) {
        const labels = {
            'computer': 'Computer',
            'drucker': 'Drucker',
            'smartphone': 'Smartphone',
            'monitor': 'Monitor',
            'netzwerk': 'Netzwerk',
            'divers': 'Divers'
        };
        return labels[type] || type || 'Unbekannt';
    }
    
    function getStatusBadge(status) {
        if (status === 'aktiv') {
            return '<span class="px-3 py-1 rounded-full text-sm font-semibold bg-primary-1040/20 text-primary-1040 dark:bg-primary-1040/30 dark:text-primary-1040">Aktiv</span>';
        } else {
            return '<span class="px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-800 dark:bg-primary-120 dark:text-primary-210">Inaktiv</span>';
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '-';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async function loadGeraete() {
        try {
            const response = await fetch(baseUrl + 'devices/api/devices.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (response.ok && data.success && data.devices) {
                const devices = data.devices;
                
                if (devices.length === 0) {
                    container.innerHTML = `
                        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-12 border border-gray-200 dark:border-primary-120 text-center">
                            <svg class="w-16 h-16 text-gray-400 dark:text-primary-210 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v5m-3 0h6M4 11h16M5 15h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1Z"/>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-primary-200 mb-2">Keine Geräte gefunden</h3>
                            <p class="text-gray-600 dark:text-primary-210">Sie haben noch keine Geräte registriert.</p>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = devices.map(device => {
                    const manufacturerModel = [device.hersteller, device.modell].filter(Boolean).join(' / ') || '-';
                    return `
                        <div class="block bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-6 border border-gray-200 dark:border-primary-120">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 w-16 h-16 bg-primary-250/20 dark:bg-primary-250/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-8 h-8 text-primary-250 dark:text-primary-250" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        ${getTypeIcon(device.typ)}
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900 dark:text-primary-200 mb-1">
                                                ${escapeHtml(device.name || 'Unbenanntes Gerät')}
                                            </h3>
                                            <p class="text-gray-600 dark:text-primary-210 mb-2">
                                                ${escapeHtml(getTypeLabel(device.typ))} • ${escapeHtml(manufacturerModel)}
                                            </p>
                                        </div>
                                        <div class="sm:ml-4 mb-2 sm:mb-0">
                                            ${getStatusBadge(device.status || 'aktiv')}
                                        </div>
                                    </div>
                                    ${device.standort ? `<p class="text-sm text-gray-500 dark:text-primary-210 mb-2"><span class="font-semibold">Standort:</span> ${escapeHtml(device.standort)}</p>` : ''}
                                    ${device.seriennummer ? `<p class="text-sm text-gray-500 dark:text-primary-210"><span class="font-semibold">Seriennummer:</span> ${escapeHtml(device.seriennummer)}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                throw new Error(data.error || 'Fehler beim Laden der Geräte');
            }
        } catch (error) {
            console.error('Fehler:', error);
            container.innerHTML = `
                <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
                    <p class="text-primary-1080 dark:text-primary-1080">Fehler beim Laden der Geräte. Bitte versuchen Sie es später erneut.</p>
                </div>
            `;
        }
    }
    
    loadGeraete();
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
