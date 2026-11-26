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
- Updates werden per Prüfsumme und per Unterschrift (Ed25519) kontrolliert. Ab Version 1.5.4 spielt die Suchmaschine nur Updates ein, die mit dem geheimen Schlüssel des Projekts unterschrieben sind.

## Öffentlicher Schlüssel für die Releases

Jedes Release ab 1.5.4 hat neben `suchmaschine-install.php` die Datei `suchmaschine-install.php.sig`. Sie passt zu diesem öffentlichen Schlüssel (Ed25519, Base64), der auch in `app/lib/update.php` steht:

```
QPO+CSP5Y7S4S5WblMGZRnMl1VT9ypbCJhEVtM3zNoY=
```

Selbst prüfen (PHP mit sodium):

```sh
php -r 'echo sodium_crypto_sign_verify_detached(base64_decode(trim(file_get_contents("suchmaschine-install.php.sig"))), file_get_contents("suchmaschine-install.php"), base64_decode("QPO+CSP5Y7S4S5WblMGZRnMl1VT9ypbCJhEVtM3zNoY=")) ? "Unterschrift gültig\n" : "UNGÜLTIG\n";'
```
- `data/` und `app/` sind per `.htaccess` gesperrt. Unter nginx muss man das selbst einrichten, die Verwaltung prüft es unter *Update → Schutz der Daten*.

---

# Security

Release signing key (Ed25519, base64), also in `app/lib/update.php`: `QPO+CSP5Y7S4S5WblMGZRnMl1VT9ypbCJhEVtM3zNoY=`. Every release from 1.5.4 on ships `suchmaschine-install.php.sig`; installations only apply updates with a valid signature.

Please do **not** open a public issue. Report privately via **Security → Report a vulnerability** in this repository. Include the version, steps to reproduce and the impact. Fixes ship in a new release that every installation can install under *Admin → Update*. Only the latest version receives security fixes.
