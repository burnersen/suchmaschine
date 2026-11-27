/* Suchmaschine – Text-Schnipsel abholen und im Browser entschlüsseln (AES-256-GCM).
   Neu (v2): Schlüssel kommt ganz aus dem Passwort (PBKDF2), der Link ist kurz. Alt (v1): Schlüssel hinter dem # im Link. */
(function () {
'use strict';
var box = document.querySelector('[data-note]');
var form = box && box.querySelector('[data-note-form]');
if (!form) return;
var L = function (k) { return box.getAttribute('data-l-' + k) || ''; };
var errBox = box.querySelector('[data-note-err]');
function err(m) { errBox.textContent = m; errBox.hidden = !m; }
function b64d(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var b = atob(s), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return a; }
function hex(a) { return Array.prototype.map.call(a, function (x) { return ('0' + x.toString(16)).slice(-2); }).join(''); }
var v2 = box.getAttribute('data-v') === '2';
var key = null;
if (!v2) {
    var frag = location.hash.slice(1);
    if (!/^[A-Za-z0-9_-]{40,}$/.test(frag)) { err(L('nokey')); form.hidden = true; return; }
    // Den Schlüssel sofort aus der Adressleiste nehmen, damit er nicht im Verlauf oder in Bildschirmfotos landet
    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
    key = b64d(frag);
}
if (!window.crypto || !crypto.subtle) { err(L('https')); form.hidden = true; return; }
async function derive(pass, salt) {
    var base = await crypto.subtle.importKey('raw', new TextEncoder().encode(pass), 'PBKDF2', false, ['deriveBits']);
    return new Uint8Array(await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: salt, iterations: v2 ? 600000 : 310000 }, base, 512));
}
form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    err('');
    var b = form.querySelector('button');
    b.disabled = true;
    try {
        var raw = key, auth = '';
        if (v2) {
            var bits2 = await derive(form.querySelector('input[name=pass]').value, b64d(box.getAttribute('data-salt')));
            raw = bits2.slice(0, 32);
            auth = hex(bits2.slice(32));
        } else if (box.getAttribute('data-pass') === '1') {
            var bits = await derive(form.querySelector('input[name=pass]').value, b64d(box.getAttribute('data-salt')));
            var both = new Uint8Array(64);
            both.set(key.slice(0, 32), 0);
            both.set(bits.slice(0, 32), 32);
            raw = new Uint8Array(await crypto.subtle.digest('SHA-256', both));
            auth = hex(bits.slice(32));
        }
        var body = new URLSearchParams();
        body.set('id', box.getAttribute('data-note'));
        body.set('auth', auth);
        var r = await (await fetch('?a=noteget', { method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store' })).json();
        if (!r.ok) { err(r.message || L('err')); b.disabled = false; return; }
        var d = JSON.parse(r.data);
        var k = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['decrypt']);
        var plain;
        try { plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: b64d(d.iv) }, k, b64d(d.ct)); } catch (e) { err(L('wrong')); return; }
        var j = JSON.parse(new TextDecoder().decode(plain));
        form.hidden = true;
        box.querySelector('[data-note-text]').textContent = j.t || '';
        box.querySelector('[data-note-out]').hidden = false;
        box.querySelector('[data-note-info]').textContent = r.left === 0 ? L('gone') : (r.left > 0 ? L('left').replace('%d', r.left) : '');
        var c = box.querySelector('[data-note-copy]');
        c.addEventListener('click', function () { navigator.clipboard.writeText(j.t || '').then(function () { c.textContent = L('done'); setTimeout(function () { c.textContent = L('copy'); }, 1500); }); });
    } catch (e) {
        err(L('err') + ' (' + e.message + ')');
        b.disabled = false;
    }
});
})();
