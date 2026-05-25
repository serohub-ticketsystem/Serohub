<?php
/**
 * User-Stammdaten: Spalten sicherstellen, Validierung, Stellvertreter-Kandidaten.
 */
declare(strict_types=1);

/** @return list<string> */
function user_profile_fields_column_names(): array
{
    return [
        'mobilnummer',
        'anrede',
        'position_funktion',
        'abteilung',
        'sprache',
        'zeitzone',
        'stellvertreter_user_id',
        'stellvertreter_von',
        'stellvertreter_bis',
        'kontakt_messenger',
        'erreichbarkeit',
        'kontaktkanal',
    ];
}

function user_profile_fields_ensure_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $definitions = [
        'mobilnummer' => "ADD COLUMN `mobilnummer` varchar(50) DEFAULT NULL COMMENT 'Mobiltelefon' AFTER `telefonnummer`",
        'anrede' => "ADD COLUMN `anrede` enum('herr','frau','divers','neutral') DEFAULT NULL COMMENT 'Anrede' AFTER `nachname`",
        'position_funktion' => "ADD COLUMN `position_funktion` varchar(120) DEFAULT NULL COMMENT 'Position/Funktion' AFTER `anrede`",
        'abteilung' => "ADD COLUMN `abteilung` varchar(120) DEFAULT NULL COMMENT 'Abteilung' AFTER `position_funktion`",
        'sprache' => "ADD COLUMN `sprache` enum('de','en') NOT NULL DEFAULT 'de' COMMENT 'Sprache' AFTER `abteilung`",
        'zeitzone' => "ADD COLUMN `zeitzone` varchar(64) NOT NULL DEFAULT 'Europe/Berlin' COMMENT 'Zeitzone' AFTER `sprache`",
        'stellvertreter_user_id' => "ADD COLUMN `stellvertreter_user_id` int UNSIGNED DEFAULT NULL COMMENT 'Stellvertreter' AFTER `zeitzone`",
        'stellvertreter_von' => "ADD COLUMN `stellvertreter_von` date DEFAULT NULL AFTER `stellvertreter_user_id`",
        'stellvertreter_bis' => "ADD COLUMN `stellvertreter_bis` date DEFAULT NULL AFTER `stellvertreter_von`",
        'kontakt_messenger' => "ADD COLUMN `kontakt_messenger` varchar(255) DEFAULT NULL AFTER `stellvertreter_bis`",
        'erreichbarkeit' => "ADD COLUMN `erreichbarkeit` text DEFAULT NULL AFTER `kontakt_messenger`",
        'kontaktkanal' => "ADD COLUMN `kontaktkanal` enum('portal','email','telefon','teams','whatsapp') NOT NULL DEFAULT 'email' AFTER `erreichbarkeit`",
    ];

    try {
        $existing = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['Field']] = $row;
        }
        foreach ($definitions as $col => $ddl) {
            if (empty($existing[$col])) {
                $pdo->exec('ALTER TABLE users ' . $ddl);
            }
        }
        if (!empty($existing['kontaktkanal'])) {
            user_profile_fields_ensure_kontaktkanal_enum($pdo, $existing['kontaktkanal']);
        }
    } catch (PDOException $e) {
        error_log('user_profile_fields_ensure_columns: ' . $e->getMessage());
    }
}

/** @return array<string, string> */
function user_profile_fields_kontaktkanal_options(): array
{
    return [
        'portal' => 'Dieses Portal',
        'email' => 'E-Mail',
        'telefon' => 'Telefon',
        'teams' => 'Teams',
        'whatsapp' => 'WhatsApp',
    ];
}

/** @return list<string> */
function user_profile_fields_kontaktkanal_values(): array
{
    return array_keys(user_profile_fields_kontaktkanal_options());
}

function user_profile_fields_ensure_kontaktkanal_enum(PDO $pdo, ?array $column = null): void
{
  static $done = false;
  if ($done) {
    return;
  }
  $done = true;

  try {
    if ($column === null) {
      $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'kontaktkanal'");
      $column = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$column) {
      return;
    }

    $type = (string) ($column['Type'] ?? '');
    $required = user_profile_fields_kontaktkanal_values();
    $needsUpdate = false;
    foreach ($required as $value) {
      if (stripos($type, "'" . $value . "'") === false) {
        $needsUpdate = true;
        break;
      }
    }

    if (!$needsUpdate) {
      return;
    }

    $enumList = implode(',', array_map(static fn(string $v): string => "'" . $v . "'", $required));
    $pdo->exec(
      "ALTER TABLE users MODIFY COLUMN `kontaktkanal` enum({$enumList}) NOT NULL DEFAULT 'email' COMMENT 'Bevorzugter Kontaktkanal'"
    );
  } catch (PDOException $e) {
    error_log('user_profile_fields_ensure_kontaktkanal_enum: ' . $e->getMessage());
  }
}

function user_profile_fields_set_email_notifications_enabled(PDO $pdo, int $userId, bool $enabled = true): void
{
    try {
        $enabledValue = $enabled ? '1' : '0';
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value, erstellt_datum)
            VALUES (:user_id, 'email_enabled', :setting_value, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = :setting_value_update,
                geaendert_datum = NOW()
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':setting_value' => $enabledValue,
            ':setting_value_update' => $enabledValue,
        ]);
    } catch (PDOException $e) {
        error_log('user_profile_fields_set_email_notifications_enabled: ' . $e->getMessage());
    }
}

function user_profile_fields_sync_email_notifications_for_kontaktkanal(PDO $pdo, int $userId, string $kontaktkanal): void
{
    if ($kontaktkanal === 'email') {
        user_profile_fields_set_email_notifications_enabled($pdo, $userId, true);
    }
}

/** @return array<string, bool> */
function user_profile_fields_columns_available(PDO $pdo): array
{
    user_profile_fields_ensure_columns($pdo);
    $out = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        $names = user_profile_fields_column_names();
        $set = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $set[$row['Field']] = true;
        }
        foreach ($names as $n) {
            $out[$n] = !empty($set[$n]);
        }
    } catch (PDOException $e) {
        foreach (user_profile_fields_column_names() as $n) {
            $out[$n] = false;
        }
    }
    return $out;
}

/** @return list<string> */
function user_profile_fields_timezone_options(): array
{
    return [
        'Europe/Berlin',
        'Europe/Vienna',
        'Europe/Zurich',
        'Europe/London',
        'Europe/Paris',
        'Europe/Amsterdam',
        'Europe/Rome',
        'Europe/Madrid',
        'Europe/Warsaw',
        'Europe/Prague',
        'UTC',
    ];
}

/**
 * @param array<string, mixed> $data
 * @return array{updates: list<string>, params: array<string, mixed>, error: ?string}
 */
function user_profile_fields_build_updates(array $data, PDO $pdo, int $userId): array
{
    $available = user_profile_fields_columns_available($pdo);
    $updates = [];
    $params = [];

    if (!empty($available['mobilnummer']) && array_key_exists('mobilnummer', $data)) {
        $v = trim((string) $data['mobilnummer']);
        $updates[] = 'mobilnummer = :mobilnummer';
        $params[':mobilnummer'] = $v === '' ? null : $v;
    }

    if (!empty($available['anrede']) && array_key_exists('anrede', $data)) {
        $v = trim((string) $data['anrede']);
        $allowed = ['', 'herr', 'frau', 'divers', 'neutral'];
        if (!in_array($v, $allowed, true)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültige Anrede'];
        }
        $updates[] = 'anrede = :anrede';
        $params[':anrede'] = $v === '' ? null : $v;
    }

    if (!empty($available['position_funktion']) && array_key_exists('position_funktion', $data)) {
        $v = trim((string) $data['position_funktion']);
        $updates[] = 'position_funktion = :position_funktion';
        $params[':position_funktion'] = $v === '' ? null : mb_substr($v, 0, 120);
    }

    if (!empty($available['abteilung']) && array_key_exists('abteilung', $data)) {
        $v = trim((string) $data['abteilung']);
        $updates[] = 'abteilung = :abteilung';
        $params[':abteilung'] = $v === '' ? null : mb_substr($v, 0, 120);
    }

    if (!empty($available['sprache']) && array_key_exists('sprache', $data)) {
        $v = trim((string) $data['sprache']);
        if (!in_array($v, ['de', 'en'], true)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültige Sprache'];
        }
        $updates[] = 'sprache = :sprache';
        $params[':sprache'] = $v;
    }

    if (!empty($available['zeitzone']) && array_key_exists('zeitzone', $data)) {
        $v = trim((string) $data['zeitzone']);
        if ($v === '' || !in_array($v, user_profile_fields_timezone_options(), true)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültige Zeitzone'];
        }
        $updates[] = 'zeitzone = :zeitzone';
        $params[':zeitzone'] = $v;
    }

    if (!empty($available['kontaktkanal']) && array_key_exists('kontaktkanal', $data)) {
        $v = trim((string) $data['kontaktkanal']);
        if (!in_array($v, user_profile_fields_kontaktkanal_values(), true)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültiger Kontaktkanal'];
        }
        $updates[] = 'kontaktkanal = :kontaktkanal';
        $params[':kontaktkanal'] = $v;
    }

    if (!empty($available['kontakt_messenger']) && array_key_exists('kontakt_messenger', $data)) {
        $v = trim((string) $data['kontakt_messenger']);
        $updates[] = 'kontakt_messenger = :kontakt_messenger';
        $params[':kontakt_messenger'] = $v === '' ? null : mb_substr($v, 0, 255);
    }

    if (!empty($available['erreichbarkeit']) && array_key_exists('erreichbarkeit', $data)) {
        $v = trim((string) $data['erreichbarkeit']);
        if ($v === '') {
            $updates[] = 'erreichbarkeit = :erreichbarkeit';
            $params[':erreichbarkeit'] = null;
        } else {
            $normalized = user_profile_fields_erreichbarkeit_normalize($v);
            if ($normalized === false) {
                return ['updates' => [], 'params' => [], 'error' => 'Ungültige Erreichbarkeitszeiten'];
            }
            $updates[] = 'erreichbarkeit = :erreichbarkeit';
            $params[':erreichbarkeit'] = $normalized;
        }
    }

    if (!empty($available['stellvertreter_user_id']) && array_key_exists('stellvertreter_user_id', $data)) {
        $sv = $data['stellvertreter_user_id'];
        $svId = ($sv === null || $sv === '' || (int) $sv <= 0) ? null : (int) $sv;
        if ($svId !== null && $svId === $userId) {
            return ['updates' => [], 'params' => [], 'error' => 'Sie können sich nicht selbst als Stellvertreter wählen'];
        }
        if ($svId !== null) {
            $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = ? LIMIT 1');
            $chk->execute([$svId, 'aktiv']);
            if (!$chk->fetch(PDO::FETCH_ASSOC)) {
                return ['updates' => [], 'params' => [], 'error' => 'Stellvertreter nicht gefunden oder inaktiv'];
            }
        }
        $updates[] = 'stellvertreter_user_id = :stellvertreter_user_id';
        $params[':stellvertreter_user_id'] = $svId;
    }

    if (!empty($available['stellvertreter_von']) && array_key_exists('stellvertreter_von', $data)) {
        $v = trim((string) $data['stellvertreter_von']);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültiges Datum (Stellvertretung ab)'];
        }
        $updates[] = 'stellvertreter_von = :stellvertreter_von';
        $params[':stellvertreter_von'] = $v === '' ? null : $v;
    }

    if (!empty($available['stellvertreter_bis']) && array_key_exists('stellvertreter_bis', $data)) {
        $v = trim((string) $data['stellvertreter_bis']);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return ['updates' => [], 'params' => [], 'error' => 'Ungültiges Datum (Stellvertretung bis)'];
        }
        $updates[] = 'stellvertreter_bis = :stellvertreter_bis';
        $params[':stellvertreter_bis'] = $v === '' ? null : $v;
    }

    $von = $params[':stellvertreter_von'] ?? null;
    $bis = $params[':stellvertreter_bis'] ?? null;
    if ($von && $bis && $von > $bis) {
        return ['updates' => [], 'params' => [], 'error' => 'Stellvertretung: „bis“ muss nach „ab“ liegen'];
    }

    return ['updates' => $updates, 'params' => $params, 'error' => null];
}

/** @return list<array{id: int, label: string}> */
function user_profile_fields_delegate_candidates(PDO $pdo, int $userId, ?int $companyId): array
{
    $list = [];
    try {
        if ($companyId !== null && $companyId > 0) {
            $stmt = $pdo->prepare("
                SELECT id, vorname, nachname, email
                FROM users
                WHERE company_id = ? AND id != ? AND status = 'aktiv'
                ORDER BY nachname ASC, vorname ASC
            ");
            $stmt->execute([$companyId, $userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, vorname, nachname, email
                FROM users
                WHERE id != ? AND status = 'aktiv' AND rolle IN ('Admin', 'Techniker')
                ORDER BY nachname ASC, vorname ASC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim(($row['vorname'] ?? '') . ' ' . ($row['nachname'] ?? ''));
            if ($name === '') {
                $name = (string) ($row['email'] ?? 'User ' . $row['id']);
            }
            $list[] = ['id' => (int) $row['id'], 'label' => $name . ' (' . ($row['email'] ?? '') . ')'];
        }
    } catch (PDOException $e) {
        error_log('user_profile_fields_delegate_candidates: ' . $e->getMessage());
    }
    return $list;
}

function user_profile_fields_select_extra_sql(PDO $pdo): string
{
    $cols = [];
    foreach (user_profile_fields_columns_available($pdo) as $name => $ok) {
        if ($ok) {
            $cols[] = 'u.' . $name;
        }
    }
    return $cols === [] ? '' : ', ' . implode(', ', $cols);
}

function user_profile_fields_labels(): array
{
    return [
        'mobilnummer' => 'Mobilnummer',
        'anrede' => 'Anrede',
        'position_funktion' => 'Position / Funktion',
        'abteilung' => 'Abteilung',
        'sprache' => 'Sprache',
        'zeitzone' => 'Zeitzone',
        'stellvertreter_user_id' => 'Stellvertreter (User-ID)',
        'stellvertreter_von' => 'Stellvertretung ab',
        'stellvertreter_bis' => 'Stellvertretung bis',
        'kontakt_messenger' => 'Teams / Slack / Durchwahl',
        'erreichbarkeit' => 'Erreichbarkeit / Bürozeiten',
        'kontaktkanal' => 'Bevorzugter Kontaktkanal',
    ];
}

function user_profile_fields_format_display(string $key, mixed $val): string
{
    if ($val === null || $val === '') {
        return '—';
    }
    if ($key === 'anrede') {
        return match ((string) $val) {
            'herr' => 'Herr',
            'frau' => 'Frau',
            'divers' => 'Divers',
            'neutral' => 'Neutral',
            default => (string) $val,
        };
    }
    if ($key === 'sprache') {
        return ((string) $val) === 'en' ? 'Englisch' : 'Deutsch';
    }
    if ($key === 'kontaktkanal') {
        return user_profile_fields_kontaktkanal_options()[(string) $val] ?? 'E-Mail';
    }
    if ($key === 'erreichbarkeit') {
        return user_profile_fields_erreichbarkeit_format_display((string) $val);
    }
    return (string) $val;
}

/** @return array<string, string> */
function user_profile_fields_erreichbarkeit_weekdays(): array
{
    return [
        'mo' => 'Mo',
        'tu' => 'Di',
        'we' => 'Mi',
        'th' => 'Do',
        'fr' => 'Fr',
        'sa' => 'Sa',
        'so' => 'So',
    ];
}

/** @return array{days: list<string>, von: string, bis: string, legacy?: string} */
function user_profile_fields_erreichbarkeit_parse(?string $raw): array
{
    $empty = ['days' => [], 'von' => '', 'bis' => ''];
    if ($raw === null || trim($raw) === '') {
        return $empty;
    }

    $trim = trim($raw);
    $json = json_decode($trim, true);
    if (is_array($json) && (array_key_exists('von', $json) || array_key_exists('bis', $json) || array_key_exists('days', $json))) {
        $allowed = user_profile_fields_erreichbarkeit_weekdays();
        $days = [];
        foreach ((array) ($json['days'] ?? []) as $day) {
            $day = strtolower((string) $day);
            if (isset($allowed[$day])) {
                $days[] = $day;
            }
        }
        $order = array_keys($allowed);
        usort($days, static fn(string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        return [
            'days' => array_values(array_unique($days)),
            'von' => user_profile_fields_erreichbarkeit_normalize_time((string) ($json['von'] ?? '')),
            'bis' => user_profile_fields_erreichbarkeit_normalize_time((string) ($json['bis'] ?? '')),
        ];
    }

    return array_merge($empty, ['legacy' => $trim]);
}

function user_profile_fields_erreichbarkeit_normalize_time(string $time): string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
        return '';
    }

    $hour = max(0, min(23, (int) $matches[1]));
    $minute = max(0, min(59, (int) $matches[2]));

    return sprintf('%02d:%02d', $hour, $minute);
}

/**
 * @param array{days?: list<string>, von?: string, bis?: string} $data
 */
function user_profile_fields_erreichbarkeit_encode(array $data): string|false|null
{
    $allowed = user_profile_fields_erreichbarkeit_weekdays();
    $days = [];
    foreach ((array) ($data['days'] ?? []) as $day) {
        $day = strtolower((string) $day);
        if (isset($allowed[$day])) {
            $days[] = $day;
        }
    }

    $von = user_profile_fields_erreichbarkeit_normalize_time((string) ($data['von'] ?? ''));
    $bis = user_profile_fields_erreichbarkeit_normalize_time((string) ($data['bis'] ?? ''));
    $days = array_values(array_unique($days));

    if ($days === [] && $von === '' && $bis === '') {
        return null;
    }

    if (($von === '') !== ($bis === '')) {
        return false;
    }

    return json_encode(['days' => $days, 'von' => $von, 'bis' => $bis], JSON_UNESCAPED_UNICODE);
}

function user_profile_fields_erreichbarkeit_normalize(string $raw): string|false|null
{
    if (trim($raw) === '') {
        return null;
    }

    $parsed = json_decode(trim($raw), true);
    if (!is_array($parsed)) {
        return trim($raw);
    }

    return user_profile_fields_erreichbarkeit_encode($parsed);
}

/**
 * FullCalendar businessHours aus Erreichbarkeit (Profil/Onboarding).
 *
 * @return list<array{daysOfWeek: list<int>, startTime: string, endTime: string}>
 */
function user_profile_fields_erreichbarkeit_calendar_business_hours(?string $raw): array
{
    $parsed = user_profile_fields_erreichbarkeit_parse($raw);
    if ($parsed['days'] === [] || $parsed['von'] === '' || $parsed['bis'] === '') {
        return [];
    }

    $dayMap = ['so' => 0, 'mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6];
    $daysOfWeek = [];
    foreach ($parsed['days'] as $day) {
        if (isset($dayMap[$day])) {
            $daysOfWeek[] = $dayMap[$day];
        }
    }
    $daysOfWeek = array_values(array_unique($daysOfWeek));
    sort($daysOfWeek);
    if ($daysOfWeek === []) {
        return [];
    }

    return [[
        'daysOfWeek' => $daysOfWeek,
        'startTime' => $parsed['von'],
        'endTime' => $parsed['bis'],
    ]];
}

function user_profile_fields_erreichbarkeit_format_days(array $days): string
{
    $labels = user_profile_fields_erreichbarkeit_weekdays();
    $order = array_keys($labels);
    usort($days, static fn(string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

    if ($days === ['mo', 'tu', 'we', 'th', 'fr']) {
        return 'Mo–Fr';
    }

    return implode(', ', array_map(static fn(string $day): string => $labels[$day] ?? $day, $days));
}

function user_profile_fields_erreichbarkeit_format_display(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }

    $parsed = user_profile_fields_erreichbarkeit_parse($raw);
    if (!empty($parsed['legacy'])) {
        return $parsed['legacy'];
    }

    $parts = [];
    if (!empty($parsed['days'])) {
        $parts[] = user_profile_fields_erreichbarkeit_format_days($parsed['days']);
    }
    if ($parsed['von'] !== '' && $parsed['bis'] !== '') {
        $parts[] = $parsed['von'] . '–' . $parsed['bis'] . ' Uhr';
    }

    return $parts !== [] ? implode(', ', $parts) : '—';
}

function user_profile_fields_render_time_wheel_picker(string $label, string $value, string $hiddenAttr, bool $isOnboarding = true): void
{
    $hour = '08';
    $minute = '00';
    $hasValue = $value !== '' && preg_match('/^(\d{2}):(\d{2})$/', $value, $matches);
    if ($hasValue) {
        $hour = $matches[1];
        $minute = $matches[2];
    }

    $pickerClass = $isOnboarding ? 'time-wheel-picker time-wheel-picker--onboarding' : 'time-wheel-picker time-wheel-picker--account';
    $groupClass = $isOnboarding ? 'time-wheel-group time-wheel-group--onboarding' : 'time-wheel-group time-wheel-group--account';
    $displayValue = $hasValue ? $value : '--:--';
    $triggerClass = 'time-wheel-trigger' . ($hasValue ? ' is-filled' : '');
    ?>
<div class="<?php echo $groupClass; ?>">
    <span class="time-wheel-group__label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
    <div class="time-wheel-collapse" data-time-wheel-collapse>
        <button type="button"
                class="<?php echo $triggerClass; ?>"
                data-time-wheel-trigger
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-label="<?php echo htmlspecialchars($label . ' wählen', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="time-wheel-trigger__value"><?php echo htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8'); ?></span>
        </button>
        <div class="time-wheel-picker-panel" data-time-wheel-panel hidden>
            <div class="<?php echo $pickerClass; ?>" data-time-wheel-group<?php echo $hasValue ? '' : ' data-time-empty="1"'; ?>>
        <input type="hidden" <?php echo $hiddenAttr; ?> value="<?php echo htmlspecialchars($hasValue ? $value : '', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="time-wheel" data-time-wheel="hour" data-selected="<?php echo htmlspecialchars($hour, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0" role="listbox" aria-label="<?php echo htmlspecialchars($label . ' Stunde', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="time-wheel__highlight" aria-hidden="true"></div>
            <div class="time-wheel__mask" aria-hidden="true"></div>
            <div class="time-wheel__list">
                <div class="time-wheel__pad" aria-hidden="true"></div>
                <?php for ($h = 0; $h < 24; $h++): $hv = sprintf('%02d', $h); ?>
                <div class="time-wheel__item" data-value="<?php echo $hv; ?>" role="option"><?php echo $hv; ?></div>
                <?php endfor; ?>
                <div class="time-wheel__pad" aria-hidden="true"></div>
            </div>
        </div>
        <span class="time-wheel-sep" aria-hidden="true">:</span>
        <div class="time-wheel" data-time-wheel="minute" data-selected="<?php echo htmlspecialchars($minute, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0" role="listbox" aria-label="<?php echo htmlspecialchars($label . ' Minute', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="time-wheel__highlight" aria-hidden="true"></div>
            <div class="time-wheel__mask" aria-hidden="true"></div>
            <div class="time-wheel__list">
                <div class="time-wheel__pad" aria-hidden="true"></div>
                <?php for ($m = 0; $m < 60; $m++): $mv = sprintf('%02d', $m); ?>
                <div class="time-wheel__item" data-value="<?php echo $mv; ?>" role="option"><?php echo $mv; ?></div>
                <?php endfor; ?>
                <div class="time-wheel__pad" aria-hidden="true"></div>
            </div>
        </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function user_profile_fields_render_erreichbarkeit_field(string $storedValue = '', array $opts = []): void
{
    $variant = $opts['variant'] ?? 'account';
    $parsed = user_profile_fields_erreichbarkeit_parse($storedValue);
    $weekdays = user_profile_fields_erreichbarkeit_weekdays();
    $selectedDays = $parsed['days'];
    $encoded = user_profile_fields_erreichbarkeit_encode([
        'days' => $parsed['days'],
        'von' => $parsed['von'],
        'bis' => $parsed['bis'],
    ]);
    $isLegacy = !empty($parsed['legacy']);
    $hiddenValue = $isLegacy ? (string) $parsed['legacy'] : ($encoded ?? '');
    $legacyAttr = $isLegacy ? ' data-erreichbarkeit-legacy="1"' : '';

    if ($variant === 'onboarding') {
        ?>
<div class="onboarding-erreichbarkeit" data-erreichbarkeit>
    <input type="hidden" id="erreichbarkeit" name="erreichbarkeit" value="<?php echo htmlspecialchars($hiddenValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $legacyAttr; ?>>

    <div class="onboarding-erreichbarkeit-layout">
        <div class="onboarding-erreichbarkeit-days" role="group" aria-label="Wochentage">
            <?php foreach ($weekdays as $key => $label): ?>
            <button type="button"
                    class="onboarding-choice-chip<?php echo in_array($key, $selectedDays, true) ? ' is-selected' : ''; ?>"
                    data-erreichbarkeit-day="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-pressed="<?php echo in_array($key, $selectedDays, true) ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-erreichbarkeit-times-col<?php echo $selectedDays !== [] ? ' is-visible' : ''; ?>" data-erreichbarkeit-times>
            <div class="onboarding-erreichbarkeit-times__row" role="group" aria-label="Uhrzeit">
                <?php user_profile_fields_render_time_wheel_picker('Von', $parsed['von'], 'data-erreichbarkeit-von', true); ?>
                <?php user_profile_fields_render_time_wheel_picker('Bis', $parsed['bis'], 'data-erreichbarkeit-bis', true); ?>
            </div>
        </div>
    </div>
</div>
        <?php
        user_profile_fields_erreichbarkeit_script();
        return;
    }

    ?>
<div class="space-y-3" data-erreichbarkeit>
    <input type="hidden" name="erreichbarkeit" value="<?php echo htmlspecialchars($hiddenValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $legacyAttr; ?>>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-gray-600 dark:text-gray-400">Wochentage</span>
        <button type="button" class="text-xs text-primary-600 hover:underline dark:text-primary-400" data-erreichbarkeit-werktage>Mo–Fr</button>
    </div>
    <div class="flex flex-wrap gap-2" role="group" aria-label="Wochentage">
        <?php foreach ($weekdays as $key => $label): ?>
        <button type="button"
                class="rounded-md border px-2.5 py-1 text-sm<?php echo in_array($key, $selectedDays, true) ? ' border-gray-500 bg-gray-200 text-gray-900 dark:border-gray-400 dark:bg-gray-600 dark:text-white' : ' border-gray-300 text-gray-600 dark:border-gray-600 dark:text-gray-300'; ?>"
                data-erreichbarkeit-day="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                aria-pressed="<?php echo in_array($key, $selectedDays, true) ? 'true' : 'false'; ?>">
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div class="account-erreichbarkeit-times">
        <?php user_profile_fields_render_time_wheel_picker('Von', $parsed['von'], 'data-erreichbarkeit-von', false); ?>
        <?php user_profile_fields_render_time_wheel_picker('Bis', $parsed['bis'], 'data-erreichbarkeit-bis', false); ?>
    </div>
</div>
    <?php
    user_profile_fields_erreichbarkeit_script();
}

function user_profile_fields_erreichbarkeit_script(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
<style id="erreichbarkeit-time-wheel-styles">
.onboarding-erreichbarkeit-times__row,
.account-erreichbarkeit-times {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
}
.time-wheel-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.375rem;
}
.time-wheel-group--onboarding {
    flex-direction: row;
    align-items: center;
    gap: 0.625rem;
}
.time-wheel-group--onboarding .time-wheel-group__label {
    font-size: 0.9375rem;
    white-space: nowrap;
}
.time-wheel-group__label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #6b7280;
}
.dark .time-wheel-group__label { color: #9ca3af; }
.time-wheel-collapse { position: relative; }
.time-wheel-trigger {
    min-width: 4.75rem;
    padding: 0.4375rem 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    font-size: 0.9375rem;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    color: #9ca3af;
    cursor: pointer;
    transition: border-color 0.18s ease, background 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}
.time-wheel-trigger:hover { border-color: #d1d5db; color: #6b7280; }
.time-wheel-trigger.is-filled { color: #374151; }
.time-wheel-trigger.is-open {
    border-color: #9ca3af;
    background: #f3f4f6;
    color: #111827;
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(17, 24, 39, 0.08);
}
.dark .time-wheel-trigger {
    border-color: #4b5563;
    background: rgba(31, 41, 55, 0.65);
    color: #6b7280;
}
.dark .time-wheel-trigger:hover { border-color: #6b7280; color: #9ca3af; }
.dark .time-wheel-trigger.is-filled { color: #e5e7eb; }
.dark .time-wheel-trigger.is-open {
    border-color: #9ca3af;
    background: rgba(55, 65, 81, 0.85);
    color: #f9fafb;
}
.time-wheel-picker-panel {
    position: absolute;
    z-index: 30;
    top: calc(100% + 0.375rem);
    left: 50%;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateX(-50%) translateY(-6px) scale(0.94);
    transform-origin: top center;
    transition: opacity 0.22s cubic-bezier(0.22, 1, 0.36, 1), transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.22s;
}
.time-wheel-collapse.is-open .time-wheel-picker-panel {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0) scale(1);
}
.time-wheel-picker {
    --tw-item-h: 28px;
    display: flex;
    align-items: center;
    gap: 0.125rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.12);
}
.time-wheel-picker--onboarding {
    --tw-item-h: 38px;
    gap: 0.25rem;
    padding: 0.5rem 0.875rem;
}
.time-wheel-picker--onboarding .time-wheel {
    width: 2.75rem;
}
.time-wheel-picker--onboarding .time-wheel-sep {
    font-size: 1.125rem;
}
.time-wheel-picker--onboarding .time-wheel__item {
    font-size: 0.9375rem;
}
.dark .time-wheel-picker {
    border-color: #4b5563;
    background: rgba(31, 41, 55, 0.98);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}
.time-wheel-sep {
    padding-bottom: 0.0625rem;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    line-height: 1;
}
.dark .time-wheel-sep { color: #e5e7eb; }
.time-wheel {
    position: relative;
    width: 2.125rem;
    height: calc(var(--tw-item-h) * 3);
    overflow: hidden;
    cursor: grab;
    touch-action: none;
    user-select: none;
}
.time-wheel.is-dragging { cursor: grabbing; }
.time-wheel__highlight {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    z-index: 1;
    height: var(--tw-item-h);
    transform: translateY(-50%);
    border-top: 1px solid #d1d5db;
    border-bottom: 1px solid #d1d5db;
    background: rgba(229, 231, 235, 0.45);
    border-radius: 0.25rem;
    pointer-events: none;
}
.dark .time-wheel__highlight {
    border-color: #6b7280;
    background: rgba(75, 85, 99, 0.35);
}
.time-wheel__mask {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: linear-gradient(to bottom, #f9fafb 0%, rgba(249,250,251,0) 28%, rgba(249,250,251,0) 72%, #f9fafb 100%);
}
.dark .time-wheel__mask {
    background: linear-gradient(to bottom, rgba(31,41,55,0.95) 0%, rgba(31,41,55,0) 28%, rgba(31,41,55,0) 72%, rgba(31,41,55,0.95) 100%);
}
.time-wheel__list {
    position: relative;
    z-index: 3;
    will-change: transform;
}
.time-wheel__list.is-animating {
    transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.time-wheel__pad { height: var(--tw-item-h); }
.time-wheel__item {
    height: var(--tw-item-h);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
    color: #c4c9d1;
    opacity: 0.55;
    transition: color 0.12s ease, font-size 0.12s ease, font-weight 0.12s ease, opacity 0.12s ease;
}
.time-wheel__item.is-selected {
    color: #111827;
    opacity: 1;
    font-size: 0.9375rem;
    font-weight: 700;
}
.dark .time-wheel__item { color: #6b7280; opacity: 0.5; }
.dark .time-wheel__item.is-selected { color: #ffffff; opacity: 1; }
.time-wheel-picker--onboarding .time-wheel__item.is-selected {
    color: #111827;
    font-size: 1.1875rem;
}
.dark .time-wheel-picker--onboarding .time-wheel__item.is-selected { color: #ffffff; }
</style>
<script>
window.initTimeWheelPickers = function(root) {
    (root || document).querySelectorAll('.time-wheel:not([data-time-wheel-init])').forEach(function(wheel) {
        wheel.dataset.timeWheelInit = '1';
        var list = wheel.querySelector('.time-wheel__list');
        var items = list.querySelectorAll('.time-wheel__item');
        if (!items.length) return;
        var ITEM_H = 28;

        function getItemHeight() {
            var measured = items[0].getBoundingClientRect().height;
            if (measured > 0) return measured;
            var picker = wheel.closest('.time-wheel-picker');
            if (picker) {
                var cssVal = getComputedStyle(picker).getPropertyValue('--tw-item-h').trim();
                if (cssVal) return parseFloat(cssVal) || 28;
            }
            return 28;
        }

        function indexForValue(val) {
            for (var i = 0; i < items.length; i++) {
                if (items[i].getAttribute('data-value') === val) return i;
            }
            return 0;
        }

        function highlightIndex(idx) {
            items.forEach(function(item, i) {
                var active = i === idx;
                item.classList.toggle('is-selected', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function applyTransformations(idx, animate) {
            ITEM_H = getItemHeight();
            list.classList.toggle('is-animating', !!animate);
            list.style.transform = 'translateY(' + (-idx * ITEM_H) + 'px)';
            highlightIndex(idx);
        }

        function setSelected(val, animate) {
            wheel.setAttribute('data-selected', val);
            applyTransformations(indexForValue(val), animate);
            updateTimeWheelGroupHidden(wheel.closest('[data-time-wheel-group]'));
        }

        wheel._timeWheelRefresh = function() {
            applyTransformations(indexForValue(wheel.getAttribute('data-selected') || '00'), false);
        };

        applyTransformations(indexForValue(wheel.getAttribute('data-selected') || '00'), false);

        wheel.addEventListener('wheel', function(e) {
            e.preventDefault();
            var idx = indexForValue(wheel.getAttribute('data-selected') || '00');
            idx = Math.max(0, Math.min(items.length - 1, idx + (e.deltaY > 0 ? 1 : -1)));
            setSelected(items[idx].getAttribute('data-value'), true);
        }, { passive: false });

        var dragging = false;
        var startY = 0;
        var startOffset = 0;
        var currentOffset = 0;

        function readOffset() {
            ITEM_H = getItemHeight();
            var idx = indexForValue(wheel.getAttribute('data-selected') || '00');
            return -idx * ITEM_H;
        }

        function applyOffset(offset) {
            ITEM_H = getItemHeight();
            currentOffset = offset;
            var min = -(items.length - 1) * ITEM_H;
            currentOffset = Math.max(min, Math.min(0, currentOffset));
            list.style.transform = 'translateY(' + currentOffset + 'px)';
            var idx = Math.max(0, Math.min(items.length - 1, Math.round(-currentOffset / ITEM_H)));
            highlightIndex(idx);
        }

        function finishDrag(animate) {
            dragging = false;
            wheel.classList.remove('is-dragging');
            ITEM_H = getItemHeight();
            var idx = Math.max(0, Math.min(items.length - 1, Math.round(-currentOffset / ITEM_H)));
            setSelected(items[idx].getAttribute('data-value'), animate);
        }

        wheel.addEventListener('pointerdown', function(e) {
            if (e.button !== 0) return;
            dragging = true;
            wheel.classList.add('is-dragging');
            startY = e.clientY;
            startOffset = readOffset();
            currentOffset = startOffset;
            list.classList.remove('is-animating');
            wheel.setPointerCapture(e.pointerId);
        });

        wheel.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            applyOffset(startOffset + (e.clientY - startY));
        });

        wheel.addEventListener('pointerup', function() { finishDrag(true); });
        wheel.addEventListener('pointercancel', function() { finishDrag(true); });

        wheel.addEventListener('keydown', function(e) {
            var idx = indexForValue(wheel.getAttribute('data-selected') || '00');
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelected(items[Math.max(0, idx - 1)].getAttribute('data-value'), true);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelected(items[Math.min(items.length - 1, idx + 1)].getAttribute('data-value'), true);
            }
        });
    });
};

function updateTimeWheelTriggerDisplay(group) {
    if (!group) return;
    var collapse = group.closest('[data-time-wheel-collapse]');
    if (!collapse) return;
    var trigger = collapse.querySelector('[data-time-wheel-trigger]');
    var triggerValue = collapse.querySelector('.time-wheel-trigger__value');
    var hourWheel = group.querySelector('[data-time-wheel="hour"]');
    var minuteWheel = group.querySelector('[data-time-wheel="minute"]');
    var isEmpty = group.hasAttribute('data-time-empty');
    var display = '--:--';
    if (!isEmpty && hourWheel && minuteWheel) {
        display = (hourWheel.getAttribute('data-selected') || '00') + ':' + (minuteWheel.getAttribute('data-selected') || '00');
    }
    if (triggerValue) triggerValue.textContent = display;
    if (trigger) trigger.classList.toggle('is-filled', !isEmpty);
}

function closeTimeWheelCollapse(collapse) {
    if (!collapse) return;
    collapse.classList.remove('is-open');
    var trigger = collapse.querySelector('[data-time-wheel-trigger]');
    var panel = collapse.querySelector('[data-time-wheel-panel]');
    if (trigger) {
        trigger.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    }
    if (panel) panel.hidden = true;
}

function openTimeWheelCollapse(collapse) {
    if (!collapse) return;
    document.querySelectorAll('[data-time-wheel-collapse].is-open').forEach(function(other) {
        if (other !== collapse) closeTimeWheelCollapse(other);
    });
    collapse.classList.add('is-open');
    var trigger = collapse.querySelector('[data-time-wheel-trigger]');
    var panel = collapse.querySelector('[data-time-wheel-panel]');
    if (trigger) {
        trigger.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
    }
    if (panel) {
        panel.hidden = false;
        requestAnimationFrame(function() {
            panel.querySelectorAll('.time-wheel[data-time-wheel-init]').forEach(function(wheel) {
                if (typeof wheel._timeWheelRefresh === 'function') {
                    wheel._timeWheelRefresh();
                }
            });
        });
    }
}

window.initTimeWheelCollapses = function(root) {
    (root || document).querySelectorAll('[data-time-wheel-collapse]:not([data-time-wheel-collapse-init])').forEach(function(collapse) {
        collapse.dataset.timeWheelCollapseInit = '1';
        var trigger = collapse.querySelector('[data-time-wheel-trigger]');
        if (!trigger) return;
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (collapse.classList.contains('is-open')) {
                closeTimeWheelCollapse(collapse);
            } else {
                openTimeWheelCollapse(collapse);
            }
        });
    });
};

function updateTimeWheelGroupHidden(group) {
    if (!group) return;
    var hidden = group.querySelector('input[type="hidden"]');
    var hourWheel = group.querySelector('[data-time-wheel="hour"]');
    var minuteWheel = group.querySelector('[data-time-wheel="minute"]');
    if (!hidden || !hourWheel || !minuteWheel) return;
    group.removeAttribute('data-time-empty');
    var hour = hourWheel.getAttribute('data-selected') || '00';
    var minute = minuteWheel.getAttribute('data-selected') || '00';
    hidden.value = hour + ':' + minute;
    updateTimeWheelTriggerDisplay(group);
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
}

document.addEventListener('click', function(e) {
    if (e.target.closest('[data-time-wheel-collapse]')) return;
    document.querySelectorAll('[data-time-wheel-collapse].is-open').forEach(closeTimeWheelCollapse);
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('[data-time-wheel-collapse].is-open').forEach(closeTimeWheelCollapse);
    }
});

window.initErreichbarkeitFields = function(form) {
    if (!form) return;
    initTimeWheelPickers(form);
    initTimeWheelCollapses(form);
    form.querySelectorAll('[data-erreichbarkeit]').forEach(function(wrap) {
        if (wrap.dataset.erreichbarkeitInit) return;
        wrap.dataset.erreichbarkeitInit = '1';
        var hidden = wrap.querySelector('input[name="erreichbarkeit"]');
        var von = wrap.querySelector('[data-erreichbarkeit-von]');
        var bis = wrap.querySelector('[data-erreichbarkeit-bis]');
        var dayBtns = wrap.querySelectorAll('[data-erreichbarkeit-day]');
        var werkBtn = wrap.querySelector('[data-erreichbarkeit-werktage]');
        if (!hidden) return;

        function timeValue(input, group) {
            if (!input) return '';
            if (group && group.hasAttribute('data-time-empty')) return '';
            return input.value || '';
        }

        function hasSelectedDays() {
            return Array.from(dayBtns).some(function(btn) {
                return btn.classList.contains('is-selected') || btn.getAttribute('aria-pressed') === 'true';
            });
        }

        function updateTimesVisibility() {
            var timesBlock = wrap.querySelector('[data-erreichbarkeit-times]');
            if (!timesBlock) return;
            if (hasSelectedDays()) {
                timesBlock.classList.add('is-visible');
            } else {
                timesBlock.classList.remove('is-visible');
                timesBlock.querySelectorAll('[data-time-wheel-collapse].is-open').forEach(closeTimeWheelCollapse);
            }
        }

        function sync() {
            if (hidden.dataset.erreichbarkeitLegacy) {
                delete hidden.dataset.erreichbarkeitLegacy;
            }
            var days = [];
            dayBtns.forEach(function(btn) {
                if (btn.classList.contains('is-selected') || btn.getAttribute('aria-pressed') === 'true') {
                    days.push(btn.getAttribute('data-erreichbarkeit-day'));
                }
            });
            updateTimesVisibility();
            var vonGroup = von ? von.closest('[data-time-wheel-group]') : null;
            var bisGroup = bis ? bis.closest('[data-time-wheel-group]') : null;
            var hasDays = days.length > 0;
            var payload = {
                days: days,
                von: hasDays ? timeValue(von, vonGroup) : '',
                bis: hasDays ? timeValue(bis, bisGroup) : ''
            };
            var allEmpty = days.length === 0 && !payload.von && !payload.bis;
            hidden.value = allEmpty ? '' : JSON.stringify(payload);
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }

        dayBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var selected = btn.getAttribute('aria-pressed') !== 'true';
                btn.classList.toggle('is-selected', selected);
                btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
                if (!btn.classList.contains('onboarding-choice-chip')) {
                    btn.classList.toggle('border-gray-500', selected);
                    btn.classList.toggle('bg-gray-200', selected);
                    btn.classList.toggle('text-gray-900', selected);
                    btn.classList.toggle('dark:bg-gray-600', selected);
                    btn.classList.toggle('dark:text-white', selected);
                    btn.classList.toggle('border-gray-300', !selected);
                    btn.classList.toggle('text-gray-600', !selected);
                }
                sync();
            });
        });

        if (werkBtn) {
            werkBtn.addEventListener('click', function() {
                var workdays = ['mo', 'tu', 'we', 'th', 'fr'];
                dayBtns.forEach(function(btn) {
                    var selected = workdays.indexOf(btn.getAttribute('data-erreichbarkeit-day')) >= 0;
                    btn.classList.toggle('is-selected', selected);
                    btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
                    if (!btn.classList.contains('onboarding-choice-chip')) {
                        btn.classList.toggle('border-gray-500', selected);
                        btn.classList.toggle('bg-gray-200', selected);
                        btn.classList.toggle('text-gray-900', selected);
                        btn.classList.toggle('dark:bg-gray-600', selected);
                        btn.classList.toggle('dark:text-white', selected);
                        btn.classList.toggle('border-gray-300', !selected);
                        btn.classList.toggle('text-gray-600', !selected);
                    }
                });
                sync();
            });
        }

        if (von) {
            von.addEventListener('change', sync);
            von.addEventListener('input', sync);
        }
        if (bis) {
            bis.addEventListener('change', sync);
            bis.addEventListener('input', sync);
        }
        if (!hidden.dataset.erreichbarkeitLegacy) {
            sync();
        }
    });
};
document.addEventListener('DOMContentLoaded', function() {
    initTimeWheelPickers(document);
    initTimeWheelCollapses(document);
    document.querySelectorAll('form').forEach(function(form) {
        if (form.querySelector('[data-erreichbarkeit]')) {
            initErreichbarkeitFields(form);
        }
    });
});
</script>
    <?php
}
