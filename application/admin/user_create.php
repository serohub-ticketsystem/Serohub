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
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Nur Admin kann User anlegen
if ($userRole !== 'Admin') {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$error = '';
$success = '';

// Firma aus Session lesen
$selectedCompanyId = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] ? (int)$_SESSION['selected_company_id'] : null;

// Firmen für Auswahl laden
$companies = [];
$customers = [];
$selectedCompany = null;
try {
    $companyStmt = $pdo->query("SELECT id, name, logo FROM companies WHERE status = 'aktiv' ORDER BY name");
    $companies = $companyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ausgewählte Firma laden
    if ($selectedCompanyId) {
        $stmt = $pdo->prepare("SELECT id, name, logo FROM companies WHERE id = ? AND status = 'aktiv' LIMIT 1");
        $stmt->execute([$selectedCompanyId]);
        $selectedCompany = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Kunden laden (nur wenn Firma ausgewählt)
    if ($selectedCompanyId) {
        $customerStmt = $pdo->prepare("SELECT id, name, email, company_id FROM customers WHERE status = 'aktiv' AND (company_id = ? OR company_id IS NULL) ORDER BY name");
        $customerStmt->execute([$selectedCompanyId]);
        $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Company/Customer Load Error: " . $e->getMessage());
}

// User-Anlegen Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $company_id = $selectedCompanyId; // Firma aus Session/Nav
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $vorname = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $telefonnummer = trim($_POST['telefonnummer'] ?? '');
    $rolle = $_POST['rolle'] ?? '';
    $status = 'aktiv'; // Immer aktiv
    $passwort_zuruecksetzen = isset($_POST['passwort_zuruecksetzen']) ? 1 : 0;
    $einfache_ansicht = isset($_POST['einfache_ansicht']) ? 1 : 0;
    
    // Validierung
    if (empty($email)) {
        $error = 'Bitte geben Sie eine E-Mail-Adresse ein.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (empty($password)) {
        $error = 'Bitte geben Sie ein Passwort ein.';
    } elseif (strlen($password) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($password !== $password_confirm) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } elseif (empty($rolle)) {
        $error = 'Bitte wählen Sie eine Rolle aus.';
    } elseif ($rolle === 'Kunde' && (!$company_id || !$customer_id)) {
        $error = 'Für die Rolle "Kunde" müssen eine Firma (über die Navigation ausgewählt) und ein Kunde ausgewählt werden.';
    } elseif ($rolle === 'Kunde' && $customer_id && $company_id) {
        // Prüfen ob Kunde zur Firma gehört
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND (company_id = ? OR company_id IS NULL)");
            $checkStmt->execute([$customer_id, $company_id]);
            if (!$checkStmt->fetch()) {
                $error = 'Der ausgewählte Kunde gehört nicht zur ausgewählten Firma.';
            }
        } catch (PDOException $e) {
            $error = 'Fehler bei der Validierung: ' . $e->getMessage();
        }
    }
    
    // Wenn keine Fehler aufgetreten sind, Benutzer anlegen
    if (empty($error)) {
        try {
            // Prüfen ob E-Mail bereits existiert
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'Diese E-Mail-Adresse existiert bereits.';
            } else {
                // Passwort hashen
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // User anlegen
                $stmt = $pdo->prepare("INSERT INTO users (email, passwort, company_id, customer_id, vorname, nachname, telefonnummer, rolle, status, passwort_zuruecksetzen, erstellt_datum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$email, $password_hash, $company_id, $customer_id, $vorname ?: null, $nachname ?: null, $telefonnummer ?: null, $rolle, $status, $passwort_zuruecksetzen]);
                
                $newUserId = $pdo->lastInsertId();
                
                // Benachrichtigungen erstellen
                require_once dirname(__DIR__) . '/assets/notifications.php';
                
                // Aktueller Benutzer für userName
                $currentUserStmt = $pdo->prepare("SELECT vorname, nachname FROM users WHERE id = ?");
                $currentUserStmt->execute([$_SESSION['user_id']]);
                $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
                $currentUserName = trim(($currentUser['vorname'] ?? '') . ' ' . ($currentUser['nachname'] ?? ''));
                if (empty($currentUserName)) {
                    $currentUserName = 'Unbekannt';
                }
                
                $newUserName = trim(($vorname ?: '') . ' ' . ($nachname ?: ''));
                if (empty($newUserName)) {
                    $newUserName = $email;
                }
                
                // Einfache Ansicht in user_settings speichern
                try {
                    $settingsStmt = $pdo->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum, geaendert_datum) VALUES (?, 'easy_mode', ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, geaendert_datum = NOW()");
                    $easyModeValue = $einfache_ansicht ? '1' : '0';
                    $settingsStmt->execute([$newUserId, $easyModeValue, $easyModeValue]);
                } catch (PDOException $e) {
                    error_log("Easy Mode Setting Error: " . $e->getMessage());
                }
                
                // Benachrichtigungen erstellen (User, Admin, Techniker, Firmen-Admin)
                createNotificationsForAction(
                    $_SESSION['user_id'],
                    $company_id,
                    'user_created',
                    'Neuer Benutzer erstellt: ' . $newUserName,
                    'Ein neuer Benutzer "' . $newUserName . '" (' . $email . ') wurde von ' . $currentUserName . ' erstellt.',
                    'hoch',
                    'admin/users.php',
                    'user',
                    $newUserId
                );
                
                $success = 'Benutzer wurde erfolgreich angelegt.';
                // Formular zurücksetzen
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = 'Fehler beim Anlegen des Benutzers: ' . $e->getMessage();
            error_log("User Create Error: " . $e->getMessage());
        }
    }
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
                  <a href="<?php echo BASE_URL; ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                    <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    Startseite
                  </a>
                </li>
                 <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Administration</a>
                  </div>
                </li>
                <li>
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <a href="<?php echo BASE_URL; ?>admin/users.php" class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white md:ms-2">Benutzer</a>
                  </div>
                </li>
                <li aria-current="page">
                  <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Benutzer anlegen</span>
                  </div>
                </li>
              </ol>
            </nav>
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Benutzer anlegen</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Erstelle einen neuen Benutzer im System</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="col-span-full mx-4">
          <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            
            <?php if ($error): ?>
              <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400" role="alert">
                <div class="flex items-center">
                  <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-3 1.732-3L5.732 4c-1.04 0-2.502 1.667-1.732 3L11.268 20c.77 1.333 2.694 1.333 3.464 0L20.268 7c.77-1.333-.692-3-1.732-3Z" />
                  </svg>
                  <?php echo htmlspecialchars($error); ?>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
              <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400" role="alert">
                <div class="flex items-center">
                  <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.5 11.5 15 15 9.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                  </svg>
                  <?php echo htmlspecialchars($success); ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Firmenauswahl wenn keine Firma ausgewählt -->
            <?php if (!$selectedCompanyId): ?>
              <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <div class="mb-4">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Firma auswählen</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Bitte wählen Sie zuerst eine Firma über die Navigation aus, bevor Sie einen Benutzer anlegen.</p>
                  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($companies as $company): ?>
                      <button type="button" 
                              onclick="selectCompany(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(getLogoUrl($company['logo']), ENT_QUOTES); ?>')"
                              class="flex items-center gap-3 rounded-lg border border-gray-300 bg-white p-3 text-left hover:bg-gray-50 hover:border-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:hover:border-primary-500 transition-colors">
                        <img src="<?php echo htmlspecialchars(getLogoUrl($company['logo'])); ?>" 
                             alt="<?php echo htmlspecialchars($company['name']); ?>" 
                             class="h-10 w-10 rounded-full object-cover flex-shrink-0">
                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo htmlspecialchars($company['name']); ?></span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <!-- Ausgewählte Firma anzeigen -->
              <?php if ($selectedCompany): ?>
                <div class="mb-6 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <img src="<?php echo htmlspecialchars(getLogoUrl($selectedCompany['logo'] ?? '')); ?>" 
                           alt="<?php echo htmlspecialchars($selectedCompany['name'] ?? ''); ?>" 
                           class="h-10 w-10 rounded-full object-cover">
                      <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Ausgewählte Firma</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($selectedCompany['name'] ?? ''); ?></p>
                      </div>
                    </div>
                    <button type="button" 
                            onclick="selectCompany(null, 'Alle Firmen', '<?php echo BASE_URL; ?>assets/images/default-avatar.png')"
                            class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                      Ändern
                    </button>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6" id="userForm" <?php echo !$selectedCompanyId ? 'style="display: none;"' : ''; ?>>
              
              <!-- Vor- und Nachname in einer Zeile -->
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label for="vorname" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Vorname</label>
                  <input type="text" id="vorname" name="vorname" 
                         value="<?php echo isset($_POST['vorname']) ? htmlspecialchars($_POST['vorname']) : ''; ?>"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                </div>
                
                <div>
                  <label for="nachname" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Nachname</label>
                  <input type="text" id="nachname" name="nachname" 
                         value="<?php echo isset($_POST['nachname']) ? htmlspecialchars($_POST['nachname']) : ''; ?>"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                </div>
              </div>

              <!-- Email und Telefonnummer in einer Zeile -->
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label for="email" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">E-Mail-Adresse *</label>
                  <input type="email" id="email" name="email" required 
                         value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                </div>
                
                <div>
                  <label for="telefonnummer" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Telefonnummer</label>
                  <input type="tel" id="telefonnummer" name="telefonnummer" 
                         value="<?php echo isset($_POST['telefonnummer']) ? htmlspecialchars($_POST['telefonnummer']) : ''; ?>"
                         placeholder="z.B. +49 123 456789"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                </div>
              </div>
              
              <!-- Passwort und Passwort wiederholen in einer Zeile mit Passwort zurücksetzen -->
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label for="password" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Passwort *</label>
                  <input type="password" id="password" name="password" required minlength="8"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mindestens 8 Zeichen</p>
                </div>
                
                <div>
                  <label for="password_confirm" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Passwort bestätigen *</label>
                  <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                         class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                  <div class="mt-2 flex items-center">
                   <!--   <input type="checkbox" id="passwort_zuruecksetzen" name="passwort_zuruecksetzen" value="1"
                          <?php echo (isset($_POST['passwort_zuruecksetzen'])) ? 'checked' : ''; ?>
                           class="h-4 w-4 rounded border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600">
                   <label for="passwort_zuruecksetzen" class="ms-2 text-xs font-medium text-gray-900 dark:text-gray-300">
                      Passwort bei nächster Anmeldung zurücksetzen -->
                    </label>
                  </div>
                </div>
              </div>
              
              <!-- Rolle als Cards -->
              <div>
                <label class="mb-3 block text-sm font-medium text-gray-900 dark:text-white">Rolle *</label>
                <div class="flex flex-wrap gap-4" id="roleCards">
                  <!-- Kunde -->
                  <label class="role-card relative cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-4 transition-all hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 flex-1 min-w-[200px]">
                    <input type="radio" name="rolle" value="Kunde" class="peer sr-only" <?php echo (isset($_POST['rolle']) && $_POST['rolle'] === 'Kunde') ? 'checked' : ''; ?> onchange="toggleCustomerRequired()">
                    <div class="flex items-start gap-3">
                      <div class="flex-shrink-0 rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">Kunde</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Zugriff auf Kunden-spezifische Funktionen</div>
                      </div>
                      <div class="flex-shrink-0">
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 peer-checked:border-primary-600 peer-checked:bg-primary-600 dark:border-gray-600 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-400">
                          <div class="hidden h-full w-full items-center justify-center peer-checked:flex">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <!-- Firmen-User -->
                  <label class="role-card relative cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-4 transition-all hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 flex-1 min-w-[200px]">
                    <input type="radio" name="rolle" value="Firmen-User" class="peer sr-only" <?php echo (isset($_POST['rolle']) && $_POST['rolle'] === 'Firmen-User') ? 'checked' : ''; ?> onchange="toggleCustomerRequired()">
                    <div class="flex items-start gap-3">
                      <div class="flex-shrink-0 rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">Firmen-User</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Benutzer, ohne Kundenbereich</div>
                      </div>
                      <div class="flex-shrink-0">
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 peer-checked:border-primary-600 peer-checked:bg-primary-600 dark:border-gray-600 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-400">
                          <div class="hidden h-full w-full items-center justify-center peer-checked:flex">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <!-- Firmen-Admin -->
                  <label class="role-card relative cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-4 transition-all hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 flex-1 min-w-[200px]">
                    <input type="radio" name="rolle" value="Firmen-Admin" class="peer sr-only" <?php echo (isset($_POST['rolle']) && $_POST['rolle'] === 'Firmen-Admin') ? 'checked' : ''; ?> onchange="toggleCustomerRequired()">
                    <div class="flex items-start gap-3">
                      <div class="flex-shrink-0 rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">Firmen-Admin</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Benutzer, mit Kundenbereich</div>
                      </div>
                      <div class="flex-shrink-0">
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 peer-checked:border-primary-600 peer-checked:bg-primary-600 dark:border-gray-600 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-400">
                          <div class="hidden h-full w-full items-center justify-center peer-checked:flex">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <!-- Techniker -->
                  <label class="role-card relative cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-4 transition-all hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 flex-1 min-w-[200px]">
                    <input type="radio" name="rolle" value="Techniker" class="peer sr-only" <?php echo (isset($_POST['rolle']) && $_POST['rolle'] === 'Techniker') ? 'checked' : ''; ?> onchange="toggleCustomerRequired()">
                    <div class="flex items-start gap-3">
                      <div class="flex-shrink-0 rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">Techniker</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Technischer Support und Wartung</div>
                      </div>
                      <div class="flex-shrink-0">
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 peer-checked:border-primary-600 peer-checked:bg-primary-600 dark:border-gray-600 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-400">
                          <div class="hidden h-full w-full items-center justify-center peer-checked:flex">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <!-- Admin -->
                  <label class="role-card relative cursor-pointer rounded-lg border-2 border-gray-200 bg-white p-4 transition-all hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500 dark:hover:bg-primary-900/20 flex-1 min-w-[200px]">
                    <input type="radio" name="rolle" value="Admin" class="peer sr-only" <?php echo (isset($_POST['rolle']) && $_POST['rolle'] === 'Admin') ? 'checked' : ''; ?> onchange="toggleCustomerRequired()">
                    <div class="flex items-start gap-3">
                      <div class="flex-shrink-0 rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">Admin</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Vollzugriff auf alle Funktionen</div>
                      </div>
                      <div class="flex-shrink-0">
                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 peer-checked:border-primary-600 peer-checked:bg-primary-600 dark:border-gray-600 dark:peer-checked:border-primary-400 dark:peer-checked:bg-primary-400">
                          <div class="hidden h-full w-full items-center justify-center peer-checked:flex">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
              
              <!-- Kunde-Zuordnung (nur wenn Kunde ausgewählt) -->
              <div id="customer_group" style="display: none;">
                <label for="customer_id" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                  Kunde *
                </label>
                <select id="customer_id" name="customer_id" required
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
                  <option value="">-- Bitte wählen --</option>
                  <?php if ($selectedCompanyId): ?>
                    <?php foreach ($customers as $customer): ?>
                      <option value="<?php echo $customer['id']; ?>" 
                              <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['name'] . ($customer['email'] ? ' (' . $customer['email'] . ')' : '')); ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nur Kunden der ausgewählten Firma werden angezeigt</p>
              </div>
              
              <!-- Einfache Ansicht Option -->
              <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center">
                  <input type="checkbox" id="einfache_ansicht" name="einfache_ansicht" value="1"
                         <?php echo (isset($_POST['einfache_ansicht'])) ? 'checked' : ''; ?>
                         class="h-4 w-4 rounded border-gray-300 bg-gray-100 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600">
                  <label for="einfache_ansicht" class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                    Einfache Ansicht aktivieren
                  </label>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Wenn aktiviert, wird der Benutzer nach dem Login direkt zur einfachen Ansicht weitergeleitet.
                </p>
              </div>
              
              <div class="flex justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                <a href="<?php echo BASE_URL; ?>admin/users.php" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700">
                  <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0 7-7m-7 7h18" />
                  </svg>
                  Zurück
                </a>
                <button type="submit" name="create_user" class="inline-flex items-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                  <svg class="me-2 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Benutzer anlegen
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
const selectedCompanyId = <?php echo $selectedCompanyId ? (int)$selectedCompanyId : 'null'; ?>;
const selectedCustomerId = <?php echo isset($_POST['customer_id']) && $_POST['customer_id'] ? (int)$_POST['customer_id'] : 'null'; ?>;

function selectCompany(companyId, companyName, companyLogo) {
    const setCompanyUrl = '<?php echo BASE_URL; ?>admin/set_company.php';
    
    fetch(setCompanyUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            company_id: companyId
        })
    })
    .then(response => response.json())
    .then(data => {
        location.reload();
    })
    .catch(error => {
        console.error('Fehler beim Setzen der Firma:', error);
        location.reload();
    });
}

function toggleCustomerRequired() {
    const rolleInputs = document.querySelectorAll('input[name="rolle"]');
    let selectedRolle = null;
    rolleInputs.forEach(input => {
        if (input.checked) {
            selectedRolle = input.value;
        }
    });
    
    const customerGroup = document.getElementById('customer_group');
    
    if (selectedRolle === 'Kunde') {
        customerGroup.style.display = 'block';
        document.getElementById('customer_id').setAttribute('required', 'required');
    } else {
        customerGroup.style.display = 'none';
        document.getElementById('customer_id').removeAttribute('required');
        document.getElementById('customer_id').value = '';
    }
}

// Role card styling
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomerRequired();
    
    // Update card styling when role is selected
    const roleInputs = document.querySelectorAll('input[name="rolle"]');
    roleInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Remove selected styling from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('border-primary-600', 'bg-primary-50', 'dark:bg-primary-900/20');
                card.classList.add('border-gray-200', 'dark:border-gray-700');
            });
            
            // Add selected styling to checked card
            if (this.checked) {
                const card = this.closest('.role-card');
                card.classList.add('border-primary-600', 'bg-primary-50', 'dark:bg-primary-900/20');
                card.classList.remove('border-gray-200', 'dark:border-gray-700');
            }
        });
        
        // Initial styling
        if (input.checked) {
            const card = input.closest('.role-card');
            card.classList.add('border-primary-600', 'bg-primary-50', 'dark:bg-primary-900/20');
            card.classList.remove('border-gray-200', 'dark:border-gray-700');
        }
    });
});
</script>

<style>
.role-card input:checked ~ div .peer-checked\:flex {
    display: flex !important;
}
</style>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
