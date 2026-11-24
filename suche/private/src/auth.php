<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 900;

/* ---------- Session ---------- */

function session_bootstrap(): void
{
    $lifetime = max(1, (int) cfg('session_days')) * 86400;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.gc_probability', '0'); // aufgeräumt wird in maintenance()
    session_save_path(SESSION_DIR);
    session_name('suche_sid');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => base_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Eingeloggt: Ablaufdatum des Cookies bei jedem Besuch verlängern
    if (isset($_SESSION['user'])) {
        $_SESSION['seen'] = time();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $lifetime,
            'path'     => base_path(),
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals(csrf_token(), $_POST['csrf']);
}

/* ---------- Nutzer ---------- */

function users_file(): string
{
    return DATA_DIR . '/users.json';
}

function users_all(): array
{
    return json_read(users_file());
}

function users_exist(): bool
{
    return count(users_all()) > 0;
}

function user_get(string $name): ?array
{
    return users_all()[strtolower($name)] ?? null;
}

function username_valid(string $name): bool
{
    return (bool) preg_match('/^[A-Za-z0-9._-]{2,32}$/', $name);
}

function password_valid(string $pw): ?string
{
    if (mb_strlen($pw) < 8) {
        return 'Das Passwort muss mindestens 8 Zeichen haben.';
    }
    if (strlen($pw) > 256) {
        return 'Das Passwort ist zu lang.';
    }
    return null;
}

/**
 * Legt einen Nutzer an. $onlyIfEmpty = true für die Ersteinrichtung,
 * damit nicht zwei Leute gleichzeitig Admin werden.
 * Gibt eine Fehlermeldung oder null zurück.
 */
function user_create(string $name, string $pw, bool $admin, bool $onlyIfEmpty = false): ?string
{
    if (!username_valid($name)) {
        return 'Benutzername: 2 bis 32 Zeichen, nur Buchstaben, Zahlen, Punkt, Minus, Unterstrich.';
    }
    if ($err = password_valid($pw)) {
        return $err;
    }
    $error = null;
    $hash  = password_hash($pw, PASSWORD_DEFAULT);
    json_update(users_file(), function (array $users) use ($name, $hash, $admin, $onlyIfEmpty, &$error) {
        if ($onlyIfEmpty && $users) {
            $error = 'Es gibt bereits einen Admin. Bitte einloggen.';
            return $users;
        }
        $key = strtolower($name);
        if (isset($users[$key])) {
            $error = 'Diesen Benutzernamen gibt es schon.';
            return $users;
        }
        $users[$key] = [
            'name'    => $name,
            'hash'    => $hash,
            'admin'   => $admin,
            'created' => date('Y-m-d H:i'),
        ];
        return $users;
    });
    return $error;
}

/** Nutzer ändern über Callback. Gibt Fehlermeldung oder null zurück. */
function user_modify(string $name, callable $fn): ?string
{
    $error = null;
    json_update(users_file(), function (array $users) use ($name, $fn, &$error) {
        $key = strtolower($name);
        if (!isset($users[$key])) {
            $error = 'Nutzer nicht gefunden.';
            return $users;
        }
        $result = $fn($users, $key);
        if (is_string($result)) {
            $error = $result;
            return $users;
        }
        return $result;
    });
    return $error;
}

function admin_count(array $users): int
{
    return count(array_filter($users, fn($u) => !empty($u['admin'])));
}

/* ---------- Login ---------- */

/** Fingerabdruck des Passwort-Hashes: ändert sich das Passwort, fliegen alte Sessions raus */
function pw_fingerprint(array $user): string
{
    return substr(hash('sha256', $user['hash']), 0, 16);
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $name = $_SESSION['user'] ?? null;
    if (is_string($name)) {
        $u = user_get($name);
        if ($u && hash_equals(pw_fingerprint($u), (string) ($_SESSION['pwfp'] ?? ''))) {
            $user = $u;
        } else {
            unset($_SESSION['user'], $_SESSION['pwfp']);
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user['name'];
    $_SESSION['pwfp'] = pw_fingerprint($user);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/* ---------- Schutz gegen Passwort-Raten ---------- */

function attempts_file(): string
{
    return DATA_DIR . '/login_attempts.json';
}

function login_locked(): int
{
    $key = hash('sha256', client_ip());
    $a   = json_read(attempts_file())[$key] ?? null;
    if (!$a || $a['count'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    $left = (int) $a['first'] + LOGIN_LOCK_SECONDS - time();
    return max(0, $left);
}

function login_failed(): void
{
    $key = hash('sha256', client_ip());
    json_update(attempts_file(), function (array $d) use ($key) {
        $now = time();
        foreach ($d as $k => $a) { // alte Einträge entfernen
            if ((int) $a['first'] + LOGIN_LOCK_SECONDS < $now) {
                unset($d[$k]);
            }
        }
        $d[$key] = isset($d[$key])
            ? ['count' => $d[$key]['count'] + 1, 'first' => $d[$key]['first']]
            : ['count' => 1, 'first' => $now];
        return $d;
    });
}

function login_reset(): void
{
    $key = hash('sha256', client_ip());
    json_update(attempts_file(), function (array $d) use ($key) {
        unset($d[$key]);
        return $d;
    });
}

/** Prüft Login-Daten, mit konstantem Aufwand auch bei unbekanntem Nutzer */
function login_check(string $name, string $pw): ?array
{
    $u = user_get($name);
    $hash = $u['hash'] ?? '$2y$10$abcdefghijklmnopqrstuuXv1Hc1ZbG2O7eXnOaXu8Bq8a0b1c2d3e';
    $ok = password_verify($pw, $hash);
    return ($u && $ok) ? $u : null;
}
