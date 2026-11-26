<?php
/**
 * Suchmaschine – Schlüsselpaar für die Unterschrift der Releases erzeugen (nur Kommandozeile, einmalig)
 *
 *   php tools/sign-key.php
 *
 * Gibt zwei Werte aus:
 *   öffentlicher Schlüssel  → in app/lib/update.php bei SM_UPDATE_PUBKEY eintragen und committen
 *   geheimer Schlüssel      → auf GitHub als Secret SM_SIGN_KEY speichern (Settings → Secrets and variables → Actions)
 *
 * Den geheimen Schlüssel niemals committen oder weitergeben. Wer ihn hat, kann Updates für alle Installationen unterschreiben.
 * Geht er verloren, braucht es ein neues Paar, und alle Installationen müssen einmal von Hand aktualisiert werden.
 * Ausführlich in BUILD.md.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "Die PHP-Erweiterung sodium fehlt.\n");
    exit(1);
}
$kp = sodium_crypto_sign_keypair();
echo "Öffentlicher Schlüssel (in app/lib/update.php bei SM_UPDATE_PUBKEY eintragen):\n\n  "
   . base64_encode(sodium_crypto_sign_publickey($kp)) . "\n\n"
   . "Geheimer Schlüssel (nur als GitHub-Secret SM_SIGN_KEY speichern, NIE committen):\n\n  "
   . base64_encode(sodium_crypto_sign_secretkey($kp)) . "\n\n";
