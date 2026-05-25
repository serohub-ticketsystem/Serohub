<?php
/**
 * Profil-Stammdaten-Felder (Account).
 * Erwartet: $user, $delegateCandidates, $timezoneOptions
 * Optional: $inputClass (CSS-Klassen für Inputs)
 */
if (!isset($user) || !is_array($user)) {
    return;
}
$delegateCandidates = $delegateCandidates ?? [];
$timezoneOptions = $timezoneOptions ?? user_profile_fields_timezone_options();
$inputClass = $inputClass ?? 'mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';
$labelClass = $labelClass ?? 'mb-2 block text-sm font-medium text-gray-900 dark:text-white';
$userTz = $user['zeitzone'] ?? 'Europe/Berlin';
?>
<div class="grid gap-4 sm:grid-cols-2">
  <div>
    <label class="<?php echo $labelClass; ?>">Anrede</label>
    <select name="anrede" class="<?php echo $inputClass; ?>">
      <option value="" <?php echo empty($user['anrede']) ? 'selected' : ''; ?>>— keine Angabe —</option>
      <option value="herr" <?php echo ($user['anrede'] ?? '') === 'herr' ? 'selected' : ''; ?>>Herr</option>
      <option value="frau" <?php echo ($user['anrede'] ?? '') === 'frau' ? 'selected' : ''; ?>>Frau</option>
      <option value="divers" <?php echo ($user['anrede'] ?? '') === 'divers' ? 'selected' : ''; ?>>Divers</option>
      <option value="neutral" <?php echo ($user['anrede'] ?? '') === 'neutral' ? 'selected' : ''; ?>>Neutral</option>
    </select>
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Mobilnummer</label>
    <input type="tel" name="mobilnummer" value="<?php echo htmlspecialchars($user['mobilnummer'] ?? ''); ?>" class="<?php echo $inputClass; ?>" placeholder="z.B. +49 171 1234567">
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Position / Funktion</label>
    <input type="text" name="position_funktion" value="<?php echo htmlspecialchars($user['position_funktion'] ?? ''); ?>" class="<?php echo $inputClass; ?>" placeholder="z.B. IT-Leiter">
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Abteilung</label>
    <input type="text" name="abteilung" value="<?php echo htmlspecialchars($user['abteilung'] ?? ''); ?>" class="<?php echo $inputClass; ?>" placeholder="z.B. IT">
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Bevorzugte Sprache</label>
    <select name="sprache" class="<?php echo $inputClass; ?>">
      <option value="de" <?php echo ($user['sprache'] ?? 'de') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
      <option value="en" <?php echo ($user['sprache'] ?? '') === 'en' ? 'selected' : ''; ?>>Englisch</option>
    </select>
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Zeitzone</label>
    <select name="zeitzone" class="<?php echo $inputClass; ?>">
      <?php foreach ($timezoneOptions as $tz): ?>
      <option value="<?php echo htmlspecialchars($tz); ?>" <?php echo $userTz === $tz ? 'selected' : ''; ?>><?php echo htmlspecialchars($tz); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Bevorzugter Kontaktkanal</label>
    <select name="kontaktkanal" class="<?php echo $inputClass; ?>">
      <?php foreach (user_profile_fields_kontaktkanal_options() as $value => $label): ?>
      <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($user['kontaktkanal'] ?? 'email') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="<?php echo $labelClass; ?>">Teams / Slack / Durchwahl</label>
    <input type="text" name="kontakt_messenger" value="<?php echo htmlspecialchars($user['kontakt_messenger'] ?? ''); ?>" class="<?php echo $inputClass; ?>" placeholder="z.B. Teams: name@firma.de">
  </div>
  <div class="sm:col-span-2">
    <label class="<?php echo $labelClass; ?>">Erreichbarkeit / Bürozeiten</label>
    <?php user_profile_fields_render_erreichbarkeit_field((string) ($user['erreichbarkeit'] ?? ''), [
        'inputClass' => $inputClass,
        'labelClass' => $labelClass,
    ]); ?>
  </div>
</div>
<div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-600">
  <h4 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Stellvertretung</h4>
  <div class="grid gap-4 sm:grid-cols-3">
    <div>
      <label class="<?php echo $labelClass; ?>">Stellvertreter</label>
      <select name="stellvertreter_user_id" class="<?php echo $inputClass; ?>">
        <option value="">— keiner —</option>
        <?php foreach ($delegateCandidates as $dc): ?>
        <option value="<?php echo (int) $dc['id']; ?>" <?php echo (int) ($user['stellvertreter_user_id'] ?? 0) === (int) $dc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dc['label']); ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($delegateCandidates)): ?>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Keine weiteren aktiven Benutzer in Ihrer Firma.</p>
      <?php endif; ?>
    </div>
    <div>
      <label class="<?php echo $labelClass; ?>">Ab (optional)</label>
      <input type="date" name="stellvertreter_von" value="<?php echo htmlspecialchars($user['stellvertreter_von'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
    </div>
    <div>
      <label class="<?php echo $labelClass; ?>">Bis (optional)</label>
      <input type="date" name="stellvertreter_bis" value="<?php echo htmlspecialchars($user['stellvertreter_bis'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
    </div>
  </div>
</div>
