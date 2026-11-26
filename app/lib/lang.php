<?php
/* Suchmaschine – Sprache: Deutsch oder Englisch, je nach Browser oder Auswahl.
   Die Texte stehen im Code auf Deutsch, die englische Fassung steht in app/lang/en.php. */
defined('SM_VERSION') || exit;

function smLang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $c = $_COOKIE['sm_lang'] ?? '';
    if ($c === 'de' || $c === 'en') return $lang = $c;
    $al = strtolower(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    return $lang = ($al === '' || strncmp($al, 'de', 2) === 0) ? 'de' : 'en';
}

function smLangCookie(string $l): void
{
    setcookie('sm_lang', $l, ['expires' => time() + 365 * 86400, 'path' => smBase(), 'secure' => smIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** Übersetzt einen deutschen Text (bei Englisch) und setzt Werte für %s/%d ein */
function t(string $de, ...$args): string
{
    static $en = null;
    $s = $de;
    if (smLang() === 'en') {
        if ($en === null) $en = (array)(require SM_APP . '/lang/en.php');
        $s = $en[$de] ?? $de;
    }
    return $args ? vsprintf($s, $args) : $s;
}
