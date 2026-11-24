<?php
declare(strict_types=1);
defined('PRIVATE_DIR') || exit;

function app_run(): void
{
    send_security_headers();
    header('Cache-Control: no-store');
    maintenance();

    $action = (string) ($_GET['a'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    if ($action === 'opensearch') {
        page_opensearch();
        return;
    }

    if ($isPost && !csrf_ok()) {
        flash('error', 'Die Sitzung ist abgelaufen. Bitte nochmal versuchen.');
        redirect(current_url());
    }

    // Ersteinrichtung: noch kein Nutzer vorhanden
    if (!users_exist()) {
        page_setup($isPost);
        return;
    }

    $user = current_user();
    if (!$user) {
        page_login($isPost);
        return;
    }

    switch ($action) {
        case 'logout':
            if ($isPost) {
                logout_user();
                flash('ok', 'Du bist ausgeloggt.');
            }
            redirect(base_path());
            // no break
        case 'account':
            page_account($user, $isPost);
            return;
        case 'admin':
            if (empty($user['admin'])) {
                redirect(base_path());
            }
            page_admin($user, $isPost);
            return;
        default:
            page_search($user);
    }
}

/* ---------- Ersteinrichtung ---------- */

function page_setup(bool $isPost): void
{
    $error = null;
    $name  = '';
    if ($isPost) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $pw   = (string) ($_POST['password'] ?? '');
        if ($pw !== (string) ($_POST['password2'] ?? '')) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $error = user_create($name, $pw, true, true);
        }
        if ($error === null) {
            login_user(user_get($name));
            flash('ok', 'Willkommen! Du bist jetzt Admin und kannst unter „Verwaltung“ weitere Nutzer anlegen.');
            redirect(base_path());
        }
    }
    render('setup', ['title' => 'Einrichtung', 'error' => $error, 'name' => $name]);
}

/* ---------- Login ---------- */

function page_login(bool $isPost): void
{
    $error = null;
    $name  = '';
    if ($isPost && ($_POST['form'] ?? '') === 'login') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $pw   = (string) ($_POST['password'] ?? '');
        if ($wait = login_locked()) {
            $error = 'Zu viele Fehlversuche. Bitte in ' . (int) ceil($wait / 60) . ' Minuten nochmal probieren.';
        } elseif ($u = login_check($name, $pw)) {
            login_reset();
            login_user($u);
            // Bei Bedarf alten Hash auffrischen
            if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($pw, PASSWORD_DEFAULT);
                user_modify($u['name'], function (array $users, string $k) use ($newHash) {
                    $users[$k]['hash'] = $newHash;
                    return $users;
                });
                login_user(user_get($u['name']));
            }
            redirect(current_url()); // zurück zur ursprünglich aufgerufenen Seite/Suche
        } else {
            login_failed();
            $error = 'Benutzername oder Passwort falsch.';
        }
    }
    render('login', ['title' => 'Login', 'error' => $error, 'name' => $name]);
}

/* ---------- Suche ---------- */

function page_search(array $user): void
{
    $q     = trim((string) ($_GET['q'] ?? ''));
    $q     = mb_substr($q, 0, 400);
    $page  = max(1, min((int) cfg('max_pages'), 10, (int) ($_GET['p'] ?? 1)));
    $exact = ($_GET['exact'] ?? '') === '1';

    if ($q === '') {
        render('home', ['title' => cfg('site_name'), 'user' => $user, 'home' => true]);
        return;
    }

    $result = null;
    $error  = null;
    try {
        $result = search($q, $page, $exact);
    } catch (SearchException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        error_log('Suche fehlgeschlagen: ' . $ex);
        $error = 'Da ist intern etwas schiefgelaufen. Details stehen im PHP-Fehlerlog.';
    }

    render('search', [
        'title'  => $q . ' · ' . cfg('site_name'),
        'user'   => $user,
        'q'      => $q,
        'page'   => $page,
        'exact'  => $exact,
        'result' => $result,
        'error'  => $error,
    ]);
}

/* ---------- Eigenes Konto ---------- */

function page_account(array $user, bool $isPost): void
{
    if ($isPost) {
        $old = (string) ($_POST['old'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($old, $user['hash'])) {
            flash('error', 'Das aktuelle Passwort stimmt nicht.');
        } elseif ($new !== (string) ($_POST['password2'] ?? '')) {
            flash('error', 'Die neuen Passwörter stimmen nicht überein.');
        } elseif ($err = password_valid($new)) {
            flash('error', $err);
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            user_modify($user['name'], function (array $users, string $k) use ($hash) {
                $users[$k]['hash'] = $hash;
                return $users;
            });
            login_user(user_get($user['name']));
            flash('ok', 'Passwort geändert. Andere Geräte sind jetzt ausgeloggt.');
        }
        redirect(url(['a' => 'account']));
    }
    render('account', ['title' => 'Mein Konto', 'user' => $user]);
}

/* ---------- Verwaltung (nur Admin) ---------- */

function page_admin(array $user, bool $isPost): void
{
    if ($isPost) {
        $do     = (string) ($_POST['do'] ?? '');
        $target = trim((string) ($_POST['name'] ?? ''));
        $self   = strtolower($target) === strtolower($user['name']);
        $error  = null;
        $ok     = null;

        switch ($do) {
            case 'create':
                $error = user_create($target, (string) ($_POST['password'] ?? ''), !empty($_POST['admin']));
                $ok = 'Nutzer „' . $target . '“ angelegt.';
                break;

            case 'delete':
                if ($self) {
                    $error = 'Du kannst dich nicht selbst löschen.';
                    break;
                }
                $error = user_modify($target, function (array $users, string $k) {
                    unset($users[$k]);
                    return $users;
                });
                $ok = 'Nutzer „' . $target . '“ gelöscht.';
                break;

            case 'password':
                $pw = (string) ($_POST['password'] ?? '');
                if ($error = password_valid($pw)) {
                    break;
                }
                $hash  = password_hash($pw, PASSWORD_DEFAULT);
                $error = user_modify($target, function (array $users, string $k) use ($hash) {
                    $users[$k]['hash'] = $hash;
                    return $users;
                });
                if ($self && !$error) {
                    login_user(user_get($user['name']));
                }
                $ok = 'Passwort für „' . $target . '“ geändert.';
                break;

            case 'toggle_admin':
                $error = user_modify($target, function (array $users, string $k) {
                    if (!empty($users[$k]['admin']) && admin_count($users) <= 1) {
                        return 'Es muss mindestens einen Admin geben.';
                    }
                    $users[$k]['admin'] = empty($users[$k]['admin']);
                    return $users;
                });
                $ok = 'Rolle von „' . $target . '“ geändert.';
                break;

            default:
                $error = 'Unbekannte Aktion.';
        }
        flash($error ? 'error' : 'ok', $error ?? $ok);
        redirect(url(['a' => 'admin']));
    }

    render('admin', [
        'title'   => 'Verwaltung',
        'user'    => $user,
        'users'   => users_all(),
        'history' => usage_history(),
    ]);
}

/* ---------- OpenSearch (Suche als Browser-Standard) ---------- */

function page_opensearch(): void
{
    $origin = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $tpl    = $origin . base_path() . '?q={searchTerms}';
    header('Content-Type: application/opensearchdescription+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">' . "\n";
    echo '  <ShortName>' . e(cfg('site_name')) . "</ShortName>\n";
    echo '  <Description>Private Suche</Description>' . "\n";
    echo '  <InputEncoding>UTF-8</InputEncoding>' . "\n";
    echo '  <Image type="image/svg+xml">' . e($origin . base_path() . 'assets/favicon.svg') . "</Image>\n";
    echo '  <Url type="text/html" method="get" template="' . e($tpl) . '"/>' . "\n";
    echo "</OpenSearchDescription>\n";
}
