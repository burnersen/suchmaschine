# Sicherheit

*English below.*

## Eine Lücke gefunden?

Bitte **nicht** als öffentliches Issue melden. Stattdessen über GitHub privat: im Repository unter **Security → Report a vulnerability** (Private vulnerability reporting).

Hilfreich sind: betroffene Version, Schritte zum Nachstellen, was ein Angreifer damit erreichen kann. Du bekommst so schnell wie möglich eine Antwort. Behoben wird in einer neuen Version, die alle Installationen über *Verwaltung → Update* einspielen können. Auf Wunsch wirst du in den Release-Notizen genannt.

## Unterstützte Versionen

Sicherheitskorrekturen gibt es für die jeweils neueste Version. Bitte immer aktualisieren.

## Was die Suchmaschine selbst schützt

- Passwörter mit `password_hash`, Sitzungen und Login-Tokens nur als Prüfsumme in der Datenbank.
- Schutz gegen fremde Formulare (CSRF), strenge Content-Security-Policy, keine Einbettung in fremde Seiten.
- Wartezeit nach Fehlversuchen beim Anmelden.
- Bild-Proxy und Lesemodus rufen nur öffentliche Adressen ab, keine internen Netze.
- Updates werden per Prüfsumme kontrolliert, auf Wunsch zusätzlich per Unterschrift (siehe [BUILD.md](BUILD.md)).
- `data/` und `app/` sind per `.htaccess` gesperrt. Unter nginx muss man das selbst einrichten, die Verwaltung prüft es unter *Update → Schutz der Daten*.

---

# Security

Please do **not** open a public issue. Report privately via **Security → Report a vulnerability** in this repository. Include the version, steps to reproduce and the impact. Fixes ship in a new release that every installation can install under *Admin → Update*. Only the latest version receives security fixes.
