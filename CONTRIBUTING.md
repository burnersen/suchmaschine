# Mitmachen

*English below.*

Schön, dass du helfen willst! Fehlerberichte, Ideen, Übersetzungen und Code sind willkommen.

## Fehler melden und Ideen vorschlagen

- Über [Issues](../../issues), dort gibt es Vorlagen für Fehler und Ideen.
- Bei Fehlern hilft: Version (steht unter *Verwaltung → Update*), PHP-Version, Hoster, was du gemacht hast und was passiert ist.
- **Niemals API-Keys, Login-Tokens, Passwörter oder Inhalte aus `data/` posten.**
- Sicherheitslücken bitte nicht öffentlich melden, siehe [SECURITY.md](SECURITY.md).

## Code beitragen

1. Repository forken, einen Branch anlegen.
2. Ändern. Die Grundsätze des Projekts:
   - **Keine Abhängigkeiten.** Kein Composer, kein npm, keine Frameworks, nichts von fremden Servern laden. PHP 8.0 und SQLite müssen reichen.
   - **Läuft auf normalem Webspace.** Kein Shell-Zugriff, kein Cronjob, keine Dienste nötig.
   - **Ohne JavaScript benutzbar.** JavaScript macht es bequemer, ist aber keine Voraussetzung.
   - **Datenschutz zuerst.** Keine Tracker, keine Protokolle über Suchen, nichts Persönliches an Dritte.
   - Texte im Code auf Deutsch mit `t('…')`, die englische Fassung in `app/lang/en.php`.
3. Prüfen: `php tools/build-release.php` muss ohne Fehler durchlaufen (Syntax, Tests, Installer bauen und installieren). Siehe [BUILD.md](BUILD.md).
4. Pull Request stellen und kurz beschreiben, was sich ändert und warum.

## Übersetzen

Alle Texte stehen im Code auf Deutsch. `app/lang/en.php` liefert die englische Fassung: links der deutsche Text genau wie im Code, rechts die Übersetzung. `php tests/run.php` meldet, wenn ein Text noch fehlt oder Platzhalter wie `%s` nicht zusammenpassen.

---

# Contributing

Bug reports, ideas, translations and code are welcome. Use the issue templates, never post API keys, tokens or anything from `data/`, and report security problems privately (see [SECURITY.md](SECURITY.md)).

Principles: no dependencies (no Composer, npm or frameworks), runs on plain shared hosting with PHP 8.0 and SQLite, usable without JavaScript, privacy first. UI texts are written in German in the code with `t('…')`; English lives in `app/lang/en.php`. Before opening a pull request, run `php tools/build-release.php` (see [BUILD.md](BUILD.md)); it must pass.
