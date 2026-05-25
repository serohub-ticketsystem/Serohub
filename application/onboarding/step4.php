<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once __DIR__ . '/includes/layout.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

// BASE_URL definieren falls nicht bereits definiert
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.vorname, u.nachname, u.rolle, u.status, u.company_id, u.logopfad, u.letztes_pw_change
        FROM users u
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prüfen ob Schritt 1 und 2 abgeschlossen wurden
    if (!$user || empty($user['letztes_pw_change'])) {
        header('Location: ' . onboarding_step_url(1));
        exit();
    }
    if (empty($_SESSION['onboarding_profile_step_seen'])) {
        header('Location: ' . onboarding_step_url(2));
        exit();
    }
    if (empty($_SESSION['onboarding_contact_step_seen'])) {
        header('Location: ' . onboarding_step_url(3));
        exit();
    }
    $_SESSION['onboarding_avatar_step_seen'] = true;
    
    // Avatar-Pfad bestimmen
    $avatarPath = !empty($user['logopfad']) ? $user['logopfad'] : BASE_URL . 'assets/images/default-avatar.png';
    $isPresetAvatar = false;
    $presetColor = null;
    
    if (!empty($user['logopfad'])) {
        // Prüfen ob es ein vorgefertigter Avatar ist (Format: preset:color:initials)
        if (str_starts_with($user['logopfad'], 'preset:')) {
            $isPresetAvatar = true;
            $parts = explode(':', $user['logopfad']);
            if (count($parts) >= 2) {
                $presetColor = $parts[1];
            }
            // Für Vorschau: Container-Hintergrundfarbe verwenden
            $avatarPath = ''; // Wird durch CSS-Farbe ersetzt
        } elseif (!str_starts_with($user['logopfad'], 'http') && !str_starts_with($user['logopfad'], '/')) {
            $avatarPath = BASE_URL . $user['logopfad'];
        } elseif (str_starts_with($user['logopfad'], '/')) {
            $avatarPath = $user['logopfad'];
        }
    }
            
            // Initialen für Initialen-Avatar generieren
            $initials = '';
            if (!empty($user['vorname']) && !empty($user['nachname'])) {
                $initials = strtoupper(substr($user['vorname'], 0, 1) . substr($user['nachname'], 0, 1));
            } elseif (!empty($user['email'])) {
                $initials = strtoupper(substr($user['email'], 0, 1));
            } else {
                $initials = 'U';
            }
            
            // Vorgefertigte Avatare (Farben)
            $presetAvatars = [
                ['color' => '#3b82f6', 'name' => 'Blau'],
                ['color' => '#10b981', 'name' => 'Grün'],
                ['color' => '#f59e0b', 'name' => 'Gelb'],
                ['color' => '#ef4444', 'name' => 'Rot'],
                ['color' => '#8b5cf6', 'name' => 'Lila'],
                ['color' => '#ec4899', 'name' => 'Rosa'],
                ['color' => '#06b6d4', 'name' => 'Cyan'],
                ['color' => '#6366f1', 'name' => 'Indigo'],
                ['color' => '#14b8a6', 'name' => 'Türkis'],
                ['color' => '#f97316', 'name' => 'Orange'],
                ['color' => '#84cc16', 'name' => 'Lime'],
                ['color' => '#0ea5e9', 'name' => 'Himmelblau'],
            ];
} catch (PDOException $e) {
    error_log("Onboarding Step 4: Fehler beim Laden der Benutzerdaten: " . $e->getMessage());
    header('Location: ' . onboarding_step_url(1));
    exit;
}

$onboardingDashboardUrl = BASE_URL . 'dashboard/';

$onboardingStatus = onboarding_status_from_user($user);

include dirname(__DIR__) . '/assets/frontend/head.php';
onboarding_layout_styles();
?>

<div id="main-content" class="onboarding-root relative w-full overflow-hidden bg-gray-50 dark:bg-primary-50">
<?php onboarding_layout_body_script(); ?>
<?php
onboarding_shell_open([
    'illustration' => 'step4',
    'current_step' => 4,
    'status' => $onboardingStatus,
]);
?>

          <div class="onboarding-step-header">
            <h1 class="text-gray-900 dark:text-white">Avatar festlegen</h1>
            <p class="text-gray-600 dark:text-gray-400">Wähle eine Farbe für deine Initialen oder lade ein eigenes Bild hoch.</p>
          </div>

          <form id="avatarForm" class="onboarding-form-compact onboarding-avatar-compact" enctype="multipart/form-data">
            <input type="hidden" id="selected-preset-color" name="preset_color" value="">

            <div class="onboarding-avatar-hero">
              <div class="onboarding-avatar-preview-wrap">
                <div id="avatar-preview-container" class="onboarding-avatar-preview rounded-full overflow-hidden flex items-center justify-center text-white" style="background-color: <?php echo $isPresetAvatar && $presetColor ? htmlspecialchars($presetColor) : '#3b82f6'; ?>;">
                  <?php if ($isPresetAvatar): ?>
                    <span id="avatar-preview-initials"><?php echo htmlspecialchars($initials); ?></span>
                    <img id="avatar-preview-img" src="" alt="" class="hidden w-full h-full object-cover">
                  <?php else: ?>
                    <img id="avatar-preview-img" src="<?php echo htmlspecialchars($avatarPath); ?>" alt="" class="w-full h-full object-cover<?php echo empty($user['logopfad']) ? ' hidden' : ''; ?>">
                    <span id="avatar-preview-initials" class="<?php echo empty($user['logopfad']) ? '' : 'hidden'; ?>"><?php echo htmlspecialchars($initials); ?></span>
                  <?php endif; ?>
                </div>
                <div id="avatar-loading" class="hidden absolute inset-0 flex items-center justify-center bg-gray-900/60 rounded-full" aria-hidden="true">
                  <svg class="animate-spin h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
              </div>
              <p class="onboarding-avatar-preview-label">Vorschau</p>
            </div>

            <section class="onboarding-avatar-panel" aria-labelledby="avatar-color-title">
              <h2 class="onboarding-avatar-panel__title" id="avatar-color-title">Farbe wählen</h2>
              <div class="onboarding-color-grid" role="listbox" aria-label="Avatar-Farbe">
                <?php foreach ($presetAvatars as $preset):
                    $isActive = $isPresetAvatar && $presetColor === $preset['color'];
                ?>
                  <button type="button"
                          class="onboarding-color-swatch preset-avatar-btn<?php echo $isActive ? ' is-selected' : ''; ?>"
                          data-color="<?php echo htmlspecialchars($preset['color']); ?>"
                          data-type="preset"
                          style="background-color: <?php echo htmlspecialchars($preset['color']); ?>;"
                          title="<?php echo htmlspecialchars($preset['name']); ?>"
                          aria-label="<?php echo htmlspecialchars($preset['name']); ?>"
                          aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>">
                    <span class="onboarding-color-swatch__check" aria-hidden="true">✓</span>
                  </button>
                <?php endforeach; ?>
              </div>
            </section>

            <div class="onboarding-avatar-divider" role="presentation"><span>oder</span></div>

            <section class="onboarding-avatar-panel" aria-labelledby="avatar-upload-title">
              <h2 class="onboarding-avatar-panel__title" id="avatar-upload-title">Eigenes Bild</h2>
              <label id="uploadZone" class="onboarding-upload-zone" for="avatar">
                <svg class="onboarding-upload-zone__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                </svg>
                <p class="onboarding-upload-zone__text">Bild hier ablegen oder <strong>klicken zum Auswählen</strong></p>
                <p class="onboarding-upload-zone__meta">JPEG, PNG, GIF, WebP · max. 5 MB</p>
                <p id="uploadFileName" class="onboarding-upload-zone__filename hidden" role="status"></p>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
              </label>
            </section>

            <div class="onboarding-form-actions">
              <a href="<?php echo onboarding_step_url(3); ?>" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Zurück</a>
              <?php onboarding_render_btn_next(); ?>
            </div>
          </form>



<?php
onboarding_shell_close();
?>
<?php onboarding_render_notice(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('avatarForm');
    const avatarInput = document.getElementById('avatar');
    const avatarPreviewImg = document.getElementById('avatar-preview-img');
    const avatarPreviewContainer = document.getElementById('avatar-preview-container');
    const avatarPreviewInitials = document.getElementById('avatar-preview-initials');
    const avatarLoading = document.getElementById('avatar-loading');
    const submitBtn = form.querySelector('button[type="submit"]');
    const selectedPresetColor = document.getElementById('selected-preset-color');
    const uploadZone = document.getElementById('uploadZone');
    const uploadFileName = document.getElementById('uploadFileName');
    let selectedAvatarType = <?php echo $isPresetAvatar ? "'preset'" : 'null'; ?>;
    let selectedPresetColorValue = <?php echo ($isPresetAvatar && $presetColor) ? json_encode($presetColor) : 'null'; ?>;

    if (selectedPresetColorValue && selectedPresetColor) {
        selectedPresetColor.value = selectedPresetColorValue;
    }

    function clearSwatchSelection() {
        document.querySelectorAll('.onboarding-color-swatch').forEach(function(btn) {
            btn.classList.remove('is-selected');
            btn.setAttribute('aria-selected', 'false');
        });
    }

    function selectSwatch(button) {
        clearSwatchSelection();
        button.classList.add('is-selected');
        button.setAttribute('aria-selected', 'true');
    }

    function clearUploadUi() {
        if (uploadFileName) {
            uploadFileName.textContent = '';
            uploadFileName.classList.add('hidden');
        }
    }

    function showUploadFileName(name) {
        if (uploadFileName && name) {
            uploadFileName.textContent = name;
            uploadFileName.classList.remove('hidden');
        }
    }

    function evaluateAvatarReady() {
        onboardingSetNextVisible(submitBtn, true);
    }

    function applyPresetToPreview(color) {
        avatarPreviewContainer.style.backgroundColor = color;
        avatarPreviewImg.classList.add('hidden');
        avatarPreviewInitials.classList.remove('hidden');
        avatarPreviewInitials.textContent = '<?php echo htmlspecialchars($initials); ?>';
    }

    function applyUploadToPreview(dataUrl) {
        avatarPreviewImg.src = dataUrl;
        avatarPreviewImg.classList.remove('hidden');
        avatarPreviewInitials.classList.add('hidden');
        avatarPreviewContainer.style.backgroundColor = 'transparent';
    }

    const presetButtons = document.querySelectorAll('.preset-avatar-btn');
    presetButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            selectSwatch(this);
            const color = this.getAttribute('data-color');
            selectedPresetColorValue = color;
            selectedPresetColor.value = color;
            selectedAvatarType = 'preset';
            applyPresetToPreview(color);
            if (avatarInput) {
                avatarInput.value = '';
            }
            clearUploadUi();
            evaluateAvatarReady();
        });
    });

    function handleFile(file) {
        if (!file) {
            return;
        }
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;

        if (!allowedTypes.includes(file.type)) {
            onboardingShowNotice('Ungültiger Dateityp. Nur Bilder sind erlaubt.');
            if (avatarInput) {
                avatarInput.value = '';
            }
            clearUploadUi();
            return;
        }

        if (file.size > maxSize) {
            onboardingShowNotice('Datei ist zu groß (max. 5MB)');
            if (avatarInput) {
                avatarInput.value = '';
            }
            clearUploadUi();
            return;
        }

        selectedAvatarType = 'upload';
        selectedPresetColor.value = '';
        selectedPresetColorValue = null;
        clearSwatchSelection();
        showUploadFileName(file.name);

        const reader = new FileReader();
        reader.onload = function(ev) {
            applyUploadToPreview(ev.target.result);
            evaluateAvatarReady();
        };
        reader.readAsDataURL(file);
    }

    if (avatarInput && avatarPreviewImg) {
        avatarInput.addEventListener('change', function(e) {
            handleFile(e.target.files[0]);
        });
    }

    if (uploadZone) {
        ['dragenter', 'dragover'].forEach(function(evt) {
            uploadZone.addEventListener(evt, function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function(evt) {
            uploadZone.addEventListener(evt, function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.remove('is-dragover');
            });
        });
        uploadZone.addEventListener('drop', function(e) {
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file && avatarInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                avatarInput.files = dt.files;
                handleFile(file);
            }
        });
    }
    
    evaluateAvatarReady();
    
    // Formular absenden
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            onboardingBtnSetLoading(submitBtn, true);
            
            if (avatarInput && avatarInput.files.length > 0) {
                avatarLoading.classList.remove('hidden');
            }
            
            try {
                const formData = new FormData();
                
                // Prüfen ob vorgefertigter Avatar oder Upload ausgewählt wurde
                if (selectedAvatarType === 'preset' && selectedPresetColorValue) {
                    // Vorgefertigter Avatar ausgewählt
                    formData.append('preset_color', selectedPresetColorValue);
                    formData.append('avatar_type', 'preset');
                    formData.append('initials', '<?php echo htmlspecialchars($initials); ?>');
                } else if (avatarInput && avatarInput.files.length > 0) {
                    // Eigenes Bild hochgeladen
                    formData.append('avatar', avatarInput.files[0]);
                    formData.append('avatar_type', 'upload');
                } else {
                    // Kein Avatar ausgewählt - überspringen
                    formData.append('skip', '1');
                }
                
                const response = await fetch('<?php echo BASE_URL; ?>onboarding/api/step4.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Fehler beim Speichern des Avatars');
                }
                
                if (data.success) {
                    window.location.href = data.redirect || '<?php echo $onboardingDashboardUrl; ?>';
                } else {
                    throw new Error(data.error || 'Fehler beim Speichern des Avatars');
                }
            } catch (error) {
                console.error('Fehler:', error);
                onboardingShowNotice(error.message || 'Fehler beim Speichern des Avatars');
            } finally {
                onboardingBtnSetLoading(submitBtn, false);
                avatarLoading.classList.add('hidden');
                evaluateAvatarReady();
            }
        });
    }
});
</script>

<?php
include dirname(__DIR__) . '/assets/frontend/footer.php';
?>
