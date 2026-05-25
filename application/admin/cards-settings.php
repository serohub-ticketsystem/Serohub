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
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userRole = $user['rolle'];
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Admin kann auf diese Seite zugreifen
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

// Verfügbare Easy Mode Cards definieren
$availableCards = [
    'easy_problem_melden' => [
        'title' => 'Problem melden',
        'description' => 'Ermöglicht Benutzern, neue Probleme oder Störungen zu melden',
        'icon' => 'problem'
    ],
    'easy_meine_probleme' => [
        'title' => 'Meine Probleme',
        'description' => 'Zeigt alle gemeldeten Probleme und deren Status an',
        'icon' => 'tickets'
    ],
    'easy_meine_geraete' => [
        'title' => 'Meine Geräte',
        'description' => 'Zeigt alle registrierten Geräte und deren Informationen an',
        'icon' => 'devices'
    ],
    'easy_shop' => [
        'title' => 'Shop',
        'description' => 'Ermöglicht die Bestellung neuer Geräte oder Zubehör',
        'icon' => 'shop'
    ]
];

// Aktuelle Card-Einstellungen aus Datenbank laden
$cardSettings = [];
foreach ($availableCards as $cardId => $cardInfo) {
    $cardSettings[$cardId] = true; // Standard: aktiviert
}

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'easy_mode_cards_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['setting_value'])) {
        $enabledCards = json_decode($result['setting_value'], true);
        if (is_array($enabledCards)) {
            // Nur die aktivierten Cards setzen
            foreach ($availableCards as $cardId => $cardInfo) {
                $cardSettings[$cardId] = isset($enabledCards[$cardId]) ? (bool)$enabledCards[$cardId] : true;
            }
        }
    }
} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht, das ist ok
    error_log("Fehler beim Laden der Card-Einstellungen: " . $e->getMessage());
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
        <!-- Header -->
        <div class="col-span-full mx-4 mt-4">
          <div class="mb-4">
            <nav class="mb-4 flex" aria-label="Breadcrumb">
              <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-660 dark:text-primary-210 dark:hover:text-primary-200">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 dark:text-primary-220 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-660 dark:text-primary-210 dark:hover:text-primary-200 md:ms-2">Administration</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 dark:text-primary-220 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-primary-220 md:ms-2">Easy Mode Cards Einstellungen</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-primary-200">Easy Mode Cards Einstellungen</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-primary-210">Konfigurieren Sie, welche Cards im Easy Mode angezeigt werden sollen</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-primary-120 dark:bg-primary-100">
            
            <form id="cardsSettingsForm" class="space-y-6">
              
              <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Easy Mode Einstellungen</h2>
                <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-primary-120 dark:bg-primary-140">
                  <div class="flex items-start gap-4">
                    <div class="flex items-center h-5 mt-0.5">
                      <input type="checkbox" 
                             id="easy_mode_tickets_clickable" 
                             name="easy_mode_tickets_clickable"
                             value="1"
                             <?php 
                             $ticketsClickable = true; // Standard: klickbar
                             try {
                                 $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'easy_mode_tickets_clickable'");
                                 $stmt->execute();
                                 $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                 if ($result && $result['setting_value'] === '0') {
                                     $ticketsClickable = false;
                                 }
                             } catch (PDOException $e) {
                                 // Standard verwenden
                             }
                             echo $ticketsClickable ? 'checked' : ''; 
                             ?>
                             class="h-4 w-4 rounded border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600">
                    </div>
                    <div class="flex-1">
                      <label for="easy_mode_tickets_clickable" class="text-sm font-medium text-gray-900 dark:text-primary-200 cursor-pointer">
                        Tickets in Easy Mode klickbar machen
                      </label>
                      <p class="mt-1 text-xs text-gray-600 dark:text-primary-210">
                        Wenn aktiviert, können Benutzer im Easy Mode auf Ticket-Karten klicken, um die Details anzuzeigen. Wenn deaktiviert, sind die Karten nur zur Ansicht.
                      </p>
                    </div>
                  </div>
                </div>
                
                <h2 class="text-lg font-semibold text-gray-900 dark:text-primary-200 mb-4">Easy Mode Cards</h2>
                <p class="text-sm text-gray-600 dark:text-primary-210 mb-4">
                  Aktivieren oder deaktivieren Sie die verschiedenen Cards, die im Easy Mode angezeigt werden können.
                </p>
                
                <div class="space-y-4">
                  <?php foreach ($availableCards as $cardId => $cardInfo): ?>
                    <div class="flex items-start gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-primary-120 dark:bg-primary-140">
                      <div class="flex items-center h-5 mt-0.5">
                        <input type="checkbox" 
                               id="card_<?php echo htmlspecialchars($cardId); ?>" 
                               name="cards[<?php echo htmlspecialchars($cardId); ?>]"
                               value="1"
                               <?php echo $cardSettings[$cardId] ? 'checked' : ''; ?>
                               class="h-4 w-4 rounded border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600">
                      </div>
                      <div class="flex-1">
                        <label for="card_<?php echo htmlspecialchars($cardId); ?>" class="text-sm font-medium text-gray-900 dark:text-primary-200 cursor-pointer">
                          <?php echo htmlspecialchars($cardInfo['title']); ?>
                        </label>
                        <p class="mt-1 text-xs text-gray-600 dark:text-primary-210">
                          <?php echo htmlspecialchars($cardInfo['description']); ?>
                        </p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              
              <div class="flex justify-end gap-3 border-t border-gray-200 pt-4 dark:border-primary-230">
                <button type="button" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>admin/'" 
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-primary-320 dark:bg-primary-300 dark:text-primary-200 dark:hover:bg-primary-140 dark:focus:ring-primary-360">
                  Abbrechen
                </button>
                <button type="submit" 
                        class="inline-flex items-center rounded-lg bg-primary-420 px-5 py-2.5 text-sm font-medium text-primary-480 hover:bg-primary-440 focus:outline-none focus:ring-4 focus:ring-primary-360 dark:bg-primary-420 dark:hover:bg-primary-440 dark:focus:ring-primary-360">
                  <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Einstellungen speichern
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
document.getElementById('cardsSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const cards = {};
    
    // Alle Checkboxen durchgehen
    formData.forEach((value, key) => {
        if (key.startsWith('cards[')) {
            const cardId = key.match(/cards\[(.*?)\]/)[1];
            cards[cardId] = value === '1';
        }
    });
    
    // Auch nicht aktivierte Cards hinzufügen (als false)
    <?php foreach ($availableCards as $cardId => $cardInfo): ?>
    if (!cards.hasOwnProperty('<?php echo $cardId; ?>')) {
        cards['<?php echo $cardId; ?>'] = false;
    }
    <?php endforeach; ?>
    
    // Easy Mode Tickets klickbar Einstellung
    const ticketsClickable = document.getElementById('easy_mode_tickets_clickable').checked;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>admin/api/cards-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                cards: cards,
                easy_mode_tickets_clickable: ticketsClickable
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Einstellungen erfolgreich gespeichert', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showToast('Fehler beim Speichern: ' + error.message, 'error');
    }
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
