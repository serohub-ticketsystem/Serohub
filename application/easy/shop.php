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

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$userRole = null;

try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'] ?? null;
} catch (PDOException $e) {
    error_log("Easy Shop: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
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
          Shop
        </h1>
        <p class="text-lg text-gray-600 dark:text-primary-210">
          Bestellen Sie neue Geräte oder Zubehör
        </p>
      </div>

      <?php if ($userRole === 'Kunde'): ?>
        <!-- Für Kunden: Link zum Shop -->
        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-12 border border-gray-200 dark:border-primary-120 text-center">
          <svg class="w-16 h-16 text-primary-250 dark:text-primary-250 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>
          </svg>
          <h3 class="text-xl font-semibold text-gray-900 dark:text-primary-200 mb-2">Zum Shop</h3>
          <p class="text-gray-600 dark:text-primary-210 mb-6">Besuchen Sie unseren Shop, um neue Geräte oder Zubehör zu bestellen.</p>
          <a href="<?php echo BASE_URL; ?>shop/" class="inline-block bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors text-lg">
            Zum Shop öffnen
          </a>
        </div>
      <?php else: ?>
        <!-- Für andere Rollen: Bestellungen anzeigen -->
        <div id="bestellungenContainer" class="space-y-4">
          <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-420 mx-auto"></div>
            <p class="mt-4 text-gray-600 dark:text-primary-210">Lade Bestellungen...</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php if ($userRole !== 'Kunde'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('bestellungenContainer');
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    function getStatusBadge(status) {
        const neuStyle = { text: 'Neu', class: 'bg-primary-1060/20 text-primary-1060 dark:bg-primary-1060/30 dark:text-primary-1060' };
        const statusMap = {
            'Neu': neuStyle,
            'neu': neuStyle,
            'offen': neuStyle,
            'Offen': neuStyle,
            'in Bearbeitung': { text: 'In Bearbeitung', class: 'bg-primary-1100/20 text-primary-1100 dark:bg-primary-1100/30 dark:text-primary-1100' },
            'abgeschlossen': { text: 'Abgeschlossen', class: 'bg-primary-1040/20 text-primary-1040 dark:bg-primary-1040/30 dark:text-primary-1040' },
            'storniert': { text: 'Storniert', class: 'bg-primary-1080/20 text-primary-1080 dark:bg-primary-1080/30 dark:text-primary-1080' }
        };
        
        const statusInfo = statusMap[status] || { text: status, class: 'bg-gray-100 text-gray-800 dark:bg-primary-120 dark:text-primary-210' };
        return `<span class="px-3 py-1 rounded-full text-sm font-semibold ${statusInfo.class}">${statusInfo.text}</span>`;
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
    
    function escapeHtml(text) {
        if (!text) return '-';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async function loadBestellungen() {
        try {
            const response = await fetch(baseUrl + 'orders/api/orders.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (response.ok && data.success && data.orders) {
                const orders = data.orders;
                
                if (orders.length === 0) {
                    container.innerHTML = `
                        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-12 border border-gray-200 dark:border-primary-120 text-center">
                            <svg class="w-16 h-16 text-gray-400 dark:text-primary-210 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-primary-200 mb-2">Keine Bestellungen gefunden</h3>
                            <p class="text-gray-600 dark:text-primary-210 mb-6">Sie haben noch keine Bestellungen aufgegeben.</p>
                            <a href="${baseUrl}orders/create.php" class="inline-block bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-3 px-8 rounded-lg transition-colors">
                                Neue Bestellung
                            </a>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = orders.map(order => {
                    return `
                        <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-6 border border-gray-200 dark:border-primary-120">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-primary-200 mb-2">
                                        Bestellung ${escapeHtml(order.bestellnummer || order.id || '')}
                                    </h3>
                                    ${order.beschreibung ? `<p class="text-gray-600 dark:text-primary-210 mb-3 line-clamp-2">${escapeHtml(order.beschreibung)}</p>` : ''}
                                </div>
                                <div class="sm:ml-4 mb-4 sm:mb-0">
                                    ${getStatusBadge(order.status || 'Neu')}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-primary-210 mb-4">
                                ${order.erstellt_datum ? `<div><span class="font-semibold">Erstellt:</span> ${formatDate(order.erstellt_datum)}</div>` : ''}
                                ${order.gesamtpreis ? `<div><span class="font-semibold">Gesamtpreis:</span> ${escapeHtml(order.gesamtpreis)} €</div>` : ''}
                            </div>
                            <div class="pt-4 border-t border-gray-200 dark:border-primary-230">
                                <a href="${baseUrl}orders/detail.php?id=${order.id}" class="inline-block bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-2 px-6 rounded-lg transition-colors text-sm">
                                    Details anzeigen
                                </a>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                throw new Error(data.error || 'Fehler beim Laden der Bestellungen');
            }
        } catch (error) {
            console.error('Fehler:', error);
            container.innerHTML = `
                <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120 text-center">
                    <p class="text-primary-1080 dark:text-primary-1080">Fehler beim Laden der Bestellungen. Bitte versuchen Sie es später erneut.</p>
                    <a href="${baseUrl}orders/" class="mt-4 inline-block text-primary-660 hover:text-primary-680 dark:text-primary-660 dark:hover:text-primary-680">
                        Zur Bestellungsübersicht
                    </a>
                </div>
            `;
        }
    }
    
    loadBestellungen();
});
</script>
<?php endif; ?>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
