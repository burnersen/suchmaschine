/* Suchmaschine – Werkzeuge für Entwickler. Alles läuft im Browser, nur .htpasswd fragt den eigenen Server (bcrypt).
   JSON, Kodieren, Hash, JWT, UUID und IDs, Regex, Cron, Text vergleichen, Farben, Zahlensysteme, Subnetz, chmod, .htpasswd, Text-Werkzeuge, Zeitstempel */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

const area = (ph, rows, val) => el('textarea', { class: 'in code', rows: rows || 8, placeholder: ph || '', spellcheck: 'false', autocomplete: 'off', value: val || '' });
const outBox = () => el('div', { class: 'out-box' });
const copyBtn = (get, label) => btn(label || T('Kopieren'), 'btn-second btn-sm', () => CF.copy(typeof get === 'function' ? get() : get), 'copy');
function kv(rows) {
    const g = el('div', { class: 'kv' });
    rows.filter(Boolean).forEach(([k, v]) => g.append(el('div', { class: 'k', text: k }), el('div', { class: 'v mono break', text: v == null ? '–' : String(v) }), el('div', { class: 'a' }, v != null && v !== '' ? ibtn('copy', T('Kopieren'), () => CF.copy(String(v)), 'sm') : null)));
    return g;
}
const utf8 = s => new TextEncoder().encode(s);
const deutf8 = b => new TextDecoder().decode(b);
const toHex = b => Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
function b64enc(bytes, url) { let s = ''; bytes.forEach(x => { s += String.fromCharCode(x); }); s = btoa(s); return url ? s.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') : s; }
function b64dec(s) { s = String(s).trim().replace(/\s+/g, '').replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; const b = atob(s); return Uint8Array.from(b, c => c.charCodeAt(0)); }
const two = (a, b) => el('div', { class: 'layout' }, el('div', { class: 'stack lg' }, a), el('div', { class: 'stack lg' }, b));

/* ---------- JSON ---------- */
function jsonToCsv(v) {
    const rows = Array.isArray(v) ? v : [v];
    const cols = [];
    rows.forEach(r => { if (r && typeof r === 'object') Object.keys(r).forEach(k => { if (cols.indexOf(k) < 0) cols.push(k); }); });
    if (!cols.length) throw new Error(T('Für CSV braucht es eine Liste von Objekten.'));
    return [cols].concat(rows.map(r => cols.map(c => { const x = r ? r[c] : ''; return x && typeof x === 'object' ? JSON.stringify(x) : x; })));
}
CF.views.json = {
    mount(root, params) {
        const inp = area('{"name": "Beispiel", "werte": [1, 2, 3]}', 14, params.text || CF.ls.get('dev.json', ''));
        const out = outBox();
        const status = el('div');
        let indent = '  ', sortKeys = false, lastOut = '';
        const sortDeep = v => Array.isArray(v) ? v.map(sortDeep) : v && typeof v === 'object' ? Object.keys(v).sort().reduce((o, k) => { o[k] = sortDeep(v[k]); return o; }, {}) : v;
        function parse() {
            const s = inp.value.trim();
            if (!s) { fill(status); out.textContent = ''; return null; }
            try {
                const v = JSON.parse(s);
                fill(status, CF.msg('ok', T('Gültiges JSON · %s', Array.isArray(v) ? T('Liste mit %s Einträgen', v.length) : v && typeof v === 'object' ? T('Objekt mit %s Schlüsseln', Object.keys(v).length) : typeof v)));
                return v;
            } catch (e) {
                const m = /position (\d+)/.exec(e.message);
                let where = '';
                if (m) { const pos = +m[1]; const before = s.slice(0, pos); where = ' · ' + T('Zeile %s, Spalte %s', before.split('\n').length, pos - before.lastIndexOf('\n')); }
                fill(status, CF.msg('danger', T('Kein gültiges JSON: %s', e.message) + where));
                return undefined;
            }
        }
        function run(mode) {
            CF.ls.set('dev.json', inp.value.slice(0, 200000));
            let v = parse();
            if (v === undefined || v === null && !inp.value.trim()) return;
            if (sortKeys) v = sortDeep(v);
            if (mode === 'min') lastOut = JSON.stringify(v);
            else if (mode === 'csv') { try { lastOut = CF.csv(jsonToCsv(v)).replace(/^﻿/, ''); } catch (e) { fill(status, CF.msg('warn', e.message)); return; } }
            else lastOut = JSON.stringify(v, null, indent);
            out.textContent = lastOut;
        }
        inp.addEventListener('input', CF.debounce(() => run('fmt'), 300));
        fill(root, two(
            [field(T('Eingabe'), inp), el('div', { class: 'row' },
                btn(T('Formatieren'), 'btn-primary', () => run('fmt'), 'wand'), btn(T('Verkleinern'), 'btn-second', () => run('min'), 'zoom'),
                btn('CSV', 'btn-second', () => run('csv'), 'grid'),
                select([['  ', T('2 Leerzeichen')], ['    ', T('4 Leerzeichen')], ['\t', 'Tab']], indent, v => { indent = v; run('fmt'); }, { style: { width: 'auto' } }),
                CF.toggle(T('Schlüssel sortieren'), false, v => { sortKeys = v; run('fmt'); })), status],
            [field(T('Ergebnis'), out), el('div', { class: 'row' }, copyBtn(() => lastOut), btn(T('Herunterladen'), 'btn-ghost btn-sm', () => CF.download('daten.' + (lastOut.trim()[0] === '{' || lastOut.trim()[0] === '[' ? 'json' : 'csv'), new Blob([lastOut], { type: 'text/plain' })), 'download'))]));
        run('fmt');
    },
};

/* ---------- Kodieren ---------- */
const CODECS = {
    b64: ['Base64', s => b64enc(utf8(s)), s => deutf8(b64dec(s))],
    b64u: ['Base64url', s => b64enc(utf8(s), true), s => deutf8(b64dec(s))],
    url: [T('URL (Komponente)'), s => encodeURIComponent(s), s => decodeURIComponent(s.replace(/\+/g, ' '))],
    urlf: [T('URL (ganze Adresse)'), s => encodeURI(s), s => decodeURI(s)],
    html: [T('HTML-Entities'), s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])).replace(/[ -￿]/g, c => '&#' + c.charCodeAt(0) + ';'),
        s => { const t = document.createElement('textarea'); t.innerHTML = s; return t.value; }],
    hex: ['Hex', s => toHex(utf8(s)), s => deutf8(Uint8Array.from((s.replace(/[^0-9a-f]/gi, '').match(/../g) || []).map(x => parseInt(x, 16))))],
    bin: [T('Binär'), s => Array.from(utf8(s), x => x.toString(2).padStart(8, '0')).join(' '), s => deutf8(Uint8Array.from((s.replace(/[^01]/g, '').match(/.{8}/g) || []).map(x => parseInt(x, 2))))],
    uni: [T('Unicode-Escape'), s => Array.from(s, c => { const cp = c.codePointAt(0); return cp < 128 ? c : cp > 0xffff ? '\\u{' + cp.toString(16) + '}' : '\\u' + cp.toString(16).padStart(4, '0'); }).join(''),
        s => s.replace(/\\u\{([0-9a-f]+)\}|\\u([0-9a-f]{4})|\\x([0-9a-f]{2})/gi, (m, a, b, c) => String.fromCodePoint(parseInt(a || b || c, 16)))],
    rot13: ['ROT13', s => s.replace(/[a-z]/gi, c => String.fromCharCode((c <= 'Z' ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26)), s => CODECS.rot13[1](s)],
};
CF.views.kodieren = {
    mount(root) {
        let codec = CF.ls.get('dev.codec', 'b64');
        const a = area(T('Klartext'), 9), b = area(T('Kodiert'), 9);
        const msg = el('div');
        const enc = () => { try { b.value = CODECS[codec][1](a.value); fill(msg); } catch (e) { fill(msg, CF.msg('warn', e.message)); } };
        const dec = () => { try { a.value = CODECS[codec][2](b.value); fill(msg); } catch (e) { fill(msg, CF.msg('warn', T('Lässt sich nicht dekodieren: %s', e.message))); } };
        a.addEventListener('input', enc);
        b.addEventListener('input', dec);
        fill(root, el('div', { class: 'stack lg' },
            CF.seg(Object.entries(CODECS).map(([k, v]) => [k, v[0]]), codec, v => { codec = v; CF.ls.set('dev.codec', v); enc(); }),
            two([field(T('Klartext'), a), el('div', { class: 'row' }, copyBtn(() => a.value), btn(T('Kodieren →'), 'btn-primary btn-sm', enc, 'right'))],
                [field(T('Kodiert'), b), el('div', { class: 'row' }, copyBtn(() => b.value), btn(T('← Dekodieren'), 'btn-primary btn-sm', dec, 'refresh'))]), msg,
            el('p', { class: 'tiny faint', text: T('Tipp: einfach in eines der beiden Felder tippen oder einfügen, das andere rechnet sofort mit.') })));
    },
};

/* ---------- Hash ---------- */
// MD5 (für Prüfsummen von Downloads; nicht für Passwörter). Nach RFC 1321.
function md5(bytes) {
    const K = new Int32Array(64), S = [7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21];
    for (let i = 0; i < 64; i++) K[i] = Math.floor(Math.abs(Math.sin(i + 1)) * 4294967296) | 0;
    const len = bytes.length, n = ((len + 8) >>> 6) + 1, w = new Int32Array(n * 16);
    for (let i = 0; i < len; i++) w[i >> 2] |= bytes[i] << ((i % 4) * 8);
    w[len >> 2] |= 0x80 << ((len % 4) * 8);
    w[n * 16 - 2] = len * 8;
    w[n * 16 - 1] = Math.floor(len / 536870912);
    let a0 = 0x67452301, b0 = 0xefcdab89 | 0, c0 = 0x98badcfe | 0, d0 = 0x10325476;
    for (let o = 0; o < w.length; o += 16) {
        let a = a0, b = b0, c = c0, d = d0;
        for (let i = 0; i < 64; i++) {
            let f, g;
            if (i < 16) { f = (b & c) | (~b & d); g = i; } else if (i < 32) { f = (d & b) | (~d & c); g = (5 * i + 1) % 16; } else if (i < 48) { f = b ^ c ^ d; g = (3 * i + 5) % 16; } else { f = c ^ (b | ~d); g = (7 * i) % 16; }
            const tmp = d; d = c; c = b;
            const x = (a + f + K[i] + w[o + g]) | 0;
            b = (b + ((x << S[i]) | (x >>> (32 - S[i])))) | 0;
            a = tmp;
        }
        a0 = (a0 + a) | 0; b0 = (b0 + b) | 0; c0 = (c0 + c) | 0; d0 = (d0 + d) | 0;
    }
    return [a0, b0, c0, d0].map(v => [0, 8, 16, 24].map(s => ((v >>> s) & 255).toString(16).padStart(2, '0')).join('')).join('');
}
function crc32(bytes) {
    let c, crc = -1;
    for (let i = 0; i < bytes.length; i++) { c = (crc ^ bytes[i]) & 255; for (let k = 0; k < 8; k++) c = c & 1 ? (c >>> 1) ^ 0xedb88320 : c >>> 1; crc = (crc >>> 8) ^ c; }
    return ((crc ^ -1) >>> 0).toString(16).padStart(8, '0');
}
async function allHashes(bytes, key) {
    const out = [];
    for (const alg of ['SHA-256', 'SHA-512', 'SHA-384', 'SHA-1']) out.push([alg, toHex(new Uint8Array(await crypto.subtle.digest(alg, bytes)))]);
    out.push(['MD5', md5(bytes)], ['CRC32', crc32(bytes)]);
    if (key) {
        for (const alg of ['SHA-256', 'SHA-512']) {
            const k = await crypto.subtle.importKey('raw', utf8(key), { name: 'HMAC', hash: alg }, false, ['sign']);
            out.push(['HMAC-' + alg, toHex(new Uint8Array(await crypto.subtle.sign('HMAC', k, bytes)))]);
        }
    }
    return out;
}
CF.views.hash = {
    mount(root) {
        const t = area(T('Text, dessen Prüfsumme du brauchst'), 6);
        const key = input({ placeholder: T('optional: Schlüssel für HMAC'), autocomplete: 'off', spellcheck: 'false' });
        const cmp = input({ placeholder: T('optional: erwartete Prüfsumme zum Vergleichen'), class: 'in mono', autocomplete: 'off', spellcheck: 'false' });
        const out = el('div');
        let last = null, src = '';
        async function show(bytes, label) {
            if (!crypto.subtle) { fill(out, CF.msg('danger', T('Prüfsummen gehen nur über HTTPS.'))); return; }
            last = bytes; src = label;
            const list = await allHashes(bytes, key.value);
            const want = cmp.value.trim().toLowerCase().replace(/[^0-9a-f]/g, '');
            const match = want ? list.find(x => x[1] === want) : null;
            fill(out, el('div', { class: 'stack' }, el('div', { class: 'small muted', text: label + ' · ' + T('%s Bytes', CF.fmtNum(bytes.length)) }),
                want ? CF.msg(match ? 'ok' : 'danger', match ? T('Passt! Stimmt mit %s überein.', match[0]) : T('Passt nicht zu den berechneten Prüfsummen.')) : null,
                kv(list.map(([a, h]) => [a, h]))));
        }
        t.addEventListener('input', CF.debounce(() => show(utf8(t.value), T('Text')), 200));
        key.addEventListener('input', CF.debounce(() => last && show(last, src), 250));
        cmp.addEventListener('input', CF.debounce(() => last && show(last, src), 250));
        const drop = el('div', { class: 'drop', onclick: async () => { const f = await CF.pickFile('*/*'); if (f) file(f); } }, T('Datei wählen oder hierher ziehen (bleibt im Browser)'));
        drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
        drop.addEventListener('dragleave', () => drop.classList.remove('over'));
        drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); if (e.dataTransfer.files[0]) file(e.dataTransfer.files[0]); });
        async function file(f) {
            if (f.size > 1024 * 1048576) { CF.toast(T('Höchstens 1 GB.'), 'warn'); return; }
            fill(out, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Lese %s …', f.name)));
            show(new Uint8Array(await f.arrayBuffer()), f.name);
        }
        fill(root, two([field(T('Text'), t), drop, field('HMAC', key), field(T('Vergleichen'), cmp)], [CF.card(T('Prüfsummen'), 'hash', out)]));
        show(utf8(''), T('Text'));
    },
};

/* ---------- JWT ---------- */
CF.views.jwt = {
    mount(root, params) {
        const t = area('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9…', 6, params.text || '');
        const secret = input({ placeholder: T('optional: Schlüssel für HS256/384/512'), autocomplete: 'off', spellcheck: 'false' });
        const out = el('div', { class: 'stack' });
        const time = v => typeof v === 'number' ? new Date(v * 1000).toLocaleString(CF.locale()) : '';
        async function run() {
            const s = t.value.trim().replace(/^Bearer\s+/i, '');
            if (!s) { fill(out); return; }
            const parts = s.split('.');
            if (parts.length < 2) { fill(out, CF.msg('danger', T('Ein JWT besteht aus drei Teilen, getrennt durch Punkte.'))); return; }
            let h, p;
            try { h = JSON.parse(deutf8(b64dec(parts[0]))); p = JSON.parse(deutf8(b64dec(parts[1]))); } catch (e) { fill(out, CF.msg('danger', T('Lässt sich nicht lesen: %s', e.message))); return; }
            const now = Date.now() / 1000;
            const notes = [];
            if (typeof p.exp === 'number') notes.push(p.exp < now ? CF.msg('danger', T('Abgelaufen am %s', time(p.exp))) : CF.msg('ok', T('Gültig bis %s', time(p.exp))));
            if (typeof p.nbf === 'number' && p.nbf > now) notes.push(CF.msg('warn', T('Erst gültig ab %s', time(p.nbf))));
            if (h.alg === 'none') notes.push(CF.msg('danger', T('Unsigniert (alg: none). Niemals so akzeptieren!')));
            let sig = null;
            if (secret.value && /^HS(256|384|512)$/.test(h.alg) && parts[2] !== undefined) {
                const k = await crypto.subtle.importKey('raw', utf8(secret.value), { name: 'HMAC', hash: 'SHA-' + h.alg.slice(2) }, false, ['sign']);
                const calc = b64enc(new Uint8Array(await crypto.subtle.sign('HMAC', k, utf8(parts[0] + '.' + parts[1]))), true);
                sig = calc === parts[2];
                notes.push(CF.msg(sig ? 'ok' : 'danger', sig ? T('Signatur stimmt.') : T('Signatur stimmt NICHT.')));
            }
            fill(out, notes,
                CF.card(T('Kopf'), 'settings', el('div', { class: 'out-box', text: JSON.stringify(h, null, 2) })),
                CF.card(T('Nutzdaten'), 'text', el('div', { class: 'stack' }, el('div', { class: 'out-box', text: JSON.stringify(p, null, 2) }),
                    kv([['iat', time(p.iat)], ['nbf', time(p.nbf)], ['exp', time(p.exp)], ['sub', p.sub], ['iss', p.iss], ['aud', Array.isArray(p.aud) ? p.aud.join(', ') : p.aud]].filter(r => r[1])))),
                el('p', { class: 'tiny faint', text: T('Das Token wird nur in deinem Browser zerlegt, nichts wird gesendet.') }));
        }
        t.addEventListener('input', CF.debounce(run, 200));
        secret.addEventListener('input', CF.debounce(run, 300));
        fill(root, two([field('JWT', t), field(T('Signatur prüfen'), secret)], [out]));
        run();
    },
};

/* ---------- UUID und IDs ---------- */
const CROCK = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
function uuid4() { return crypto.randomUUID ? crypto.randomUUID() : (() => { const b = crypto.getRandomValues(new Uint8Array(16)); b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128; const h = toHex(b); return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20); })(); }
function uuid7() {
    const b = crypto.getRandomValues(new Uint8Array(16));
    let ts = Date.now();
    for (let i = 5; i >= 0; i--) { b[i] = ts % 256; ts = Math.floor(ts / 256); }
    b[6] = (b[6] & 15) | 0x70; b[8] = (b[8] & 63) | 128;
    const h = toHex(b);
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
}
function ulid() {
    let ts = Date.now(), t = '';
    for (let i = 0; i < 10; i++) { t = CROCK[ts % 32] + t; ts = Math.floor(ts / 32); }
    const r = crypto.getRandomValues(new Uint8Array(16));
    return t + Array.from(r, x => CROCK[x & 31]).join('');
}
function nanoid(n) { const a = 'useandom-26T198340PX75pxJACKVERYMINDBUSHWOLF_GQZbfghjklqvwyzrict'; return Array.from(crypto.getRandomValues(new Uint8Array(n || 21)), x => a[x & 63]).join(''); }
CF.views.uuid = {
    mount(root) {
        let kind = CF.ls.get('dev.idk', 'v4'), n = 10, upper = false, noDash = false;
        const out = outBox();
        let list = [];
        const gen = () => {
            const f = { v4: uuid4, v7: uuid7, ulid, nano: () => nanoid(21), nil: () => '00000000-0000-0000-0000-000000000000' }[kind];
            list = [];
            for (let i = 0; i < n; i++) { let s = f(); if (noDash) s = s.replace(/-/g, ''); if (upper) s = s.toUpperCase(); list.push(s); }
            out.textContent = list.join('\n');
        };
        fill(root, el('div', { class: 'stack lg' },
            CF.seg([['v4', 'UUID v4'], ['v7', T('UUID v7 (zeitlich sortiert)')], ['ulid', 'ULID'], ['nano', 'Nano ID'], ['nil', 'Nil UUID']], kind, v => { kind = v; CF.ls.set('dev.idk', v); gen(); }),
            el('div', { class: 'row' }, el('span', { class: 'small muted', text: T('Anzahl') }), CF.seg([['1', '1'], ['10', '10'], ['50', '50'], ['100', '100']], String(n), v => { n = +v; gen(); }),
                CF.toggle(T('Großbuchstaben'), upper, v => { upper = v; gen(); }), CF.toggle(T('ohne Bindestriche'), noDash, v => { noDash = v; gen(); })),
            out, el('div', { class: 'row' }, btn(T('Neu erzeugen'), 'btn-primary', gen, 'refresh'), copyBtn(() => list.join('\n'), T('Alle kopieren'))),
            el('p', { class: 'tiny faint', text: T('v4 ist rein zufällig. v7 und ULID beginnen mit der Uhrzeit und lassen sich darum gut als Datenbank-Schlüssel sortieren.') })));
        gen();
    },
};

/* ---------- Regex ---------- */
const REGEX_LIB = [['E-Mail', '[\\w.+-]+@[\\w-]+(\\.[\\w-]+)+'], ['URL', 'https?:\\/\\/[^\\s/$.?#].[^\\s]*'], ['IPv4', '\\b(?:(?:25[0-5]|2[0-4]\\d|1?\\d?\\d)\\.){3}(?:25[0-5]|2[0-4]\\d|1?\\d?\\d)\\b'],
    [T('Datum TT.MM.JJJJ'), '\\b(0?[1-9]|[12]\\d|3[01])\\.(0?[1-9]|1[0-2])\\.(\\d{4})\\b'], [T('PLZ'), '\\b\\d{5}\\b'], ['IBAN', '\\b[A-Z]{2}\\d{2}(?: ?[A-Z0-9]{4}){3,7}(?: ?[A-Z0-9]{1,3})?\\b'],
    [T('Telefon'), '(?:\\+\\d{1,3}[ -]?)?\\(?\\d{2,5}\\)?[ -/]?\\d{3,}[ -]?\\d*'], ['Hex-Farbe', '#(?:[0-9a-fA-F]{3}){1,2}\\b']];
CF.views.regex = {
    mount(root) {
        const s = CF.ls.get('dev.re', { p: '(\\w+)@(\\w+)\\.de', f: 'g', t: 'anna@beispiel.de, bernd@example.org, carla@test.de', r: '$1 bei $2' });
        const pat = input({ class: 'in mono big', value: s.p, spellcheck: 'false', autocomplete: 'off' });
        const flags = input({ class: 'in mono', value: s.f, style: { width: '6rem' }, spellcheck: 'false' });
        const text = area(T('Text zum Testen'), 8, s.t);
        const rep = input({ class: 'in mono', value: s.r, placeholder: T('Ersetzen durch ($1, $2 …)'), spellcheck: 'false' });
        const view = el('div', { class: 'out-box' });
        const info = el('div');
        const list = el('div');
        const repOut = outBox();
        function run() {
            CF.ls.set('dev.re', { p: pat.value, f: flags.value, t: text.value.slice(0, 20000), r: rep.value });
            let re;
            try { re = new RegExp(pat.value, flags.value.replace(/[^gimsuyd]/g, '')); } catch (e) { fill(info, CF.msg('danger', e.message)); view.textContent = text.value; fill(list); repOut.textContent = ''; return; }
            const g = new RegExp(re.source, re.flags.indexOf('g') < 0 ? re.flags + 'g' : re.flags);
            const ms = [];
            let m, guard = 0;
            while ((m = g.exec(text.value)) && guard++ < 2000) { ms.push(m); if (m[0] === '') g.lastIndex++; }
            fill(info, CF.msg(ms.length ? 'ok' : 'info', T('%s Treffer', ms.length)));
            const frag = [];
            let pos = 0;
            ms.forEach((x, i) => { frag.push(text.value.slice(pos, x.index)); frag.push(el('mark', { class: i % 2 ? 'hl2' : 'hl', text: x[0] || '∅' })); pos = x.index + x[0].length; });
            frag.push(text.value.slice(pos));
            fill(view, frag);
            fill(list, ms.length ? el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: '#' }), el('th', { text: T('Treffer') }), el('th', { text: T('Gruppen') }), el('th', { class: 'num', text: T('Position') })),
                ms.slice(0, 200).map((x, i) => el('tr', {}, el('td', { text: i + 1 }), el('td', { class: 'mono', text: x[0] }), el('td', { class: 'mono small', text: x.slice(1).map((v, k) => '$' + (k + 1) + '=' + (v === undefined ? '∅' : v)).join('  ') + (x.groups ? '  ' + Object.entries(x.groups).map(([k, v]) => k + '=' + v).join('  ') : '') }), el('td', { class: 'num', text: x.index }))))) : null);
            try { repOut.textContent = text.value.replace(re, rep.value); } catch (e) { repOut.textContent = ''; }
        }
        [pat, flags, text, rep].forEach(x => x.addEventListener('input', CF.debounce(run, 150)));
        fill(root, el('div', { class: 'stack lg' },
            el('div', { class: 'row nw' }, el('span', { class: 'mono big-num', text: '/' }), el('div', { class: 'grow' }, pat), el('span', { class: 'mono big-num', text: '/' }), flags),
            el('div', { class: 'chips' }, REGEX_LIB.map(([n, p]) => el('button', { type: 'button', class: 'chip', text: n, onclick: () => { pat.value = p; run(); } }))),
            info,
            two([field(T('Text'), text), field(T('Ersetzen'), rep), field(T('Ergebnis nach dem Ersetzen'), repOut)], [field(T('Treffer im Text'), view), list]),
            el('p', { class: 'tiny faint', text: T('Flags: g = alle Treffer, i = Groß/klein egal, m = ^ und $ je Zeile, s = Punkt passt auch auf Zeilenumbruch, u = Unicode. Läuft mit der Regex-Maschine deines Browsers (JavaScript).') })));
        run();
    },
};

/* ---------- Cron ---------- */
const MON = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], DOW = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
function cronField(s, lo, hi, names) {
    const set = new Set();
    s.toLowerCase().split(',').forEach(part => {
        let [r, step] = part.split('/');
        step = step ? +step : 1;
        if (!(step >= 1)) throw new Error(T('Ungültige Schrittweite: %s', part));
        let a, b;
        const num = x => { const i = names ? names.indexOf(x) : -1; if (i >= 0) return i + (lo === 1 && names === MON ? 1 : 0); const n = +x; if (!/^\d+$/.test(x)) throw new Error(T('Ungültiger Wert: %s', x)); return n; };
        if (r === '*') { a = lo; b = hi; }
        else if (r.indexOf('-') > 0) { [a, b] = r.split('-').map(num); }
        else { a = num(r); b = step > 1 ? hi : a; }
        if (names === DOW) { if (a === 7) a = 0; if (b === 7) b = 6 === b ? 6 : 7; }
        for (let i = a; i <= b; i += step) set.add(names === DOW && i === 7 ? 0 : i);
    });
    set.forEach(v => { if (v < lo || v > hi) throw new Error(T('Wert außerhalb des Bereichs %s bis %s: %s', lo, hi, v)); });
    return set;
}
function parseCron(expr) {
    const alias = { '@yearly': '0 0 1 1 *', '@annually': '0 0 1 1 *', '@monthly': '0 0 1 * *', '@weekly': '0 0 * * 0', '@daily': '0 0 * * *', '@midnight': '0 0 * * *', '@hourly': '0 * * * *' };
    let e = expr.trim().replace(/^CRON_TZ=\S+\s+/, '');
    e = alias[e.toLowerCase()] || e;
    const f = e.split(/\s+/);
    if (f.length !== 5) throw new Error(T('Ein Cron-Ausdruck hat fünf Felder: Minute Stunde Tag Monat Wochentag.'));
    return { raw: f, min: cronField(f[0], 0, 59), hour: cronField(f[1], 0, 23), dom: cronField(f[2], 1, 31), mon: cronField(f[3], 1, 12, MON), dow: cronField(f[4], 0, 7, DOW), domStar: f[2] === '*', dowStar: f[4] === '*' };
}
function cronNext(c, from, n) {
    const out = [];
    const d = new Date(from.getTime());
    d.setSeconds(0, 0);
    d.setMinutes(d.getMinutes() + 1);
    for (let i = 0; i < 600000 && out.length < n; i++) {
        const domOk = c.dom.has(d.getDate()), dowOk = c.dow.has(d.getDay());
        const dayOk = c.domStar || c.dowStar ? domOk && dowOk : domOk || dowOk;
        if (!c.mon.has(d.getMonth() + 1)) { d.setMonth(d.getMonth() + 1, 1); d.setHours(0, 0); continue; }
        if (!dayOk) { d.setDate(d.getDate() + 1); d.setHours(0, 0); continue; }
        if (!c.hour.has(d.getHours())) { d.setHours(d.getHours() + 1, 0); continue; }
        if (c.min.has(d.getMinutes())) out.push(new Date(d.getTime()));
        d.setMinutes(d.getMinutes() + 1);
    }
    return out;
}
function cronDescribe(c) {
    const list = (set, fmt) => [...set].sort((a, b) => a - b).map(fmt).join(', ');
    const days = CF.lang === 'en' ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] : ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    const months = Array.from({ length: 12 }, (x, i) => new Date(2026, i, 1).toLocaleString(CF.locale(), { month: 'long' }));
    let time;
    if (c.min.size === 60 && c.hour.size === 24) time = T('jede Minute');
    else if (c.min.size === 1 && c.hour.size === 24) time = T('jede Stunde um :%s', String([...c.min][0]).padStart(2, '0'));
    else if (c.min.size === 1 && c.hour.size <= 6) time = T('um %s', list(c.hour, h => h + ':' + String([...c.min][0]).padStart(2, '0')));
    else if (c.min.size === 60) time = T('jede Minute in den Stunden %s', list(c.hour, h => h));
    else time = T('in den Minuten %s', list(c.min, m => m)) + (c.hour.size < 24 ? ', ' + T('in den Stunden %s', list(c.hour, h => h)) : '');
    const parts = [time];
    if (!c.dowStar) parts.push(T('an %s', list(c.dow, d => days[d])));
    if (!c.domStar) parts.push(T('am %s. Tag des Monats', list(c.dom, d => d)));
    if (c.mon.size < 12) parts.push(T('im %s', list(c.mon, m => months[m - 1])));
    if (!c.domStar && !c.dowStar) parts.push(T('(Tag oder Wochentag passt)'));
    return parts.join(', ');
}
CF.views.cron = {
    mount(root, params) {
        const inp = input({ class: 'in big mono', value: params.text || CF.ls.get('dev.cron', '37 8 * * 1-5'), spellcheck: 'false', autocomplete: 'off' });
        const out = el('div', { class: 'stack' });
        const run = () => {
            CF.ls.set('dev.cron', inp.value);
            try {
                const c = parseCron(inp.value);
                const next = cronNext(c, new Date(), 10);
                fill(out, el('div', { class: 'big-num', style: { fontSize: '1.35rem' }, text: cronDescribe(c) }),
                    el('div', { class: 'grid3 small mono' }, ['Minute', T('Stunde'), T('Tag'), T('Monat'), T('Wochentag')].map((n, i) => el('div', {}, el('div', { class: 'tiny faint', text: n }), el('div', { text: c.raw[i] })))),
                    CF.card(T('Die nächsten Läufe (Uhrzeit dieses Geräts)'), 'calendar', el('div', { class: 'stack small mono' }, next.length ? next.map(d => el('div', { text: d.toLocaleString(CF.locale(), { weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) })) : T('Läuft nie.'))));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        };
        inp.addEventListener('input', CF.debounce(run, 150));
        fill(root, el('div', { class: 'stack lg' }, inp,
            el('div', { class: 'chips' }, [['*/5 * * * *', T('alle 5 Minuten')], ['0 * * * *', T('stündlich')], ['30 2 * * *', T('täglich 2:30')], ['37 8 * * 1-5', T('werktags 8:37')], ['0 0 1 * *', T('Monatsanfang')], ['0 9 * * mon', T('montags 9 Uhr')], ['@weekly', '@weekly']].map(([e, n]) => el('button', { type: 'button', class: 'chip', title: e, text: n, onclick: () => { inp.value = e; run(); } }))),
            out, el('p', { class: 'tiny faint', text: T('Fünf Felder: Minute (0–59), Stunde (0–23), Tag (1–31), Monat (1–12 oder jan–dec), Wochentag (0–7 oder sun–sat, 0 und 7 = Sonntag). Erlaubt: * , - / und @daily, @hourly …') })));
        run();
    },
};

/* ---------- Text vergleichen ---------- */
function lcsDiff(a, b) {
    const n = a.length, m = b.length;
    if (n * m > 9e6) return null;
    const dp = Array.from({ length: n + 1 }, () => new Uint32Array(m + 1));
    for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
    const out = [];
    let i = 0, j = 0;
    while (i < n && j < m) {
        if (a[i] === b[j]) { out.push(['=', a[i], b[j]]); i++; j++; }
        else if (dp[i + 1][j] >= dp[i][j + 1]) out.push(['-', a[i++]]);
        else out.push(['+', b[j++]]);
    }
    while (i < n) out.push(['-', a[i++]]);
    while (j < m) out.push(['+', b[j++]]);
    return out;
}
CF.views.diff = {
    mount(root) {
        const a = area(T('Ursprünglicher Text'), 12, CF.ls.get('dev.da', ''));
        const b = area(T('Geänderter Text'), 12, CF.ls.get('dev.db', ''));
        let ws = false, ic = false, mode = 'line';
        const out = el('div', { class: 'stack' });
        const norm = s => { if (ws) s = s.replace(/\s+/g, ' ').trim(); if (ic) s = s.toLowerCase(); return s; };
        function run() {
            CF.ls.set('dev.da', a.value.slice(0, 100000)); CF.ls.set('dev.db', b.value.slice(0, 100000));
            if (!a.value && !b.value) { fill(out); return; }
            const split = mode === 'line' ? s => s.split('\n') : s => s.split(/(\s+)/);
            const A = split(a.value), B = split(b.value);
            const d = lcsDiff(A.map(norm), B.map(norm));
            if (!d) { fill(out, CF.msg('warn', T('Die Texte sind zu groß für den Vergleich.'))); return; }
            // Originaltexte zurück zuordnen
            let ia = 0, ib = 0;
            const rows = d.map(([op]) => op === '=' ? ['=', A[ia++], B[ib++]] : op === '-' ? ['-', A[ia++]] : ['+', B[ib++]]);
            const add = rows.filter(r => r[0] === '+').length, del = rows.filter(r => r[0] === '-').length;
            const view = el('div', { class: 'out-box' });
            if (mode === 'line') rows.forEach(r => view.append(el('div', { class: r[0] === '+' ? 'diff-add' : r[0] === '-' ? 'diff-del' : '', text: (r[0] === '=' ? '  ' : r[0] + ' ') + (r[1] === '' ? ' ' : r[1]) })));
            else rows.forEach(r => view.append(el('span', { class: r[0] === '+' ? 'diff-add' : r[0] === '-' ? 'diff-del' : '', text: r[1] })));
            fill(out, el('div', { class: 'row' }, CF.badge(T('%s hinzugefügt', add), 'ok'), CF.badge(T('%s entfernt', del), 'danger'), add + del === 0 ? CF.badge(T('identisch'), 'info') : null), view);
        }
        [a, b].forEach(x => x.addEventListener('input', CF.debounce(run, 250)));
        fill(root, el('div', { class: 'stack lg' }, two([field(T('Vorher'), a)], [field(T('Nachher'), b)]),
            el('div', { class: 'row' }, CF.seg([['line', T('Zeilenweise')], ['word', T('Wortweise')]], mode, v => { mode = v; run(); }),
                CF.toggle(T('Leerzeichen ignorieren'), ws, v => { ws = v; run(); }), CF.toggle(T('Groß/klein ignorieren'), ic, v => { ic = v; run(); })), out));
        run();
    },
};

/* ---------- Farben ---------- */
function parseColor(s) {
    const c = document.createElement('canvas').getContext('2d');
    c.fillStyle = '#010203';
    c.fillStyle = String(s).trim();
    const v = c.fillStyle;
    if (v === '#010203' && !/^#?010203$/i.test(String(s).trim())) return null;
    if (v[0] === '#') return [parseInt(v.slice(1, 3), 16), parseInt(v.slice(3, 5), 16), parseInt(v.slice(5, 7), 16), 1];
    const m = v.match(/[\d.]+/g).map(Number);
    return [m[0], m[1], m[2], m[3] === undefined ? 1 : m[3]];
}
const hex2 = n => Math.round(n).toString(16).padStart(2, '0');
const toHexC = ([r, g, b]) => '#' + hex2(r) + hex2(g) + hex2(b);
function toHsl([r, g, b]) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
    let h = 0, s = 0;
    if (max !== min) {
        const d = max - min;
        s = l > .5 ? d / (2 - max - min) : d / (max + min);
        h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
        h *= 60;
    }
    return [Math.round(h), Math.round(s * 100), Math.round(l * 100)];
}
function fromHsl(h, s, l) {
    s /= 100; l /= 100;
    const k = n => (n + h / 30) % 12, a = s * Math.min(l, 1 - l);
    const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return [f(0) * 255, f(8) * 255, f(4) * 255];
}
function lum([r, g, b]) { const c = x => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * c(r) + .7152 * c(g) + .0722 * c(b); }
const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
CF.views.farben = {
    mount(root) {
        const fg = input({ class: 'in big mono', value: CF.ls.get('dev.fg', '#4f46e5') });
        const bg = input({ class: 'in mono', value: CF.ls.get('dev.bg', '#ffffff') });
        const pick = el('input', { type: 'color' }), pick2 = el('input', { type: 'color' });
        const out = el('div', { class: 'stack' });
        function sw(c, label) { const h = toHexC(c); return el('button', { type: 'button', class: 'color-sw', title: h, style: { background: h, color: lum(c) > .35 ? '#000' : '#fff' }, onclick: () => { fg.value = h; run(); } }, el('span', { text: label || h })); }
        function run() {
            CF.ls.set('dev.fg', fg.value); CF.ls.set('dev.bg', bg.value);
            const c = parseColor(fg.value), b = parseColor(bg.value);
            if (!c) { fill(out, CF.msg('danger', T('Diese Farbe kenne ich nicht. Erlaubt: #hex, rgb(), hsl(), Namen wie „tomato“.'))); return; }
            pick.value = toHexC(c); if (b) pick2.value = toHexC(b);
            const [h, s, l] = toHsl(c);
            const k = 1 - Math.max(c[0], c[1], c[2]) / 255;
            const cmyk = k >= 1 ? [0, 0, 0, 100] : [(1 - c[0] / 255 - k) / (1 - k), (1 - c[1] / 255 - k) / (1 - k), (1 - c[2] / 255 - k) / (1 - k), k].map(x => Math.round(x * 100));
            const ratio = b ? contrast(c, b) : 0;
            const pass = (min, label) => el('span', { class: ratio >= min ? 'pill-ok' : 'pill-bad', text: label + (ratio >= min ? ' ✓' : ' ✗') });
            fill(out,
                el('div', { class: 'color-big', style: { background: toHexC(c), color: b ? toHexC(b) : '#fff' } }, el('b', { text: 'Aa' }), el('span', { text: T('Beispieltext in dieser Farbe') })),
                el('div', { class: 'kv' }, [['HEX', toHexC(c)], ['RGB', 'rgb(' + c.slice(0, 3).map(Math.round).join(', ') + ')'], ['HSL', 'hsl(' + h + ', ' + s + '%, ' + l + '%)'], ['CMYK', cmyk.join('% ') + '%'],
                    ['CSS', c[3] < 1 ? 'rgba(' + c.slice(0, 3).map(Math.round).join(', ') + ', ' + c[3] + ')' : toHexC(c)]].map(([k2, v]) => [el('div', { class: 'k', text: k2 }), el('div', { class: 'v mono', text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))]).flat()),
                b ? CF.card(T('Kontrast zum Hintergrund: %s : 1', ratio.toFixed(2)), 'eye', el('div', { class: 'row' }, pass(4.5, 'AA'), pass(7, 'AAA'), pass(3, T('AA groß')), pass(4.5, T('AAA groß')),
                    el('span', { class: 'tiny faint', text: T('WCAG 2: normaler Text braucht 4,5 (AA) bzw. 7 (AAA), großer Text 3 bzw. 4,5.') }))) : null,
                CF.card(T('Abstufungen'), 'palette', el('div', { class: 'color-row' }, [95, 85, 75, 65, 55, 45, 35, 25, 15, 8].map(L => sw(fromHsl(h, s, L))))),
                CF.card(T('Passende Farben'), 'sparkles', el('div', { class: 'color-row' }, [[180, T('Gegenfarbe')], [30, T('analog')], [-30, T('analog')], [120, T('Triade')], [240, T('Triade')], [150, T('geteilt')], [210, T('geteilt')]].map(([d, n]) => sw(fromHsl((h + d + 360) % 360, s, l), n)))));
        }
        pick.addEventListener('input', () => { fg.value = pick.value; run(); });
        pick2.addEventListener('input', () => { bg.value = pick2.value; run(); });
        [fg, bg].forEach(x => x.addEventListener('input', CF.debounce(run, 150)));
        fill(root, two([field(T('Farbe'), el('div', { class: 'row nw' }, pick, el('div', { class: 'grow' }, fg))), field(T('Hintergrund (für den Kontrast)'), el('div', { class: 'row nw' }, pick2, el('div', { class: 'grow' }, bg)))], [out]));
        run();
    },
};

/* ---------- Zahlensysteme ---------- */
CF.views.zahlen = {
    mount(root) {
        let base = CF.ls.get('dev.base', 10);
        const inp = input({ class: 'in big mono', value: CF.ls.get('dev.num', '255'), spellcheck: 'false', autocomplete: 'off' });
        const custom = input({ type: 'number', min: 2, max: 36, value: 36, style: { width: '5rem' } });
        const out = el('div', { class: 'stack' });
        const digits = '0123456789abcdefghijklmnopqrstuvwxyz';
        function parseBig(s, b) {
            s = s.trim().toLowerCase().replace(/[\s_']/g, '');
            let neg = false;
            if (s[0] === '-') { neg = true; s = s.slice(1); }
            if (b === 16) s = s.replace(/^0x/, ''); if (b === 2) s = s.replace(/^0b/, ''); if (b === 8) s = s.replace(/^0o/, '');
            if (!s) throw new Error(T('Bitte eine Zahl eingeben.'));
            let v = 0n;
            for (const ch of s) { const d = digits.indexOf(ch); if (d < 0 || d >= b) throw new Error(T('„%s“ ist keine Ziffer zur Basis %s.', ch, b)); v = v * BigInt(b) + BigInt(d); }
            return neg ? -v : v;
        }
        const group = (s, n) => s.replace(new RegExp('\\B(?=(.{' + n + '})+(?!.))', 'g'), ' ');
        function run() {
            CF.ls.set('dev.num', inp.value); CF.ls.set('dev.base', base);
            try {
                const v = parseBig(inp.value, base);
                const abs = v < 0n ? -v : v;
                const neg = v < 0n ? '-' : '';
                const rows = [[T('Dezimal'), neg + group(abs.toString(10), 3)], ['Hex', neg + group(abs.toString(16).toUpperCase(), 4)], [T('Oktal'), neg + abs.toString(8)], [T('Binär'), neg + group(abs.toString(2), 4)],
                    [T('Basis %s', custom.value), neg + abs.toString(Math.max(2, Math.min(36, +custom.value || 36)))]];
                const bits = abs.toString(2).length;
                const extra = [];
                if (v < 0n && bits < 64) { const tw = (1n << 64n) + v; extra.push([T('Zweierkomplement (64 Bit)'), group(tw.toString(16).toUpperCase(), 4)]); }
                if (abs < 1114112n && abs > 31n) extra.push([T('Zeichen'), String.fromCodePoint(Number(abs))]);
                fill(out, el('div', { class: 'kv' }, rows.concat(extra).map(([k, val]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v mono break', text: val }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(val.replace(/ /g, '')), 'sm'))]).flat()),
                    el('div', { class: 'small muted', text: T('%s Bit nötig', bits) + (bits <= 8 ? ' · 1 Byte' : bits <= 16 ? ' · 2 Bytes' : bits <= 32 ? ' · 4 Bytes' : bits <= 64 ? ' · 8 Bytes' : '') }));
            } catch (e) { fill(out, CF.msg('warn', e.message)); }
        }
        inp.addEventListener('input', CF.debounce(run, 100));
        custom.addEventListener('input', run);
        fill(root, two([field(T('Zahl'), inp), field(T('Eingabe ist zur Basis'), CF.seg([['10', T('Dezimal')], ['16', 'Hex'], ['2', T('Binär')], ['8', T('Oktal')]], String(base), v => { base = +v; run(); })),
            field(T('Eigene Basis'), custom)], [out]));
        run();
    },
};

/* ---------- Subnetz ---------- */
function ip4(s) { const p = s.split('.'); if (p.length !== 4 || p.some(x => !/^\d{1,3}$/.test(x) || +x > 255)) return null; return p.reduce((a, x) => a * 256 + +x, 0); }
const ip4s = n => [24, 16, 8, 0].map(s => Math.floor(n / Math.pow(2, s)) % 256).join('.');
function ip6(s) {
    s = s.toLowerCase();
    if (s.indexOf('.') >= 0) { const i = s.lastIndexOf(':'); const v4 = ip4(s.slice(i + 1)); if (v4 === null) return null; s = s.slice(0, i + 1) + (Math.floor(v4 / 65536)).toString(16) + ':' + (v4 % 65536).toString(16); }
    const halves = s.split('::');
    if (halves.length > 2) return null;
    const a = halves[0] ? halves[0].split(':') : [], b = halves.length === 2 && halves[1] ? halves[1].split(':') : [];
    const fill0 = halves.length === 2 ? 8 - a.length - b.length : 0;
    const parts = a.concat(Array(Math.max(0, fill0)).fill('0'), b);
    if (parts.length !== 8 || parts.some(x => !/^[0-9a-f]{1,4}$/.test(x))) return null;
    return parts.reduce((acc, x) => (acc << 16n) + BigInt(parseInt(x, 16)), 0n);
}
function ip6s(n) {
    const p = [];
    for (let i = 7; i >= 0; i--) p.push(((n >> BigInt(i * 16)) & 0xffffn).toString(16));
    const s = p.join(':');
    const best = (s.match(/(^|:)0(:0)+(:|$)/g) || []).sort((x, y) => y.length - x.length)[0];
    return best ? s.replace(best, '::').replace(/^:::/, '::').replace(/:::$/, '::') : s;
}
CF.views.subnetz = {
    mount(root) {
        const inp = input({ class: 'in big mono', value: CF.ls.get('dev.net', '192.168.10.37/24'), spellcheck: 'false', autocomplete: 'off' });
        const out = el('div', { class: 'stack' });
        function run() {
            CF.ls.set('dev.net', inp.value);
            const m = inp.value.trim().match(/^([0-9a-fA-F:.]+)(?:\/(\d{1,3}))?(?:\s+(\d+\.\d+\.\d+\.\d+))?$/);
            if (!m) { fill(out, CF.msg('warn', T('Bitte so eingeben: 192.168.1.10/24, 10.0.0.1 255.255.0.0 oder 2001:db8::1/64'))); return; }
            const v4 = ip4(m[1]);
            if (v4 !== null) {
                let pre = m[2] !== undefined ? +m[2] : 32;
                if (m[3]) { const mk = ip4(m[3]); const bin = mk.toString(2).padStart(32, '0'); if (!/^1*0*$/.test(bin)) { fill(out, CF.msg('warn', T('Ungültige Netzmaske.'))); return; } pre = bin.indexOf('0') < 0 ? 32 : bin.indexOf('0'); }
                if (pre > 32) { fill(out, CF.msg('warn', T('Präfix höchstens /32.'))); return; }
                const size = Math.pow(2, 32 - pre), net = Math.floor(v4 / size) * size, bc = net + size - 1;
                const mask = 4294967296 - size;
                const cls = v4 < 2147483648 ? 'A' : v4 < 3221225472 ? 'B' : v4 < 3758096384 ? 'C' : v4 < 4026531840 ? 'D (Multicast)' : 'E';
                const priv = /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|127\.|169\.254\.)/.test(ip4s(v4));
                fill(out, el('div', { class: 'kv' }, [[T('Netz'), ip4s(net) + '/' + pre], [T('Netzmaske'), ip4s(mask)], [T('Wildcard'), ip4s(4294967295 - mask)], [T('Broadcast'), ip4s(bc)],
                    [T('Erste nutzbare Adresse'), pre >= 31 ? ip4s(net) : ip4s(net + 1)], [T('Letzte nutzbare Adresse'), pre >= 31 ? ip4s(bc) : ip4s(bc - 1)],
                    [T('Nutzbare Adressen'), CF.fmtNum(pre >= 31 ? size : size - 2)], [T('Adressen insgesamt'), CF.fmtNum(size)], [T('Klasse'), cls], [T('Art'), priv ? T('privat / lokal') : T('öffentlich')],
                    [T('Binär'), v4.toString(2).padStart(32, '0').replace(/(.{8})(?!$)/g, '$1.')]].map(([k, v]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v mono', text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))]).flat()),
                    pre < 30 && pre >= 8 ? CF.card(T('Aufteilen'), 'grid', el('div', { class: 'small mono stack' }, [1, 2, 3].map(k => pre + k <= 30 ? el('div', {}, T('%s Netze /%s mit je %s Adressen', Math.pow(2, k), pre + k, CF.fmtNum(Math.pow(2, 32 - pre - k)))) : null))) : null);
                return;
            }
            const v6 = ip6(m[1]);
            if (v6 === null) { fill(out, CF.msg('warn', T('Keine gültige IP-Adresse.'))); return; }
            const pre = m[2] !== undefined ? Math.min(128, +m[2]) : 128;
            const hostBits = BigInt(128 - pre);
            const net = (v6 >> hostBits) << hostBits;
            const last = net + (1n << hostBits) - 1n;
            fill(out, el('div', { class: 'kv' }, [[T('Netz'), ip6s(net) + '/' + pre], [T('Erste Adresse'), ip6s(net)], [T('Letzte Adresse'), ip6s(last)],
                [T('Adressen'), pre >= 64 ? CF.fmtNum(Number(1n << hostBits)) : '2^' + (128 - pre)], [T('Ausgeschrieben'), Array.from({ length: 8 }, (x, i) => ((v6 >> BigInt((7 - i) * 16)) & 0xffffn).toString(16).padStart(4, '0')).join(':')],
                [T('Gekürzt'), ip6s(v6)], [T('/64-Netze darin'), pre <= 64 ? (pre <= 11 ? '2^' + (64 - pre) : CF.fmtNum(Math.pow(2, 64 - pre))) : '–']].map(([k, v]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v mono break', text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))]).flat()));
        }
        inp.addEventListener('input', CF.debounce(run, 150));
        fill(root, el('div', { class: 'stack lg' }, inp, el('div', { class: 'chips' }, ['192.168.1.0/24', '10.0.0.0/8', '172.16.5.4 255.255.240.0', '2001:db8::/48', 'fe80::1/64'].map(x => el('button', { type: 'button', class: 'chip mono', text: x, onclick: () => { inp.value = x; run(); } }))), out));
        run();
    },
};

/* ---------- chmod ---------- */
CF.views.chmod = {
    mount(root) {
        let mode = parseInt(CF.ls.get('dev.chmod', '755'), 8);
        const num = input({ class: 'in big mono', value: mode.toString(8).padStart(3, '0'), maxlength: 4, style: { width: '7rem' } });
        const grid = el('div');
        const out = el('div', { class: 'stack' });
        function render() {
            CF.ls.set('dev.chmod', mode.toString(8));
            const who = [[T('Besitzer'), 6], [T('Gruppe'), 3], [T('Andere'), 0]], what = [[T('Lesen'), 4], [T('Schreiben'), 2], [T('Ausführen'), 1]];
            fill(grid, el('table', { class: 'tbl' }, el('tr', {}, el('th'), what.map(w => el('th', { text: w[0] }))),
                who.map(([n, sh]) => el('tr', {}, el('td', { text: n }), what.map(([, bit]) => el('td', {}, el('input', { type: 'checkbox', checked: !!(mode & (bit << sh)), onchange: e => { mode = e.target.checked ? mode | (bit << sh) : mode & ~(bit << sh); num.value = mode.toString(8).padStart(3, '0'); render(); } })))))),
                el('div', { class: 'row' }, [['SUID', 0o4000], ['SGID', 0o2000], ['Sticky', 0o1000]].map(([n, bit]) => CF.toggle(n, !!(mode & bit), v => { mode = v ? mode | bit : mode & ~bit; num.value = mode.toString(8).padStart(mode > 0o777 ? 4 : 3, '0'); render(); }))));
            const sym = ['r', 'w', 'x', 'r', 'w', 'x', 'r', 'w', 'x'].map((c, i) => (mode & (1 << (8 - i))) ? c : '-');
            if (mode & 0o4000) sym[2] = sym[2] === 'x' ? 's' : 'S';
            if (mode & 0o2000) sym[5] = sym[5] === 'x' ? 's' : 'S';
            if (mode & 0o1000) sym[8] = sym[8] === 'x' ? 't' : 'T';
            const oct = mode.toString(8).padStart(mode > 0o777 ? 4 : 3, '0');
            fill(out, el('div', { class: 'kv' }, [[T('Zahl'), oct], [T('Symbolisch'), '-' + sym.join('')], [T('Befehl'), 'chmod ' + oct + ' datei'], [T('Rekursiv nur Ordner'), 'find . -type d -exec chmod ' + oct + ' {} +']].map(([k, v]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v mono', text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))]).flat()),
                (mode & 0o002) ? CF.msg('warn', T('Für alle schreibbar: auf Webservern fast nie eine gute Idee.')) : null);
        }
        num.addEventListener('input', () => { if (/^[0-7]{3,4}$/.test(num.value)) { mode = parseInt(num.value, 8); render(); } });
        fill(root, two([el('div', { class: 'row' }, num, el('div', { class: 'chips' }, [['644', T('Dateien')], ['755', T('Ordner, Skripte')], ['600', T('privat')], ['700', T('privater Ordner')], ['775', T('Gruppe schreibt')]].map(([v, n]) => el('button', { type: 'button', class: 'chip', text: v + ' · ' + n, onclick: () => { mode = parseInt(v, 8); num.value = v; render(); } })))), grid], [out]));
        render();
    },
};

/* ---------- .htpasswd ---------- */
CF.views.htpasswd = {
    mount(root) {
        const user = input({ placeholder: T('Benutzername'), autocomplete: 'off', spellcheck: 'false' });
        const pass = input({ type: 'text', placeholder: T('Passwort'), autocomplete: 'off', spellcheck: 'false' });
        let algo = 'bcrypt';
        const out = el('div', { class: 'stack' });
        const go = btn(T('Zeile erzeugen'), 'btn-primary', async () => {
            go.disabled = true;
            try {
                const r = await CF.api('htpasswd', { user: user.value, pass: pass.value, algo });
                const conf = 'AuthType Basic\nAuthName "' + T('Geschützter Bereich') + '"\nAuthUserFile /absoluter/pfad/zu/.htpasswd\nRequire valid-user';
                fill(out, field('.htpasswd', el('div', { class: 'out-box', text: r.line })), el('div', { class: 'row' }, copyBtn(r.line)),
                    field('.htaccess', el('div', { class: 'out-box', text: conf })), el('div', { class: 'row' }, copyBtn(conf)),
                    CF.msg('info', T('Den Pfad bei AuthUserFile durch den echten, absoluten Pfad auf deinem Webspace ersetzen. Die .htpasswd am besten außerhalb des öffentlichen Ordners ablegen.')));
            } catch (e) { CF.toast(e.message, 'bad', 4000); }
            go.disabled = false;
        }, 'lock');
        fill(root, two([field(T('Benutzer'), user), field(T('Passwort'), pass), el('div', { class: 'row' }, btn(T('Passwort erzeugen'), 'btn-ghost btn-sm', () => { pass.value = Array.from(crypto.getRandomValues(new Uint8Array(18)), x => 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'[x % 56]).join(''); }, 'refresh')),
            field(T('Verfahren'), CF.seg([['bcrypt', T('bcrypt (empfohlen)')], ['sha', T('SHA-1 (alt)')]], algo, v => { algo = v; })), el('div', { class: 'row' }, go),
            el('p', { class: 'tiny faint', text: T('bcrypt rechnet dein eigener Server (password_hash), das Passwort wird nicht gespeichert.') })], [out]));
    },
};

/* ---------- Text-Werkzeuge ---------- */
const LOREM = 'lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt in culpa qui officia deserunt mollit anim id est laborum'.split(' ');
function lorem(par) {
    const r = n => Math.floor(Math.random() * n);
    return Array.from({ length: par }, (x, p) => Array.from({ length: 4 + r(4) }, (y, si) => { const w = Array.from({ length: 6 + r(10) }, () => LOREM[r(LOREM.length)]); if (!p && !si) w.splice(0, 5, 'lorem', 'ipsum', 'dolor', 'sit', 'amet'); const s = w.join(' '); return s[0].toUpperCase() + s.slice(1) + '.'; }).join(' ')).join('\n\n');
}
function slug(s) { return s.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
const words = s => s.replace(/([a-z])([A-Z])/g, '$1 $2').split(/[^A-Za-z0-9äöüÄÖÜß]+/).filter(Boolean);
CF.views.textwerk = {
    mount(root) {
        const t = area(T('Text hier einfügen'), 12, CF.ls.get('dev.text', ''));
        const stats = el('div');
        function count() {
            CF.ls.set('dev.text', t.value.slice(0, 100000));
            const v = t.value;
            const w = (v.match(/[^\s]+/g) || []).length;
            fill(stats, el('div', { class: 'grid3 small' }, [[T('Zeichen'), v.length], [T('ohne Leerzeichen'), v.replace(/\s/g, '').length], [T('Wörter'), w], [T('Sätze'), (v.match(/[^.!?]+[.!?]+/g) || []).length],
                [T('Zeilenzahl'), v ? v.split('\n').length : 0], [T('Absätze'), v.trim() ? v.trim().split(/\n\s*\n/).length : 0], [T('Lesezeit'), T('%s Min.', Math.max(1, Math.round(w / 220)))], ['Bytes (UTF-8)', utf8(v).length]]
                .map(([k, n]) => el('div', { class: 'card flat tight' }, el('div', { class: 'tiny faint', text: k }), el('div', { style: { fontWeight: 750, fontSize: '1.1rem' }, text: typeof n === 'number' ? CF.fmtNum(n) : n })))));
        }
        const lines = fn => { t.value = fn(t.value.split('\n')).join('\n'); count(); };
        const set = fn => { t.value = fn(t.value); count(); };
        const ops = [
            [T('GROSS'), () => set(s => s.toUpperCase())], [T('klein'), () => set(s => s.toLowerCase())],
            [T('Satzanfang groß'), () => set(s => s.toLowerCase().replace(/(^\s*|[.!?]\s+)([a-zäöü])/g, (m, a, b) => a + b.toUpperCase()))],
            [T('Jedes Wort groß'), () => set(s => s.replace(/(^|\s)(\S)/g, (m, a, b) => a + b.toUpperCase()))],
            ['camelCase', () => set(s => s.split('\n').map(l => words(l).map((w, i) => i ? w[0].toUpperCase() + w.slice(1).toLowerCase() : w.toLowerCase()).join('')).join('\n'))],
            ['snake_case', () => set(s => s.split('\n').map(l => words(l).map(w => w.toLowerCase()).join('_')).join('\n'))],
            ['kebab-case', () => set(s => s.split('\n').map(l => words(l).map(w => w.toLowerCase()).join('-')).join('\n'))],
            ['Slug', () => set(s => s.split('\n').map(slug).join('\n'))],
            [T('A → Z'), () => lines(l => l.sort((a, b) => a.localeCompare(b, CF.locale())))], [T('Z → A'), () => lines(l => l.sort((a, b) => b.localeCompare(a, CF.locale())))],
            [T('nach Länge'), () => lines(l => l.sort((a, b) => a.length - b.length))], [T('mischen'), () => lines(l => { for (let i = l.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [l[i], l[j]] = [l[j], l[i]]; } return l; })],
            [T('umdrehen'), () => lines(l => l.reverse())], [T('Duplikate weg'), () => lines(l => [...new Set(l)])], [T('leere Zeilen weg'), () => lines(l => l.filter(x => x.trim()))],
            [T('Leerzeichen säubern'), () => set(s => s.split('\n').map(l => l.replace(/[ \t]+/g, ' ').trim()).join('\n'))], [T('nummerieren'), () => lines(l => l.map((x, i) => (i + 1) + '. ' + x))],
            [T('Zeilen verbinden'), () => set(s => s.split('\n').map(x => x.trim()).filter(Boolean).join(' '))],
        ];
        t.addEventListener('input', CF.debounce(count, 150));
        fill(root, two([field(T('Text'), t), el('div', { class: 'row' }, copyBtn(() => t.value), btn(T('Leeren'), 'btn-ghost btn-sm', () => { t.value = ''; count(); }, 'trash'))],
            [stats, CF.card(T('Umwandeln'), 'wand', el('div', { class: 'chips' }, ops.map(([n, fn]) => el('button', { type: 'button', class: 'chip', text: n, onclick: fn })))),
                CF.card('Lorem ipsum', 'text', el('div', { class: 'row' }, [1, 3, 5].map(n => btn(T('%s Absätze', n), 'btn-second btn-sm', () => { t.value = lorem(n); count(); }))))]));
        count();
    },
};

/* ---------- Zeitstempel ---------- */
CF.views.zeitstempel = {
    mount(root) {
        const now = el('div', { class: 'big-num mono' });
        const nowMs = el('div', { class: 'small muted mono' });
        const ts = input({ class: 'in big mono', placeholder: '1798761600', value: String(Math.floor(Date.now() / 1000)) });
        const dtIn = input({ type: 'datetime-local', step: 1 });
        const out = el('div');
        const out2 = el('div');
        const tick = () => { now.textContent = Math.floor(Date.now() / 1000); nowMs.textContent = Date.now() + ' ms · ' + new Date().toISOString(); };
        const rel = d => { const s = Math.round((d - Date.now()) / 1000); const a = Math.abs(s); const u = a < 90 ? [s, 'second'] : a < 5400 ? [Math.round(s / 60), 'minute'] : a < 129600 ? [Math.round(s / 3600), 'hour'] : a < 3888000 ? [Math.round(s / 86400), 'day'] : a < 47304000 ? [Math.round(s / 2592000), 'month'] : [Math.round(s / 31536000), 'year']; try { return new Intl.RelativeTimeFormat(CF.locale(), { numeric: 'auto' }).format(u[0], u[1]); } catch (e) { return ''; } };
        const show = d => el('div', { class: 'kv' }, [[T('Ortszeit'), d.toLocaleString(CF.locale(), { dateStyle: 'full', timeStyle: 'long' })], ['UTC', d.toUTCString()], ['ISO 8601', d.toISOString()],
            [T('Sekunden'), String(Math.floor(d / 1000))], [T('Millisekunden'), String(d.getTime())], [T('relativ'), rel(d)]].map(([k, v]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v mono', text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))]).flat());
        function fromTs() {
            const v = ts.value.trim();
            if (!/^-?\d+(\.\d+)?$/.test(v)) { fill(out, v ? CF.msg('warn', T('Bitte nur Ziffern eingeben.')) : null); return; }
            let n = +v;
            const unit = Math.abs(n) > 1e14 ? T('Mikrosekunden') : Math.abs(n) > 1e11 ? T('Millisekunden') : T('Sekunden');
            if (Math.abs(n) > 1e14) n /= 1000; else if (Math.abs(n) <= 1e11) n *= 1000;
            fill(out, el('div', { class: 'stack' }, el('div', { class: 'tiny faint', text: T('Erkannt als %s', unit) }), show(new Date(n))));
        }
        function fromDate() { if (!dtIn.value) { fill(out2); return; } fill(out2, show(new Date(dtIn.value))); }
        ts.addEventListener('input', fromTs);
        dtIn.addEventListener('input', fromDate);
        tick();
        const iv = setInterval(tick, 1000);
        fill(root, el('div', { class: 'stack lg' },
            CF.card(T('Jetzt'), 'refresh', el('div', { class: 'row between' }, el('div', {}, now, nowMs), copyBtn(() => now.textContent))),
            two([CF.card(T('Zeitstempel → Datum'), 'calendar', el('div', { class: 'stack' }, ts, out))], [CF.card(T('Datum → Zeitstempel'), 'calendar', el('div', { class: 'stack' }, dtIn, out2))])));
        fromTs();
        return () => clearInterval(iv);
    },
};
})();
