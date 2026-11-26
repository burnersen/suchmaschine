/* CodeForge – Strichcodes (1D): EAN-13, EAN-8, UPC-A, UPC-E, Code 128, GS1-128, Code 39, Code 93, ITF, ITF-14, Codabar
 *
 * Jeder Encoder liefert die Module als Folge von 0 und 1 (1 = Strich), dazu die Klarschrift
 * und bei EAN/UPC die verlängerten Randzeichen. Gezeichnet wird in render.js.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

/** Fehler mit Platzhaltern (%s), damit die Oberfläche die Meldung übersetzen kann */
function fail(msg, ...args) { const e = new Error(args.reduce((r, a) => r.replace('%s', a), msg)); e.code = 'invalid'; e.tpl = msg; e.args = args; throw e; }

/** Breitenfolge (z. B. "212222", Strich zuerst) in Module */
function widths(w, startBar) {
    let out = '', bar = startBar !== false;
    for (const c of w) { out += (bar ? '1' : '0').repeat(+c); bar = !bar; }
    return out;
}

/* ---------- EAN / UPC ---------- */

const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
const EAN_R = EAN_L.map(p => p.split('').map(b => (b === '1' ? '0' : '1')).join(''));
const EAN_G = EAN_R.map(p => p.split('').reverse().join('')); // G-Code ist die gespiegelte R-Folge
const EAN_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

function gtinBody(input, len, name) {
    const d = String(input).replace(/[\s-]/g, '');
    if (!/^\d+$/.test(d)) fail('%s: nur Ziffern erlaubt', name);
    if (d.length === len - 1) return d + CF.Num.gtinCheck(d);
    if (d.length !== len) fail('%s: %s oder %s Ziffern nötig', name, len - 1, len);
    if (!CF.Num.gtinValid(d)) fail('%s: Prüfziffer falsch, richtig wäre %s', name, CF.Num.gtinCheck(d.slice(0, -1)));
    return d;
}

function ean13(input) {
    const d = gtinBody(input, 13, 'EAN-13');
    const par = EAN_PARITY[+d[0]];
    let m = '101';
    for (let i = 1; i <= 6; i++) m += (par[i - 1] === 'L' ? EAN_L : EAN_G)[+d[i]];
    m += '01010';
    for (let i = 7; i <= 12; i++) m += EAN_R[+d[i]];
    m += '101';
    return {
        format: 'EAN13', value: d, modules: m, quiet: [11, 7],
        guards: [[0, 3], [45, 50], [92, 95]],
        hrt: [{ text: d[0], x: -6, w: 6 }, { text: d.substr(1, 6), x: 3, w: 42 }, { text: d.substr(7), x: 50, w: 42 }],
    };
}

function ean8(input) {
    const d = gtinBody(input, 8, 'EAN-8');
    let m = '101';
    for (let i = 0; i < 4; i++) m += EAN_L[+d[i]];
    m += '01010';
    for (let i = 4; i < 8; i++) m += EAN_R[+d[i]];
    m += '101';
    return {
        format: 'EAN8', value: d, modules: m, quiet: [7, 7],
        guards: [[0, 3], [31, 36], [64, 67]],
        hrt: [{ text: d.substr(0, 4), x: 3, w: 28 }, { text: d.substr(4), x: 36, w: 28 }],
    };
}

function upca(input) {
    const d = gtinBody(input, 12, 'UPC-A');
    const e = ean13('0' + d);
    return {
        format: 'UPCA', value: d, modules: e.modules, quiet: [9, 9],
        // Bei UPC-A sind auch die erste und letzte Ziffer lang
        guards: [[0, 10], [45, 50], [85, 95]],
        hrt: [{ text: d[0], x: -7, w: 6, small: true }, { text: d.substr(1, 5), x: 10, w: 35 }, { text: d.substr(6, 5), x: 50, w: 35 }, { text: d[11], x: 96, w: 6, small: true }],
    };
}

const UPCE_PARITY = ['EEEOOO', 'EEOEOO', 'EEOOEO', 'EEOOOE', 'EOEEOO', 'EOOEEO', 'EOOOEE', 'EOEOEO', 'EOEOOE', 'EOOEOE'];
function upce(input) {
    let d = String(input).replace(/[\s-]/g, '');
    if (!/^\d+$/.test(d)) fail('%s: nur Ziffern erlaubt', 'UPC-E');
    if (d.length === 12 || d.length === 11) {
        const e = CF.Num.upcAToE(d);
        if (!e) fail('%s: Diese UPC-A lässt sich nicht verkürzen', 'UPC-E');
        d = e;
    }
    if (d.length === 6) d = '0' + d;
    if (d.length === 7) { const a = CF.Num.upcEToA(d); if (!a) fail('%s: Zahlensystem muss 0 oder 1 sein', 'UPC-E'); d += a.upca[11]; }
    if (d.length !== 8) fail('%s: 6, 7 oder 8 Ziffern nötig', 'UPC-E');
    const a = CF.Num.upcEToA(d);
    if (!a) fail('%s: Zahlensystem muss 0 oder 1 sein', 'UPC-E');
    if (!a.checkOk) fail('%s: Prüfziffer falsch, richtig wäre %s', 'UPC-E', a.upca[11]);
    const ns = +d[0], check = +d[7];
    let par = UPCE_PARITY[check];
    if (ns === 1) par = par.replace(/[EO]/g, c => (c === 'E' ? 'O' : 'E'));
    let m = '101';
    for (let i = 0; i < 6; i++) m += (par[i] === 'O' ? EAN_L : EAN_G)[+d[i + 1]];
    m += '010101';
    return {
        format: 'UPCE', value: d, modules: m, quiet: [9, 7],
        guards: [[0, 3], [45, 51]],
        hrt: [{ text: d[0], x: -7, w: 6, small: true }, { text: d.substr(1, 6), x: 3, w: 42 }, { text: d[7], x: 52, w: 6, small: true }],
    };
}

/* ---------- Code 128 (mit GS1-128) ---------- */

const C128 = ['212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412', '211214', '211232', '2331112'];
const FNC1 = { fnc1: true };

/** Zahl aufeinanderfolgender Ziffern ab Position i (FNC1 unterbricht) */
function digitRun(tokens, i) {
    let n = 0;
    while (i + n < tokens.length && typeof tokens[i + n] === 'string' && tokens[i + n] >= '0' && tokens[i + n] <= '9') n++;
    return n;
}

function code128Values(tokens) {
    const vals = [];
    let set = null;
    const inA = c => c.charCodeAt(0) < 96;
    const inB = c => c.charCodeAt(0) >= 32 && c.charCodeAt(0) < 128;
    const valA = c => { const k = c.charCodeAt(0); return k < 32 ? k + 64 : k - 32; };
    const valB = c => c.charCodeAt(0) - 32;
    let i = 0;
    // Start
    const run0 = digitRun(tokens, 0);
    const fncThenDigits = tokens[0] === FNC1 && digitRun(tokens, 1) >= 2;
    if (fncThenDigits || run0 >= 4 || (run0 === tokens.length && run0 % 2 === 0 && run0 >= 2)) { set = 'C'; vals.push(105); }
    else {
        let ctrl = false;
        for (const t of tokens) { if (typeof t !== 'string') continue; if (t.charCodeAt(0) < 32) { ctrl = true; break; } if (t.charCodeAt(0) >= 96) break; }
        set = ctrl ? 'A' : 'B';
        vals.push(ctrl ? 103 : 104);
    }
    while (i < tokens.length) {
        const t = tokens[i];
        if (t === FNC1) { vals.push(102); i++; continue; }
        if (set === 'C') {
            if (digitRun(tokens, i) >= 2) { vals.push(+(t + tokens[i + 1])); i += 2; continue; }
            // Aus C heraus: nach A oder B
            const c = t;
            if (c.charCodeAt(0) < 32) { vals.push(101); set = 'A'; } else { vals.push(100); set = 'B'; }
            continue;
        }
        // A oder B: lohnt sich ein Wechsel nach C?
        const run = digitRun(tokens, i);
        const atEnd = i + run === tokens.length;
        if (run >= 4 && (atEnd || run >= 6)) {
            if (run % 2 === 1) { vals.push(set === 'A' ? valA(t) : valB(t)); i++; }
            vals.push(99); set = 'C';
            continue;
        }
        const code = t.charCodeAt(0);
        if (code > 127) fail('%s: nur ASCII-Zeichen (ohne Umlaute). Für Umlaute einen QR-Code nehmen.', 'Code 128');
        if (set === 'A' && !inA(t)) { vals.push(100); set = 'B'; }
        else if (set === 'B' && !inB(t)) { vals.push(101); set = 'A'; }
        vals.push(set === 'A' ? valA(t) : valB(t));
        i++;
    }
    let sum = vals[0];
    for (let k = 1; k < vals.length; k++) sum += vals[k] * k;
    vals.push(sum % 103);
    vals.push(106);
    return vals;
}

function code128(input, gs1) {
    let tokens, hrtText = String(input);
    if (gs1) {
        if (!CF.GS1.isHri(input)) fail('%s: Daten bitte als (01)04012345678901(10)ABC eingeben', 'GS1-128');
        let es;
        try { es = CF.GS1.toElementString(input); } catch (e) { fail(e.tpl || e.message, ...(e.args || [])); }
        tokens = [FNC1];
        for (const ch of es.text) tokens.push(ch === CF.GS1.GS ? FNC1 : ch);
        hrtText = input;
    } else {
        if (!input.length) fail('%s: Bitte Text eingeben', 'Code 128');
        tokens = Array.from(input);
        hrtText = input.replace(/[\x00-\x1f]/g, ' ');
    }
    if (tokens.length > 200) fail('%s: höchstens 200 Zeichen', 'Code 128');
    const vals = code128Values(tokens);
    const m = vals.map(v => widths(C128[v])).join('');
    return { format: gs1 ? 'GS1-128' : 'Code128', value: input, modules: m, quiet: [10, 10], hrt: [{ text: hrtText, x: 0, w: m.length }], gs1: !!gs1 };
}

/* ---------- Code 39 ---------- */

const C39_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%*';
const C39 = ['nnnwwnwnn', 'wnnwnnnnw', 'nnwwnnnnw', 'wnwwnnnnn', 'nnnwwnnnw', 'wnnwwnnnn', 'nnwwwnnnn', 'nnnwnnwnw', 'wnnwnnwnn', 'nnwwnnwnn',
    'wnnnnwnnw', 'nnwnnwnnw', 'wnwnnwnnn', 'nnnnwwnnw', 'wnnnwwnnn', 'nnwnwwnnn', 'nnnnnwwnw', 'wnnnnwwnn', 'nnwnnwwnn', 'nnnnwwwnn',
    'wnnnnnnww', 'nnwnnnnww', 'wnwnnnnwn', 'nnnnwnnww', 'wnnnwnnwn', 'nnwnwnnwn', 'nnnnnnwww', 'wnnnnnwwn', 'nnwnnnwwn', 'nnnnwnwwn',
    'wwnnnnnnw', 'nwwnnnnnw', 'wwwnnnnnn', 'nwnnwnnnw', 'wwnnwnnnn', 'nwwnwnnnn', 'nwnnnnwnw', 'wwnnnnwnn', 'nwwnnnwnn', 'nwnwnwnnn',
    'nwnwnnnwn', 'nwnnnwnwn', 'nnnwnwnwn', 'nwnnwnwnn'];

function code39(input, withCheck) {
    let s = String(input).toUpperCase();
    if (!s.length) fail('%s: Bitte Text eingeben', 'Code 39');
    for (const c of s) if (C39_CHARS.indexOf(c) < 0 || c === '*') fail('%s: Zeichen „%s“ geht nicht. Erlaubt: A–Z, 0–9, - . $ / + % und Leerzeichen', 'Code 39', c);
    let sum = 0;
    for (const c of s) sum += C39_CHARS.indexOf(c);
    const check = C39_CHARS[sum % 43];
    const full = '*' + s + (withCheck ? check : '') + '*';
    const parts = [];
    for (const c of full) parts.push(C39[C39_CHARS.indexOf(c)].replace(/n/g, '1').replace(/w/g, '3'));
    const m = parts.map(p => widths(p)).join('0');
    return { format: 'Code39', value: s, modules: m, quiet: [10, 10], hrt: [{ text: '*' + s + (withCheck ? check : '') + '*', x: 0, w: m.length }] };
}

/* ---------- Code 93 (vollständiges ASCII über Umschaltzeichen) ---------- */

const C93_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%abcd*'; // a=($) b=(%) c=(/) d=(+)
const C93 = ['100010100', '101001000', '101000100', '101000010', '100101000', '100100100', '100100010', '101010000', '100010010', '100001010',
    '110101000', '110100100', '110100010', '110010100', '110010010', '110001010', '101101000', '101100100', '101100010', '100110100',
    '100011010', '101011000', '101001100', '101000110', '100101100', '100010110', '110110100', '110110010', '110101100', '110100110',
    '110010110', '110011010', '101101100', '101100110', '100110110', '100111010', '100101110', '111010100', '111010010', '111001010',
    '101101110', '101110110', '110101110', '100100110', '111011010', '111010110', '100110010', '101011110'];

function c93Ascii(code) {
    const up = n => String.fromCharCode(65 + n);
    if (code === 0) return 'bU';
    if (code <= 26) return 'a' + up(code - 1);
    if (code <= 31) return 'b' + up(code - 27);
    if (code === 32) return ' ';
    if ('$%+-./'.indexOf(String.fromCharCode(code)) >= 0) return String.fromCharCode(code);
    if (code >= 48 && code <= 57) return String.fromCharCode(code);
    if (code >= 65 && code <= 90) return String.fromCharCode(code);
    if (code >= 97 && code <= 122) return 'd' + up(code - 97);
    const map = { 33: 'cA', 34: 'cB', 35: 'cC', 38: 'cF', 39: 'cG', 40: 'cH', 41: 'cI', 42: 'cJ', 44: 'cL', 58: 'cZ', 59: 'bF', 60: 'bG', 61: 'bH', 62: 'bI', 63: 'bJ', 64: 'bV', 91: 'bK', 92: 'bL', 93: 'bM', 94: 'bN', 95: 'bO', 96: 'bW', 123: 'bP', 124: 'bQ', 125: 'bR', 126: 'bS', 127: 'bT' };
    if (map[code]) return map[code];
    fail('%s: Zeichen nicht darstellbar', 'Code 93');
}

function code93(input) {
    const s = String(input);
    if (!s.length) fail('%s: Bitte Text eingeben', 'Code 93');
    let enc = '';
    for (const ch of s) {
        const c = ch.codePointAt(0);
        if (c > 127) fail('%s: nur ASCII-Zeichen (ohne Umlaute)', 'Code 93');
        enc += c93Ascii(c);
    }
    const vals = Array.from(enc).map(c => C93_CHARS.indexOf(c));
    const weighted = (arr, max) => { let sum = 0; for (let i = arr.length - 1, w = 1; i >= 0; i--, w = w === max ? 1 : w + 1) sum += arr[i] * w; return sum % 47; };
    const c = weighted(vals, 20);
    const k = weighted(vals.concat([c]), 15);
    const all = [47].concat(vals, [c, k], [47]);
    const m = all.map(v => C93[v]).join('') + '1';
    return { format: 'Code93', value: s, modules: m, quiet: [10, 10], hrt: [{ text: s, x: 0, w: m.length }] };
}

/* ---------- ITF und ITF-14 ---------- */

const ITF = ['nnwwn', 'wnnnw', 'nwnnw', 'wwnnn', 'nnwnw', 'wnwnn', 'nwwnn', 'nnnww', 'wnnwn', 'nwnwn'];
function itf(input, mode) {
    let d = String(input).replace(/[\s-]/g, '');
    if (!/^\d+$/.test(d)) fail('%s: nur Ziffern erlaubt', 'ITF');
    if (mode === 14) {
        if (d.length === 13) d += CF.Num.gtinCheck(d);
        if (d.length !== 14) fail('%s: 13 oder 14 Ziffern nötig', 'ITF-14');
        if (!CF.Num.gtinValid(d)) fail('%s: Prüfziffer falsch, richtig wäre %s', 'ITF-14', CF.Num.gtinCheck(d.slice(0, -1)));
    } else if (d.length % 2) d = '0' + d;
    const W = '3';
    let m = widths('1111');
    for (let i = 0; i < d.length; i += 2) {
        const a = ITF[+d[i]], b = ITF[+d[i + 1]];
        let w = '';
        for (let j = 0; j < 5; j++) w += (a[j] === 'w' ? W : '1') + (b[j] === 'w' ? W : '1');
        m += widths(w);
    }
    m += widths(W + '11');
    return { format: mode === 14 ? 'ITF14' : 'ITF', value: d, modules: m, quiet: [mode === 14 ? 12 : 10, mode === 14 ? 12 : 10], bearer: mode === 14, hrt: [{ text: mode === 14 ? d.replace(/^(\d)(\d{2})(\d{5})(\d{5})(\d)$/, '$1 $2 $3 $4 $5') : d, x: 0, w: m.length }] };
}

/* ---------- Codabar ---------- */

const CODABAR_CHARS = '0123456789-$:/.+ABCD';
const CODABAR = ['101010011', '101011001', '101001011', '110010101', '101101001', '110101001', '100101011', '100101101', '100110101', '110100101',
    '101001101', '101100101', '1101011011', '1101101011', '1101101101', '1011011011', '1011001001', '1001001011', '1010010011', '1010011001'];
function codabar(input) {
    let s = String(input).toUpperCase().replace(/\s/g, '');
    if (!/^[ABCD]/.test(s)) s = 'A' + s;
    if (!/[ABCD]$/.test(s) || s.length < 2) s = s + 'B';
    if (s.length < 3) fail('%s: Bitte Ziffern eingeben', 'Codabar');
    const body = s.slice(1, -1);
    for (const c of body) if ('0123456789-$:/.+'.indexOf(c) < 0) fail('%s: erlaubt sind 0–9 und - $ : / . +', 'Codabar');
    const m = Array.from(s).map(c => CODABAR[CODABAR_CHARS.indexOf(c)]).join('0');
    return { format: 'Codabar', value: s, modules: m, quiet: [10, 10], hrt: [{ text: body, x: 0, w: m.length }] };
}

/* ---------- Gemeinsamer Einstieg ---------- */

const FORMATS = {
    EAN13: { name: 'EAN-13', make: ean13, hint: '12 oder 13 Ziffern', example: '4006381333931', numeric: true },
    EAN8: { name: 'EAN-8', make: ean8, hint: '7 oder 8 Ziffern', example: '96385074', numeric: true },
    UPCA: { name: 'UPC-A', make: upca, hint: '11 oder 12 Ziffern', example: '036000291452', numeric: true },
    UPCE: { name: 'UPC-E', make: upce, hint: '6 bis 8 Ziffern', example: '01234565', numeric: true },
    Code128: { name: 'Code 128', make: s => code128(s, false), hint: 'Text und Ziffern (ASCII)', example: 'CodeForge-2026' },
    'GS1-128': { name: 'GS1-128', make: s => code128(s, true), hint: '(01)…(17)…(10)…', example: '(01)04012345678901(17)271231(10)ABC123' },
    Code39: { name: 'Code 39', make: s => code39(s, false), hint: 'A–Z, 0–9, - . $ / + %', example: 'CODE-39' },
    Code93: { name: 'Code 93', make: code93, hint: 'Text (ASCII)', example: 'Code93' },
    ITF: { name: 'ITF (2 aus 5)', make: s => itf(s, 0), hint: 'Ziffern', example: '12345678' },
    ITF14: { name: 'ITF-14', make: s => itf(s, 14), hint: '13 oder 14 Ziffern', example: '04012345678901', numeric: true },
    Codabar: { name: 'Codabar', make: codabar, hint: '0–9 und - $ : / . +', example: 'A40156B' },
};

function encode(format, value) {
    const f = FORMATS[format];
    if (!f) fail('Unbekanntes Format');
    return f.make(String(value == null ? '' : value));
}

CF.Bars = { encode, FORMATS, FNC1 };
})(typeof self !== 'undefined' ? self : globalThis);
