<?php

function onboarding_password_user_hints(?array $user): array
{
    $email = trim((string) ($user['email'] ?? ''));
    $emailLocal = $email;
    if (str_contains($email, '@')) {
        $emailLocal = substr($email, 0, strpos($email, '@'));
    }

    return array_values(array_filter(array_unique(array_map(
        static fn(string $v): string => mb_strtolower(trim($v)),
        [
            (string) ($user['vorname'] ?? ''),
            (string) ($user['nachname'] ?? ''),
            $emailLocal,
            $email,
        ]
    )), static fn(string $v): bool => mb_strlen($v) >= 3));
}

function onboarding_password_has_digit(string $password): bool
{
    return (bool) preg_match('/\d/u', $password);
}

function onboarding_password_is_sequential_digits(string $password): bool
{
    if (!preg_match('/^\d+$/', $password) || strlen($password) < 4) {
        return false;
    }

    $ascending = true;
    $descending = true;
    $chars = str_split($password);

    for ($i = 1, $len = count($chars); $i < $len; $i++) {
        if ((int) $chars[$i] !== (int) $chars[$i - 1] + 1) {
            $ascending = false;
        }
        if ((int) $chars[$i] !== (int) $chars[$i - 1] - 1) {
            $descending = false;
        }
    }

    return $ascending || $descending;
}

function onboarding_password_common_weak_list(): array
{
    return [
        '12345678', '123456789', '1234567890', '87654321', '01234567', '1234567',
        '11111111', '00000000', 'password', 'passwort', 'passwort1', 'qwerty12',
        'qwertyui', 'admin123', 'letmein1', 'welcome1', 'iloveyou', 'sunshine',
        'abc12345', 'asdf1234', 'test1234', 'master12', 'changeme', 'serohub123',
    ];
}

function onboarding_password_is_guessable(string $password, array $userHints = []): bool
{
    $normalized = mb_strtolower(trim($password));
    if ($normalized === '') {
        return true;
    }

    if (in_array($normalized, onboarding_password_common_weak_list(), true)) {
        return true;
    }

    if (onboarding_password_is_sequential_digits($password)) {
        return true;
    }

    if (preg_match('/^(.)\1+$/u', $password)) {
        return true;
    }

    foreach ($userHints as $hint) {
        if ($hint === '') {
            continue;
        }
        if ($normalized === $hint) {
            return true;
        }
    }

    return false;
}

function onboarding_password_is_same_as_stored(string $password, string $storedHash): bool
{
    if ($password === '' || $storedHash === '') {
        return false;
    }

    return password_verify($password, $storedHash);
}

function onboarding_password_requirements_status(string $password, string $confirmPassword, array $userHints = [], ?bool $differentFromOld = null): array
{
    return [
        'length' => mb_strlen($password) >= 8,
        'digit' => onboarding_password_has_digit($password),
        'guessable' => !onboarding_password_is_guessable($password, $userHints),
        'different' => $differentFromOld === true,
        'match' => $password !== '' && $confirmPassword !== '' && $password === $confirmPassword,
    ];
}

function onboarding_password_requirements_met(string $password, string $confirmPassword, array $userHints = [], ?bool $differentFromOld = null): bool
{
    $status = onboarding_password_requirements_status($password, $confirmPassword, $userHints, $differentFromOld);

    return $status['length'] && $status['digit'] && $status['guessable'] && $status['different'] && $status['match'];
}

function onboarding_password_validation_error(string $password, string $confirmPassword, array $userHints = [], ?string $storedHash = null): ?string
{
    if ($password === '' || $confirmPassword === '') {
        return 'Bitte füllen Sie alle Felder aus.';
    }

    if ($password !== $confirmPassword) {
        return 'Die Passwörter stimmen nicht überein.';
    }

    if (mb_strlen($password) < 8) {
        return 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if (!onboarding_password_has_digit($password)) {
        return 'Das Passwort muss mindestens eine Zahl enthalten.';
    }

    if (onboarding_password_is_guessable($password, $userHints)) {
        return 'Bitte wähle ein sichereres Passwort.';
    }

    if ($storedHash !== null && onboarding_password_is_same_as_stored($password, $storedHash)) {
        return 'Das neue Passwort muss sich vom alten Passwort unterscheiden.';
    }

    return null;
}
