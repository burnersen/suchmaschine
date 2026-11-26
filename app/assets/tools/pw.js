/* Suchmaschine – Passwort-Generator und Stärke-Check. Läuft nur im Browser (Web Crypto), der Server sieht kein Passwort.
   Modi: zufällige Zeichen, Passphrase (Wortlisten aus ShrinkURL), PIN, aussprechbar, eigenes Muster, Tokens für Entwickler. */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

/* ---------- Zufall: gleichverteilt, ohne Modulo-Verzerrung ---------- */
function rnd(n) {
    if (n <= 1) return 0;
    const lim = Math.floor(0x100000000 / n) * n;
    const b = new Uint32Array(1);
    do { crypto.getRandomValues(b); } while (b[0] >= lim);
    return b[0] % n;
}
const pick = s => s[rnd(s.length)];
function shuffle(a) { for (let i = a.length - 1; i > 0; i--) { const j = rnd(i + 1); [a[i], a[j]] = [a[j], a[i]]; } return a; }
const log2 = x => Math.log(x) / Math.LN2;

const SETS = { lower: 'abcdefghijklmnopqrstuvwxyz', upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', digits: '0123456789', symbols: '!#$%&*+-=?@^_~.,:;()[]{}/' };
const AMBIG = /[Il1O0oB8S5Z2|`'"]/g;
const SAFE_SYMBOLS = '-_';

const DEF = {
    mode: 'chars', count: 1,
    chars: { len: 20, lower: true, upper: true, digits: true, symbols: true, sym: SETS.symbols, noAmbig: false, minEach: 1, letterFirst: false, noRepeat: false, exclude: '' },
    phrase: { words: 5, lang: CF.lang === 'en' ? 'en' : 'de', sep: '-', caps: 'first', num: true, sym: false },
    pin: { len: 6, noTrivial: true },
    say: { syl: 4, caps: true, digits: 2 },
    pattern: { p: 'Aaaa-aaaa-9999' },
    token: { fmt: 'hex', bytes: 32, prefix: '' },
};
const PRESETS = [
    ['wifi', 'WLAN', 'Gut abzutippen: ohne Verwechsler und Sonderzeichen', { mode: 'chars', chars: { len: 20, lower: true, upper: true, digits: true, symbols: false, noAmbig: true, minEach: 1, letterFirst: true } }],
    ['web', 'Online-Konto', '20 Zeichen, alles gemischt', { mode: 'chars', chars: { len: 20, lower: true, upper: true, digits: true, symbols: true, sym: SETS.symbols, noAmbig: false, minEach: 1 } }],
    ['max', 'Maximal sicher', '40 Zeichen, alles gemischt', { mode: 'chars', chars: { len: 40, lower: true, upper: true, digits: true, symbols: true, sym: SETS.symbols, minEach: 2 } }],
    ['compat', 'Kompatibel', 'Nur - und _ als Sonderzeichen, beginnt mit Buchstabe', { mode: 'chars', chars: { len: 16, lower: true, upper: true, digits: true, symbols: true, sym: SAFE_SYMBOLS, minEach: 1, letterFirst: true, noAmbig: true } }],
    ['phrase', 'Merkbar', '5 Wörter mit Zahl', { mode: 'phrase', phrase: { words: 5, sep: '-', caps: 'first', num: true, sym: false } }],
    ['pin', 'PIN', '6 Ziffern ohne 123456 und Co.', { mode: 'pin', pin: { len: 6, noTrivial: true } }],
];

/* ---------- Erzeugen ---------- */

function genChars(o) {
    let pool = [];
    const sets = [];
    ['lower', 'upper', 'digits', 'symbols'].forEach(k => {
        if (!o[k]) return;
        let s = k === 'symbols' ? (o.sym || SETS.symbols) : SETS[k];
        if (o.noAmbig) s = s.replace(AMBIG, '');
        if (o.exclude) s = s.split('').filter(c => o.exclude.indexOf(c) < 0).join('');
        s = Array.from(new Set(s.split(''))).join('');
        if (s) { sets.push([k, s]); pool = pool.concat(s.split('')); }
    });
    pool = Array.from(new Set(pool));
    if (!pool.length) return { pw: '', bits: 0 };
    const len = Math.max(4, Math.min(128, o.len | 0));
    for (let tries = 0; tries < 200; tries++) {
        const chars = [];
        const minEach = Math.max(0, Math.min(5, o.minEach | 0));
        if (minEach * sets.length <= len) sets.forEach(([, s]) => { for (let i = 0; i < minEach; i++) chars.push(pick(s)); });
        while (chars.length < len) chars.push(pick(pool));
        shuffle(chars);
        if (o.letterFirst) {
            const i = chars.findIndex(c => /[a-zA-Z]/.test(c));
            if (i > 0) [chars[0], chars[i]] = [chars[i], chars[0]];
            else if (i < 0) continue;
        }
        if (o.noRepeat && chars.some((c, i) => i && c === chars[i - 1])) continue;
        return { pw: chars.join(''), bits: len * log2(pool.length) };
    }
    return { pw: '', bits: 0 };
}

function capWord(w, mode) {
    if (mode === 'first') return w[0].toUpperCase() + w.slice(1);
    if (mode === 'all') return w.toUpperCase();
    if (mode === 'random') return rnd(2) ? w[0].toUpperCase() + w.slice(1) : w;
    return w;
}
function genPhrase(o) {
    const list = o.lang === 'mix' ? CF.WORDS.de.concat(CF.WORDS.en) : (CF.WORDS[o.lang] || CF.WORDS.de);
    const n = Math.max(3, Math.min(12, o.words | 0));
    const words = [];
    for (let i = 0; i < n; i++) words.push(capWord(pick(list), o.caps));
    let bits = n * log2(list.length) + (o.caps === 'random' ? n : 0);
    if (o.num) { const pos = rnd(words.length); words[pos] += String(rnd(100)); bits += log2(100) + log2(n); }
    if (o.sym) { const s = '!#$%&*+?@'; const pos = rnd(words.length); words[pos] += pick(s); bits += log2(s.length) + log2(n); }
    return { pw: words.join(o.sep), bits };
}

function pinTrivial(p) {
    if (/^(\d)\1+$/.test(p)) return true;
    const asc = '01234567890123456789', desc = '98765432109876543210';
    if (asc.indexOf(p) >= 0 || desc.indexOf(p) >= 0) return true;
    if (/^(19[3-9]\d|20[0-3]\d)$/.test(p)) return true;               // Jahreszahl
    if (p.length === 4 && /^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])$/.test(p)) return true;   // TTMM
    if (p.length === 6 && /^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])\d\d$/.test(p)) return true; // TTMMJJ
    if (/^(\d\d)\1+$/.test(p) || /^(\d{3})\1$/.test(p)) return true;
    return ['1212', '1004', '2000', '4444', '2580', '5683', '0852', '1379', '7777', '159753', '147258', '123123', '112233', '696969', '121212'].indexOf(p) >= 0;
}
function genPin(o) {
    const len = Math.max(4, Math.min(12, o.len | 0));
    let p = '';
    for (let t = 0; t < 500; t++) {
        p = '';
        for (let i = 0; i < len; i++) p += rnd(10);
        if (!o.noTrivial || !pinTrivial(p)) break;
    }
    return { pw: p, bits: len * log2(10) - (o.noTrivial ? 0.05 : 0) };
}

const CONS = 'bdfgkmnprstvwz', VOW = 'aeiou', END = 'nrstlmk';
function genSay(o) {
    const n = Math.max(2, Math.min(10, o.syl | 0));
    const parts = [];
    let bits = 0;
    for (let i = 0; i < n; i++) {
        let s = pick(CONS) + pick(VOW);
        bits += log2(CONS.length) + log2(VOW.length);
        if (rnd(3) === 0) { s += pick(END); bits += log2(END.length) - log2(1.5); }
        parts.push(o.caps && i % 2 === 0 ? s[0].toUpperCase() + s.slice(1) : s);
    }
    let pw = '';
    parts.forEach((s, i) => { pw += s + (i % 2 === 1 && i < parts.length - 1 ? '-' : ''); });
    const d = Math.max(0, Math.min(6, o.digits | 0));
    if (d) { let x = ''; for (let i = 0; i < d; i++) x += rnd(10); pw += '-' + x; bits += d * log2(10); }
    return { pw, bits };
}

/** Muster: A = Großbuchstabe, a = Kleinbuchstabe, 9 = Ziffer, # = Sonderzeichen, * = irgendein Zeichen, \ = nächstes Zeichen wörtlich */
function genPattern(o) {
    const p = String(o.p || '');
    let out = '', bits = 0;
    const all = SETS.lower + SETS.upper + SETS.digits + SETS.symbols;
    for (let i = 0; i < p.length && i < 200; i++) {
        const c = p[i];
        const set = { A: SETS.upper, a: SETS.lower, 9: SETS.digits, '#': SETS.symbols, '*': all, '?': SETS.lower + SETS.upper }[c];
        if (c === '\\' && i + 1 < p.length) { out += p[++i]; continue; }
        if (set) { out += pick(set); bits += log2(set.length); } else out += c;
    }
    return { pw: out, bits };
}

function b64url(bytes) { let s = ''; bytes.forEach(b => { s += String.fromCharCode(b); }); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
function base32(bytes) {
    const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = 0, val = 0, out = '';
    bytes.forEach(b => { val = (val << 8) | b; bits += 8; while (bits >= 5) { out += a[(val >>> (bits - 5)) & 31]; bits -= 5; } });
    if (bits > 0) out += a[(val << (5 - bits)) & 31];
    return out;
}
function uuid4() {
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    const h = Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
}
function genToken(o) {
    if (o.fmt === 'uuid') return { pw: (o.prefix || '') + uuid4(), bits: 122 };
    const n = Math.max(8, Math.min(128, o.bytes | 0));
    const b = crypto.getRandomValues(new Uint8Array(n));
    let s = o.fmt === 'b64' ? b64url(b) : o.fmt === 'b32' ? base32(b) : Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
    return { pw: (o.prefix || '') + s, bits: n * 8 };
}

function generate(cfg) {
    switch (cfg.mode) {
        case 'phrase': return genPhrase(cfg.phrase);
        case 'pin': return genPin(cfg.pin);
        case 'say': return genSay(cfg.say);
        case 'pattern': return genPattern(cfg.pattern);
        case 'token': return genToken(cfg.token);
        default: return genChars(cfg.chars);
    }
}

/* ---------- Stärke: Entropie, Knackzeit, Muster ---------- */

const COMMON = ('123456 password 123456789 12345678 12345 qwerty 1234567 111111 1234567890 123123 abc123 1234 password1 iloveyou 1q2w3e4r 000000 qwerty123 zaq12wsx dragon sunshine ' +
    'princess letmein 654321 monkey 27653 1qaz2wsx 123321 qwertyuiop superman asdfghjkl 123qwe 666666 121212 football baseball welcome admin login master hello freedom whatever ' +
    'qazwsx trustno1 starwars passw0rd shadow michael jordan23 jennifer hunter killer charlie batman 7777777 987654321 159753 hallo passwort hallo123 schatz schatzi hase ficken ' +
    'fussball fußball schalke04 bvb09 bvb1909 borussia bayern sommer winter berlin hamburg muenchen geheim qwertz qwertz123 asdfgh yxcvbnm 1234qwer qwer1234 lol123 ' +
    'test test123 guest default changeme secret password123 passwort1 passwort123 internet computer killer1 snoopy pokemon mustang soccer harley ranger buster thomas tigger ' +
    'robert daniel andrea stefan thomas123 michael1 sabine 12341234 11111111 00000000 88888888 abcdef abcdefg abcd1234 a1b2c3 aa123456 asdf1234 q1w2e3r4 1q2w3e iloveyou1 ' +
    'liebe ichliebedich mausi baerchen engel sonne blume lucky snoopy1 matrix cookie flower purple orange chocolate maggie ginger pepper summer2024 sommer2024 winter2024 ' +
    'summer2025 sommer2025 winter2025 sommer2026 winter2026 welcome1 willkommen willkommen1 start123 start passwort! password! qwerty1 qwerty12 1111 0000 2580 apple samsung').split(' ');
const KEYROWS = ['qwertzuiopü', 'asdfghjklöä', 'yxcvbnm', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm', '1234567890', '!"§$%&/()=', '1qay2wsx3edc4rfv5tgb6zhn', '1qaz2wsx3edc4rfv5tgb6yhn'];
const LEET = { '4': 'a', '@': 'a', '3': 'e', '1': 'i', '!': 'i', '0': 'o', '$': 's', '5': 's', '7': 't', '+': 't', '8': 'b', '9': 'g' };

let wordSet = null;
function words() {
    if (!wordSet) {
        wordSet = new Set();
        ['de', 'en'].forEach(l => (CF.WORDS && CF.WORDS[l] || []).forEach(w => { if (w.length >= 4) wordSet.add(w); }));
        COMMON.forEach(w => { if (/^[a-zäöüß]{4,}$/.test(w)) wordSet.add(w); });
    }
    return wordSet;
}

/** Schätzt die Stärke eines vorhandenen Passworts. Liefert { bits, findings: [[Stufe, Text]] } */
function analyze(pw) {
    const out = [];
    if (!pw) return { bits: 0, findings: out };
    const chars = Array.from(pw);
    const len = chars.length;
    let pool = 0;
    if (/[a-z]/.test(pw)) pool += 26;
    if (/[A-Z]/.test(pw)) pool += 26;
    if (/\d/.test(pw)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(pw)) pool += 33;
    if (/[^\x00-\x7f]/.test(pw)) pool += 30;
    const per = log2(Math.max(pool, 2));
    // Pro Zeichen Kosten in Bit; Muster ersetzen die Kosten der Zeichen, die sie abdecken
    const cost = new Array(len).fill(per);
    const cover = (from, to, bits, why) => {
        const old = cost.slice(from, to).reduce((a, b) => a + b, 0);
        if (bits >= old) return;
        for (let i = from; i < to; i++) cost[i] = bits / (to - from);
        out.push(why);
    };
    const low = pw.toLowerCase();
    const unleet = low.replace(/[4@3!10$57+89]/g, c => LEET[c] || c);
    if (COMMON.indexOf(low) >= 0 || COMMON.indexOf(unleet) >= 0) {
        return { bits: Math.min(10, len), findings: [['danger', T('Steht auf den Listen der häufigsten Passwörter. Wird in Sekunden erraten.')]] };
    }
    // Wörter aus dem Wörterbuch (auch mit 4 statt a, 3 statt e …)
    const ws = words();
    for (let i = 0; i < len; i++) {
        for (let j = Math.min(len, i + 14); j >= i + 4; j--) {
            const w = unleet.slice(i, j);
            if (ws.has(w)) { cover(i, j, log2(ws.size) + (pw.slice(i, j) !== low.slice(i, j) ? 1 : 0) + (w !== low.slice(i, j) ? 2 : 0), ['warn', T('Enthält ein Wort aus dem Wörterbuch: „%s“', pw.slice(i, j))]); i = j - 1; break; }
        }
    }
    // Tastatur-Reihen und Folgen (abc, 123, qwertz)
    for (let i = 0; i < len - 2; i++) {
        let j = i + 1;
        const step = low.charCodeAt(i + 1) - low.charCodeAt(i);
        if (Math.abs(step) === 1) { while (j < len && low.charCodeAt(j) - low.charCodeAt(j - 1) === step) j++; }
        if (j - i >= 3) { cover(i, j, log2(26 * 2 * (j - i)), ['warn', T('Enthält eine Folge: „%s“', pw.slice(i, j))]); i = j - 1; continue; }
        for (const row of KEYROWS) {
            let k = i;
            while (k < len && row.indexOf(low.slice(i, k + 1)) >= 0) k++;
            if (k - i >= 4) { cover(i, k, log2(row.length * 2 * (k - i)), ['warn', T('Enthält eine Tastatur-Reihe: „%s“', pw.slice(i, k))]); i = k - 1; break; }
        }
    }
    // Wiederholungen (aaaa, abcabc)
    const rep = /(.+?)\1{2,}|(.{2,})\2+/g;
    let m;
    while ((m = rep.exec(pw))) cover(m.index, m.index + m[0].length, per * (m[1] || m[2]).length + log2(m[0].length), ['warn', T('Enthält Wiederholungen: „%s“', m[0])]);
    // Jahreszahlen und Daten
    const yr = /(19[0-9]\d|20[0-4]\d)/g;
    while ((m = yr.exec(pw))) cover(m.index, m.index + 4, log2(150), ['info', T('Enthält eine Jahreszahl: %s', m[0])]);
    const dt = /(0?[1-9]|[12]\d|3[01])[.\-\/]?(0?[1-9]|1[0-2])[.\-\/]?(\d{2}|\d{4})/g;
    while ((m = dt.exec(pw))) if (m[0].length >= 6) cover(m.index, m.index + m[0].length, log2(36500), ['info', T('Enthält ein Datum: %s', m[0])]);
    if (/^[A-Z][a-z]+\d{1,4}[!?.]?$/.test(pw)) out.push(['info', T('Typisches Schema: Großbuchstabe, Wort, Zahl, Satzzeichen. Angreifer probieren das zuerst.')]);
    if (len < 10) out.push(['warn', T('Kürzer als 10 Zeichen.')]);
    if (pool <= 10) out.push(['warn', T('Nur Ziffern.')]);
    const bits = cost.reduce((a, b) => a + b, 0);
    return { bits, findings: out };
}

/** Knackzeit für 2^bits Möglichkeiten (im Schnitt die Hälfte) bei gegebenen Versuchen pro Sekunde */
function crackTime(bits, perSec) {
    const sec = Math.pow(2, Math.max(0, bits - 1)) / perSec;
    const units = [[31557600 * 1e9, () => T('etwa %s Milliarden Jahre', CF.fmtNum(Math.round(sec / 31557600e9)))], [31557600 * 1e6, () => T('etwa %s Millionen Jahre', CF.fmtNum(Math.round(sec / 31557600e6)))],
        [31557600 * 1000, () => T('etwa %s tausend Jahre', CF.fmtNum(Math.round(sec / 31557600e3)))], [31557600, () => T('etwa %s Jahre', CF.fmtNum(Math.round(sec / 31557600)))],
        [2629800, () => T('etwa %s Monate', CF.fmtNum(Math.round(sec / 2629800)))], [86400, () => T('etwa %s Tage', CF.fmtNum(Math.round(sec / 86400)))],
        [3600, () => T('etwa %s Stunden', CF.fmtNum(Math.round(sec / 3600)))], [60, () => T('etwa %s Minuten', CF.fmtNum(Math.round(sec / 60)))], [1, () => T('etwa %s Sekunden', CF.fmtNum(Math.round(sec)))]];
    if (sec < 1) return T('sofort');
    if (sec > 31557600 * 1e12) return T('länger als das Universum alt ist');
    for (const [u, fn] of units) if (sec >= u) return fn();
    return T('sofort');
}
function verdictOf(bits) {
    if (bits < 40) return ['danger', T('schwach'), 15];
    if (bits < 60) return ['warn', T('mittel'), 40];
    if (bits < 80) return ['ok', T('gut'), 65];
    if (bits < 110) return ['ok', T('stark'), 85];
    return ['ok', T('sehr stark'), 100];
}
function strengthBox(bits) {
    const [kind, label, pct] = verdictOf(bits);
    return el('div', { class: 'stack pw-str' },
        el('div', { class: 'verdict' }, el('div', { class: 'score', text: Math.round(bits) + ' Bit' }),
            el('div', { class: 'grow' }, el('b', { text: label }), el('div', { class: 'meter pw-meter ' + kind, style: { marginTop: '.35rem' } }, el('span', { style: { width: pct + '%' } })))),
        el('div', { class: 'kv pw-kv' },
            el('div', { class: 'k', text: T('Offline, Grafikkarte') }), el('div', { class: 'v', text: crackTime(bits, 1e11) }), el('div', { class: 'a' }),
            el('div', { class: 'k', text: T('Offline, gut gesichert') }), el('div', { class: 'v', text: crackTime(bits, 1e5) }), el('div', { class: 'a' }),
            el('div', { class: 'k', text: T('Online, mit Bremse') }), el('div', { class: 'v', text: crackTime(bits, 10) }), el('div', { class: 'a' })),
        el('div', { class: 'tiny faint', text: T('Grafikkarte: 100 Milliarden Versuche pro Sekunde gegen schnelle Prüfsummen (MD5, NTLM). Gut gesichert: 100.000 pro Sekunde (bcrypt, Argon2). Online: 10 pro Sekunde.') }));
}

/** Zeichen einfärben: Ziffern und Sonderzeichen heben sich ab */
function colored(pw) {
    const box = el('div', { class: 'pw-chars mono' });
    Array.from(pw).forEach(c => box.append(el('span', { class: /\d/.test(c) ? 'd' : /[a-zA-ZäöüÄÖÜß]/.test(c) ? '' : 's', text: c })));
    return box;
}

/* ---------- Oberfläche ---------- */

CF.views.passwort = {
    mount(root, params) {
        const saved = CF.ls.get('pw', {});
        const cfg = JSON.parse(JSON.stringify(DEF));
        Object.keys(cfg).forEach(k => { if (saved[k] !== undefined) cfg[k] = typeof cfg[k] === 'object' ? Object.assign(cfg[k], saved[k]) : saved[k]; });
        if (params.len) { cfg.mode = 'chars'; cfg.chars.len = +params.len || 20; }
        if (params.phrase) cfg.mode = 'phrase';
        let tab = params.check ? 'check' : 'gen';
        let list = [];
        const save = () => CF.ls.set('pw', cfg);

        const out = el('div', { class: 'stack' });
        const opts = el('div', { class: 'stack' });
        const str = el('div');
        const listBox = el('div', { class: 'stack' });
        const checkBox = el('div', { class: 'stack' });

        function regen() {
            const n = Math.max(1, Math.min(100, cfg.count | 0));
            list = [];
            let bits = 0;
            for (let i = 0; i < n; i++) { const r = generate(cfg); list.push(r.pw); bits = r.bits; }
            renderOut(bits);
        }
        function renderOut(bits) {
            const pw = list[0] || '';
            fill(out,
                el('div', { class: 'pw-out' }, colored(pw),
                    el('div', { class: 'row nw' },
                        btn(T('Kopieren'), 'btn-primary', () => CF.copy(pw), 'copy'),
                        ibtn('refresh', T('Neu erzeugen (Leertaste)'), () => regen()))),
                el('div', { class: 'row small' },
                    el('span', { class: 'muted', text: T('%s Zeichen', Array.from(pw).length) }),
                    btn(T('Als QR-Code'), 'btn-ghost btn-sm', () => CF.go('create', { text: pw }), 'qr'),
                    btn(T('WLAN-QR damit'), 'btn-ghost btn-sm', () => CF.go('create', { tpl: 'wifi', values: { p: pw } }), 'wifi')));
            fill(str, strengthBox(bits));
            if (list.length > 1) {
                fill(listBox, el('div', { class: 'row between' }, el('b', { text: T('%s Passwörter', list.length) }),
                    el('div', { class: 'row' }, btn(T('Alle kopieren'), 'btn-second btn-sm', () => CF.copy(list.join('\n')), 'copy'),
                        btn('CSV', 'btn-ghost btn-sm', () => CF.download('passwoerter-' + CF.fileStamp() + '.csv', new Blob([CF.csv([[T('Nr.'), T('Passwort')]].concat(list.map((p, i) => [i + 1, p])))], { type: 'text/csv;charset=utf-8' })), 'download'))),
                    el('div', { class: 'pw-list mono' }, list.map((p, i) => el('div', { class: 'pw-li', title: T('Klicken zum Kopieren'), onclick: () => CF.copy(p) }, el('span', { class: 'faint', text: String(i + 1).padStart(2, ' ') }), el('span', { class: 'break', text: p })))));
            } else fill(listBox);
        }

        const num = (obj, key, min, max, label, onch) => {
            const r = el('input', { type: 'range', min, max, value: obj[key] });
            const n = input({ type: 'number', min, max, value: obj[key], style: { width: '5rem' } });
            const set = v => { v = Math.max(min, Math.min(max, +v || min)); obj[key] = v; r.value = v; n.value = v; save(); regen(); if (onch) onch(); };
            r.addEventListener('input', () => set(r.value));
            n.addEventListener('change', () => set(n.value));
            return field(label, el('div', { class: 'row nw' }, r, n));
        };
        const tog = (obj, key, label, sub) => CF.toggle(label, !!obj[key], v => { obj[key] = v; save(); regen(); }, sub);
        const txt = (obj, key, label, hint, ph) => { const i = input({ value: obj[key] || '', placeholder: ph || '', spellcheck: 'false', autocomplete: 'off' }); i.addEventListener('input', () => { obj[key] = i.value; save(); regen(); }); return field(label, i, hint); };

        function renderOpts() {
            const o = cfg[cfg.mode];
            let body;
            switch (cfg.mode) {
                case 'phrase':
                    body = [num(o, 'words', 3, 12, T('Wörter')),
                        el('div', { class: 'grid2' },
                            field(T('Sprache'), select([['de', T('Deutsch')], ['en', T('Englisch')], ['mix', T('gemischt')]], o.lang, v => { o.lang = v; save(); regen(); })),
                            field(T('Trenner'), select([['-', '-'], ['.', '.'], ['_', '_'], [' ', T('Leerzeichen')], ['', T('keiner')], ['/', '/']], o.sep, v => { o.sep = v; save(); regen(); }))),
                        field(T('Großschreibung'), CF.seg([['none', T('keine')], ['first', T('Wortanfang')], ['random', T('zufällig')], ['all', T('alles')]], o.caps, v => { o.caps = v; save(); regen(); }, 'full')),
                        tog(o, 'num', T('Mit Zahl')), tog(o, 'sym', T('Mit Sonderzeichen')),
                        el('div', { class: 'tiny faint', text: T('%s Wörter in der Liste. Jedes Wort bringt rund %s Bit.', CF.fmtNum((o.lang === 'mix' ? CF.WORDS.de.length + CF.WORDS.en.length : (CF.WORDS[o.lang] || []).length)), (log2(o.lang === 'mix' ? CF.WORDS.de.length + CF.WORDS.en.length : (CF.WORDS[o.lang] || [1]).length)).toFixed(1)) })];
                    break;
                case 'pin':
                    body = [num(o, 'len', 4, 12, T('Stellen')), tog(o, 'noTrivial', T('Keine leicht erratbaren PINs'), T('ohne 1234, 0000, Jahreszahlen, Daten, Muster'))];
                    break;
                case 'say':
                    body = [num(o, 'syl', 2, 10, T('Silben')), num(o, 'digits', 0, 6, T('Ziffern am Ende')), tog(o, 'caps', T('Großbuchstaben'))];
                    break;
                case 'pattern':
                    body = [txt(o, 'p', T('Muster'), T('A = Großbuchstabe, a = Kleinbuchstabe, 9 = Ziffer, # = Sonderzeichen, ? = Buchstabe, * = beliebig, \\ = nächstes Zeichen wörtlich'), 'Aaaa-aaaa-9999'),
                        el('div', { class: 'chips' }, ['Aaaa-aaaa-9999', 'aaa9-aaa9-aaa9-aaa9', '????????##99', 'AAAA-9999-AAAA-9999', '*************'].map(p => el('button', { type: 'button', class: 'chip', text: p, onclick: () => { o.p = p; save(); renderOpts(); regen(); } })))];
                    break;
                case 'token':
                    body = [field(T('Format'), CF.seg([['hex', 'Hex'], ['b64', 'Base64url'], ['b32', 'Base32'], ['uuid', 'UUID v4']], o.fmt, v => { o.fmt = v; save(); renderOpts(); regen(); }, 'full')),
                        o.fmt !== 'uuid' ? field(T('Länge in Bytes'), CF.seg([['16', '16 (128 Bit)'], ['24', '24'], ['32', '32 (256 Bit)'], ['48', '48'], ['64', '64 (512 Bit)']], String(o.bytes), v => { o.bytes = +v; save(); regen(); }, 'full')) : null,
                        txt(o, 'prefix', T('Präfix'), T('optional, z. B. sk_live_'), 'sk_')];
                    break;
                default:
                    body = [num(o, 'len', 4, 128, T('Länge')),
                        el('div', { class: 'chips' },
                            [['lower', 'a–z'], ['upper', 'A–Z'], ['digits', '0–9'], ['symbols', '!#$%…']].map(([k, l]) => el('button', { type: 'button', class: 'chip' + (o[k] ? ' on' : ''), text: l, onclick: () => { o[k] = !o[k]; if (!o.lower && !o.upper && !o.digits && !o.symbols) o.lower = true; save(); renderOpts(); regen(); } }))),
                        o.symbols ? txt(o, 'sym', T('Erlaubte Sonderzeichen'), T('zickige Seiten mögen oft nur wenige')) : null,
                        tog(o, 'noAmbig', T('Ohne Verwechsler'), 'l 1 I | O 0 o B 8 S 5 Z 2'),
                        tog(o, 'letterFirst', T('Mit Buchstabe beginnen')),
                        tog(o, 'noRepeat', T('Kein Zeichen zweimal hintereinander')),
                        el('details', { class: 'adv' }, el('summary', {}, T('Feineinstellungen')), el('div', { class: 'stack' },
                            num(o, 'minEach', 0, 5, T('Mindestens je Zeichenart')),
                            txt(o, 'exclude', T('Diese Zeichen nie verwenden'), '', '"\'`\\')))];
            }
            fill(opts, body);
        }

        const modeSeg = CF.seg([['chars', T('Zeichen'), 'key'], ['phrase', T('Passphrase'), 'text'], ['pin', 'PIN', 'hash'], ['say', T('Aussprechbar'), 'volume'], ['pattern', T('Muster'), 'grid'], ['token', T('Token'), 'terminal']],
            cfg.mode, v => { cfg.mode = v; save(); renderOpts(); regen(); });
        const presets = el('div', { class: 'chips' }, PRESETS.map(([id, name, hint, p]) => el('button', { type: 'button', class: 'chip', title: T(hint), text: T(name), onclick: () => {
            cfg.mode = p.mode;
            Object.keys(p).forEach(k => { if (k !== 'mode') Object.assign(cfg[k], p[k]); });
            save(); modeSeg.set(cfg.mode); renderOpts(); regen();
        } })));
        const countSel = CF.seg([['1', '1'], ['5', '5'], ['10', '10'], ['25', '25'], ['50', '50']], String(cfg.count), v => { cfg.count = +v; save(); regen(); });

        /* Stärke-Check für ein eigenes Passwort */
        function renderCheck() {
            const inp = input({ type: 'password', placeholder: T('Passwort eingeben'), autocomplete: 'off', spellcheck: 'false', class: 'in big mono' });
            const eye = ibtn('eye', T('Anzeigen'), () => { inp.type = inp.type === 'password' ? 'text' : 'password'; eye.classList.toggle('on', inp.type === 'text'); });
            const res = el('div', { class: 'stack' });
            const run = () => {
                const v = inp.value;
                if (!v) { fill(res, el('p', { class: 'muted small', text: T('Die Prüfung läuft nur in deinem Browser. Nichts wird gesendet oder gespeichert.') })); return; }
                const a = analyze(v);
                fill(res, strengthBox(a.bits),
                    a.findings.length ? el('div', { class: 'stack' }, a.findings.map(([k, t]) => CF.msg(k, t))) : CF.msg('ok', T('Keine typischen Muster gefunden.')),
                    el('div', { class: 'tiny faint', text: T('Eine Schätzung: Echte Angreifer nutzen geleakte Passwortlisten und Regeln. Ein Passwort, das schon irgendwo benutzt wurde, ist nie sicher.') }));
            };
            inp.addEventListener('input', run);
            run();
            fill(checkBox, CF.card(T('Eigenes Passwort prüfen'), 'shield', el('div', { class: 'stack' }, el('div', { class: 'row nw' }, el('div', { class: 'grow' }, inp), eye), res)));
            setTimeout(() => inp.focus(), 30);
        }

        const genView = el('div', { class: 'layout' },
            el('div', { class: 'stack lg' },
                CF.card(T('Art'), 'key', el('div', { class: 'stack' }, modeSeg, el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Vorlagen') }), presets))),
                CF.card(T('Einstellungen'), 'settings', opts)),
            el('div', { class: 'sticky stack lg' },
                CF.card(T('Dein Passwort'), 'sparkles', el('div', { class: 'stack' }, out, el('div', { class: 'row small' }, el('span', { class: 'muted', text: T('Anzahl') }), countSel), listBox)),
                CF.card(T('Stärke'), 'shield', str),
                CF.msg('info', T('Wird nur in deinem Browser erzeugt (Web Crypto), nichts davon geht an den Server.'), 'lock')));
        const tabs = CF.seg([['gen', T('Erzeugen'), 'wand'], ['check', T('Stärke prüfen'), 'shield']], tab, v => { tab = v; show(); });
        function show() {
            genView.hidden = tab !== 'gen';
            checkBox.hidden = tab !== 'check';
            if (tab === 'check') renderCheck();
        }
        fill(root, el('div', { class: 'row between cf-bar' }, tabs), genView, checkBox);
        renderOpts();
        regen();
        show();
        const onKey = e => { if (tab === 'gen' && e.key === ' ' && !/INPUT|TEXTAREA|SELECT|BUTTON/.test((e.target.tagName || ''))) { e.preventDefault(); regen(); } };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    },
};
CF.Pw = { generate, analyze, crackTime, rnd };
})();
