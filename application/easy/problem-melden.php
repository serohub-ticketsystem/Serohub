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
    <div class="max-w-3xl mx-auto">
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
          Problem melden
        </h1>
        <p class="text-lg text-gray-600 dark:text-primary-210">
          Beschreiben Sie Ihr Problem so genau wie möglich
        </p>
      </div>

      <!-- Formular -->
      <div class="bg-white dark:bg-primary-100 rounded-2xl shadow-lg p-8 border border-gray-200 dark:border-primary-120">
        <form id="problemForm" class="space-y-6">
          <div>
            <label for="titel" class="block text-lg font-semibold text-gray-900 dark:text-primary-200 mb-2">
              Betreff
            </label>
            <input 
              type="text" 
              id="titel" 
              name="titel" 
              required
              maxlength="50"
              placeholder="z.B. Drucker funktioniert nicht"
              class="w-full px-4 py-3 text-lg border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-360 focus:border-primary-360"
            >
            <p class="mt-1 text-sm text-gray-500 dark:text-primary-210">Kurze Beschreibung des Problems (max. 50 Zeichen)</p>
          </div>

          <div>
            <label for="beschreibung" class="block text-lg font-semibold text-gray-900 dark:text-primary-200 mb-2">
              Beschreibung
            </label>
            <textarea 
              id="beschreibung" 
              name="beschreibung" 
              required
              rows="8"
              placeholder="Beschreiben Sie Ihr Problem ausführlich..."
              class="w-full px-4 py-3 text-lg border border-gray-300 dark:border-primary-320 rounded-lg bg-white dark:bg-primary-300 text-gray-900 dark:text-primary-200 focus:ring-2 focus:ring-primary-360 focus:border-primary-360"
            ></textarea>
          </div>

          <div class="flex flex-col sm:flex-row gap-4 pt-4">
            <button 
              type="submit" 
              class="flex-1 bg-primary-420 hover:bg-primary-440 text-primary-480 dark:text-primary-480 font-semibold py-4 px-8 rounded-lg transition-colors text-lg"
            >
              Problem melden
            </button>
            <a 
              href="<?php echo BASE_URL; ?>easy/" 
              class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-primary-540 dark:hover:bg-primary-600 text-gray-900 dark:text-primary-580 font-semibold py-4 px-8 rounded-lg transition-colors text-lg text-center border dark:border-primary-560"
            >
              Abbrechen
            </a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('problemForm');
    const baseUrl = '<?php echo BASE_URL; ?>';
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Wird gesendet...';
        
        const titel = document.getElementById('titel').value.trim();
        const beschreibung = document.getElementById('beschreibung').value.trim();
        
        if (!titel || !beschreibung) {
            if (typeof showToast === 'function') {
                showToast('Bitte füllen Sie alle Felder aus', 'error');
            } else {
                alert('Bitte füllen Sie alle Felder aus');
            }
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }
        
        try {
            const response = await fetch(baseUrl + 'tickets/api/tickets.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    titel: titel,
                    beschreibung: beschreibung,
                    prioritaet: 'normal'
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast('Problem erfolgreich gemeldet', 'success');
                } else {
                    alert('Problem erfolgreich gemeldet');
                }
                
                // Zurück zur Übersicht nach kurzer Verzögerung
                setTimeout(() => {
                    window.location.href = baseUrl + 'easy/meine-probleme.php';
                }, 1500);
            } else {
                throw new Error(data.error || 'Fehler beim Melden des Problems');
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (typeof showToast === 'function') {
                showToast(error.message || 'Fehler beim Melden des Problems', 'error');
            } else {
                alert(error.message || 'Fehler beim Melden des Problems');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
