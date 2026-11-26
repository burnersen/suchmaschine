/* CodeForge – QR-Code-Encoder nach ISO/IEC 18004
 *
 * Eigene Umsetzung ohne fremde Bibliothek: Versionen 1 bis 40, Fehlerkorrektur L, M, Q, H,
 * Ziffern-, Alphanumerik- und Byte-Modus mit optimaler Aufteilung, alle acht Masken.
 * Liefert neben dem Muster auch den Aufbau (welches Modul ist was, welches Byte liegt wo),
 * daraus zeichnet der Röntgenblick seine Ansicht.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

const LEVELS = ['L', 'M', 'Q', 'H'];
const LEVEL_FORMAT_BITS = [1, 0, 3, 2];
// Fehlerkorrektur-Bytes pro Block und Zahl der Blöcke, je Stufe (L, M, Q, H) und Version (Index 1 bis 40)
const ECC_PER_BLOCK = [
    [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
    [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
];
const NUM_BLOCKS = [
    [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
    [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
    [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
    [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
];

// Modultypen für den Röntgenblick
const TYPE = { DATA: 0, FINDER: 1, SEPARATOR: 2, TIMING: 3, ALIGN: 4, FORMAT: 5, VERSION: 6, DARK: 7, REMAINDER: 8 };

const ALNUM = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
const MODE_BITS = { N: 1, A: 2, B: 4 };

function countBits(mode, ver) {
    const i = ver <= 9 ? 0 : ver <= 26 ? 1 : 2;
    return mode === 'N' ? [10, 12, 14][i] : mode === 'A' ? [9, 11, 13][i] : [8, 16, 16][i];
}

/** Zahl der Module, die für Daten und Fehlerkorrektur bleiben (ohne Funktionsmuster) */
function rawDataModules(ver) {
    let r = (16 * ver + 128) * ver + 64;
    if (ver >= 2) {
        const n = Math.floor(ver / 7) + 2;
        r -= (25 * n - 10) * n - 55;
        if (ver >= 7) r -= 36;
    }
    return r;
}

function dataCodewords(ver, ecl) {
    return Math.floor(rawDataModules(ver) / 8) - ECC_PER_BLOCK[ecl][ver] * NUM_BLOCKS[ecl][ver];
}

function alignPositions(ver) {
    if (ver === 1) return [];
    const n = Math.floor(ver / 7) + 2;
    const size = ver * 4 + 17;
    const step = ver === 32 ? 26 : Math.ceil((ver * 4 + 4) / (n * 2 - 2)) * 2;
    const out = [6];
    for (let pos = size - 7; out.length < n; pos -= step) out.splice(1, 0, pos);
    return out;
}

/* ---------- Text in Abschnitte aufteilen (Ziffern, Alphanumerik, Bytes), so kurz wie möglich ---------- */

const enc = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
function utf8Bytes(s) {
    if (enc) return Array.from(enc.encode(s));
    const out = [];
    const u = unescape(encodeURIComponent(s));
    for (let i = 0; i < u.length; i++) out.push(u.charCodeAt(i));
    return out;
}

function segmentText(text, ver) {
    const cps = Array.from(text);
    if (!cps.length) return [];
    const modes = ['B', 'A', 'N'];
    const head = modes.map(m => (4 + countBits(m, ver)) * 6); // Kosten in Sechstel-Bit
    let prev = head.slice();
    const choice = [];
    for (const c of cps) {
        const cur = [Infinity, Infinity, Infinity];
        const pick = [null, null, null];
        cur[0] = prev[0] + utf8Bytes(c).length * 48; pick[0] = 'B';
        if (ALNUM.indexOf(c) >= 0) { cur[1] = prev[1] + 33; pick[1] = 'A'; }
        if (c >= '0' && c <= '9') { cur[2] = prev[2] + 20; pick[2] = 'N'; }
        // Nach diesem Zeichen in einen anderen Modus wechseln
        for (let j = 0; j < 3; j++) {
            for (let k = 0; k < 3; k++) {
                if (pick[k] === null || k === j) continue;
                const cost = Math.ceil(cur[k] / 6) * 6 + head[j];
                if (cost < cur[j]) { cur[j] = cost; pick[j] = modes[k]; }
            }
        }
        // pick[j] ist der Modus, in dem dieses Zeichen steht, wenn man danach in Modus j ist
        choice.push(pick.slice());
        prev = cur;
    }
    let best = 0;
    for (let j = 1; j < 3; j++) if (prev[j] < prev[best]) best = j;
    let mode = modes[best];
    const charModes = new Array(cps.length);
    for (let i = cps.length - 1; i >= 0; i--) {
        mode = choice[i][modes.indexOf(mode)];
        charModes[i] = mode;
    }
    const segs = [];
    for (let i = 0; i < cps.length; i++) {
        const last = segs[segs.length - 1];
        if (last && last.mode === charModes[i]) last.text += cps[i];
        else segs.push({ mode: charModes[i], text: cps[i] });
    }
    return segs;
}

function segmentBits(seg) {
    const out = [];
    const push = (val, len) => { for (let i = len - 1; i >= 0; i--) out.push((val >>> i) & 1); };
    if (seg.mode === 'N') {
        const t = seg.text;
        for (let i = 0; i < t.length; i += 3) {
            const chunk = t.substr(i, 3);
            push(parseInt(chunk, 10), chunk.length * 3 + 1);
        }
        seg.count = t.length;
    } else if (seg.mode === 'A') {
        const t = seg.text;
        for (let i = 0; i + 1 < t.length; i += 2) push(ALNUM.indexOf(t[i]) * 45 + ALNUM.indexOf(t[i + 1]), 11);
        if (t.length % 2) push(ALNUM.indexOf(t[t.length - 1]), 6);
        seg.count = t.length;
    } else {
        const bytes = seg.bytes || utf8Bytes(seg.text);
        bytes.forEach(b => push(b, 8));
        seg.count = bytes.length;
    }
    return out;
}

function totalBits(segs, ver, eci) {
    let n = eci ? 12 : 0;
    for (const s of segs) {
        const len = s.bits.length;
        if (s.count >= (1 << countBits(s.mode, ver))) return Infinity;
        n += 4 + countBits(s.mode, ver) + len;
    }
    return n;
}

/* ---------- Reed-Solomon über GF(256) ---------- */

function gfMul(x, y) {
    let z = 0;
    for (let i = 7; i >= 0; i--) {
        z = (z << 1) ^ ((z >>> 7) * 0x11D);
        z ^= ((y >>> i) & 1) * x;
    }
    return z;
}

const divisorCache = {};
function rsDivisor(degree) {
    if (divisorCache[degree]) return divisorCache[degree];
    const r = new Array(degree).fill(0);
    r[degree - 1] = 1;
    let rt = 1;
    for (let i = 0; i < degree; i++) {
        for (let j = 0; j < r.length; j++) {
            r[j] = gfMul(r[j], rt);
            if (j + 1 < r.length) r[j] ^= r[j + 1];
        }
        rt = gfMul(rt, 2);
    }
    return (divisorCache[degree] = r);
}

function rsRemainder(data, divisor) {
    const r = divisor.map(() => 0);
    for (const b of data) {
        const f = b ^ r.shift();
        r.push(0);
        divisor.forEach((c, i) => { r[i] ^= gfMul(c, f); });
    }
    return r;
}

/* ---------- Aufbau: Funktionsmuster und Lage der Bytes ---------- */

function formatWord(ecl, mask) {
    const data = (LEVEL_FORMAT_BITS[ecl] << 3) | mask;
    let rem = data;
    for (let i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    return ((data << 10) | rem) ^ 0x5412;
}

function versionWord(ver) {
    let rem = ver;
    for (let i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
    return (ver << 12) | rem;
}

/** Die 15 Formatbits: Lage der ersten und der zweiten Kopie */
function formatPositions(size) {
    const a = [], b = [];
    for (let i = 0; i <= 5; i++) a.push([8, i]);
    a.push([8, 7], [8, 8], [7, 8]);
    for (let i = 9; i < 15; i++) a.push([14 - i, 8]);
    for (let i = 0; i < 8; i++) b.push([size - 1 - i, 8]);
    for (let i = 8; i < 15; i++) b.push([8, size - 15 + i]);
    return [a, b];
}

/**
 * Baut das leere Gerüst einer Version: Funktionsmuster (schon gezeichnet) und für jedes Datenmodul
 * die Nummer des Bytes und des Bits, das dort landet. Unabhängig vom Inhalt, deshalb auch für
 * gescannte Codes nutzbar.
 */
function layout(ver, ecl) {
    const size = ver * 4 + 17;
    const n = size * size;
    const mod = new Uint8Array(n);
    const type = new Uint8Array(n).fill(255);
    const set = (x, y, dark, t) => { const i = y * size + x; mod[i] = dark ? 1 : 0; type[i] = t; };

    for (let i = 0; i < size; i++) { set(6, i, i % 2 === 0, TYPE.TIMING); set(i, 6, i % 2 === 0, TYPE.TIMING); }
    for (const [cx, cy] of [[3, 3], [size - 4, 3], [3, size - 4]]) {
        for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) {
            const x = cx + dx, y = cy + dy;
            if (x < 0 || y < 0 || x >= size || y >= size) continue;
            const d = Math.max(Math.abs(dx), Math.abs(dy));
            set(x, y, d !== 2 && d !== 4, d === 4 ? TYPE.SEPARATOR : TYPE.FINDER);
        }
    }
    const al = alignPositions(ver);
    const last = al.length - 1;
    for (let i = 0; i < al.length; i++) for (let j = 0; j < al.length; j++) {
        if ((i === 0 && j === 0) || (i === 0 && j === last) || (i === last && j === 0)) continue;
        for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) {
            set(al[i] + dx, al[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1, TYPE.ALIGN);
        }
    }
    // Formatbits erst als Platzhalter, die echten kommen nach der Wahl der Maske
    const [fa, fb] = formatPositions(size);
    fa.concat(fb).forEach(([x, y]) => set(x, y, false, TYPE.FORMAT));
    set(8, size - 8, true, TYPE.DARK);
    if (ver >= 7) {
        const bits = versionWord(ver);
        for (let i = 0; i < 18; i++) {
            const bit = (bits >>> i) & 1;
            const a = size - 11 + (i % 3), b = Math.floor(i / 3);
            set(a, b, bit, TYPE.VERSION);
            set(b, a, bit, TYPE.VERSION);
        }
    }

    // Zickzack von rechts unten: Bit i landet an Position order[i]
    const order = [];
    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right = 5;
        for (let v = 0; v < size; v++) {
            for (let j = 0; j < 2; j++) {
                const x = right - j;
                const up = ((right + 1) & 2) === 0;
                const y = up ? size - 1 - v : v;
                const i = y * size + x;
                if (type[i] === 255) order.push(i);
            }
        }
    }

    // Welches Byte gehört zu welchem Block? (nach dem Verschränken)
    const ecc = ECC_PER_BLOCK[ecl][ver];
    const blocks = NUM_BLOCKS[ecl][ver];
    const raw = Math.floor(rawDataModules(ver) / 8);
    const shortBlocks = blocks - raw % blocks;
    const shortLen = Math.floor(raw / blocks);
    const lens = [];
    for (let b = 0; b < blocks; b++) lens.push(shortLen - ecc + (b < shortBlocks ? 0 : 1));
    const cwInfo = []; // pro verschränktem Byte: {block, ec, index}
    const maxLen = Math.max.apply(null, lens);
    for (let i = 0; i < maxLen; i++) for (let b = 0; b < blocks; b++) if (i < lens[b]) cwInfo.push({ block: b, ec: false, index: i });
    for (let i = 0; i < ecc; i++) for (let b = 0; b < blocks; b++) cwInfo.push({ block: b, ec: true, index: lens[b] + i });

    const cw = new Int16Array(n).fill(-1);
    order.forEach((idx, bit) => {
        if (bit < raw * 8) cw[idx] = bit >>> 3;
        else { cw[idx] = -2; type[idx] = TYPE.REMAINDER; }
    });
    for (let i = 0; i < n; i++) if (type[i] === 255) type[i] = TYPE.DATA;

    return { version: ver, ecl, size, mod, type, cw, order, cwInfo, blockLens: lens, eccPerBlock: ecc, blocks, rawCodewords: raw };
}

/* ---------- Masken und Bewertung ---------- */

const MASKS = [
    (x, y) => (x + y) % 2 === 0,
    (x, y) => y % 2 === 0,
    (x) => x % 3 === 0,
    (x, y) => (x + y) % 3 === 0,
    (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0,
    (x, y) => (x * y) % 2 + (x * y) % 3 === 0,
    (x, y) => ((x * y) % 2 + (x * y) % 3) % 2 === 0,
    (x, y) => ((x + y) % 2 + (x * y) % 3) % 2 === 0,
];
const MASK_FORMULAS = ['(x + y) mod 2 = 0', 'y mod 2 = 0', 'x mod 3 = 0', '(x + y) mod 3 = 0',
    '(⌊x/3⌋ + ⌊y/2⌋) mod 2 = 0', '(xy mod 2) + (xy mod 3) = 0', '((xy mod 2) + (xy mod 3)) mod 2 = 0', '((x+y) mod 2 + (xy mod 3)) mod 2 = 0'];

function applyMask(mod, type, size, m) {
    const f = MASKS[m];
    for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
        const i = y * size + x;
        if ((type[i] === TYPE.DATA || type[i] === TYPE.REMAINDER) && f(x, y)) mod[i] ^= 1;
    }
}

function drawFormat(mod, size, ecl, mask) {
    const bits = formatWord(ecl, mask);
    const [a, b] = formatPositions(size);
    for (let i = 0; i < 15; i++) {
        const bit = (bits >>> i) & 1;
        mod[a[i][1] * size + a[i][0]] = bit;
        mod[b[i][1] * size + b[i][0]] = bit;
    }
    mod[(size - 8) * size + 8] = 1;
}

function penalty(mod, size) {
    let score = 0;
    const get = (x, y) => mod[y * size + x];
    const finderLike = line => {
        let c = 0;
        const p = [0, 0, 0, 0].concat(line, [0, 0, 0, 0]);
        for (let i = 0; i + 11 <= p.length; i++) {
            const s = p.slice(i, i + 11).join('');
            if (s === '10111010000' || s === '00001011101') c++;
        }
        return c;
    };
    for (let pass = 0; pass < 2; pass++) {
        for (let a = 0; a < size; a++) {
            const line = [];
            let run = 0, prev = -1;
            for (let b = 0; b < size; b++) {
                const v = pass ? get(a, b) : get(b, a);
                line.push(v);
                if (v === prev) { run++; if (run === 5) score += 3; else if (run > 5) score++; }
                else { run = 1; prev = v; }
            }
            score += finderLike(line) * 40;
        }
    }
    for (let y = 0; y < size - 1; y++) for (let x = 0; x < size - 1; x++) {
        const v = get(x, y);
        if (v === get(x + 1, y) && v === get(x, y + 1) && v === get(x + 1, y + 1)) score += 3;
    }
    let dark = 0;
    for (let i = 0; i < mod.length; i++) dark += mod[i];
    const total = size * size;
    score += (Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1) * 10;
    return score;
}

/* ---------- Kodieren ---------- */

/**
 * Text zu QR-Code.
 * opts: ecl ('L'|'M'|'Q'|'H'), minVersion, maxVersion, mask (-1 = beste), boost (Stufe hochsetzen, wenn Platz ist), eci (UTF-8 kennzeichnen)
 */
function encode(text, opts) {
    opts = Object.assign({ ecl: 'M', minVersion: 1, maxVersion: 40, mask: -1, boost: true, eci: false }, opts || {});
    let ecl = Math.max(0, LEVELS.indexOf(opts.ecl));
    const eci = !!opts.eci && /[^\x00-\x7f]/.test(text);
    let ver, segs, used;
    const cache = {};
    for (ver = opts.minVersion; ver <= opts.maxVersion; ver++) {
        const cls = ver <= 9 ? 0 : ver <= 26 ? 1 : 2;
        if (!cache[cls]) {
            const s = segmentText(text, ver);
            s.forEach(x => { x.bits = segmentBits(x); });
            cache[cls] = s;
        }
        segs = cache[cls];
        used = totalBits(segs, ver, eci);
        if (used <= dataCodewords(ver, ecl) * 8) break;
    }
    if (ver > opts.maxVersion) {
        const err = new Error('too long');
        err.code = 'too_long';
        throw err;
    }
    if (opts.boost) for (let e = ecl + 1; e < 4; e++) if (used <= dataCodewords(ver, e) * 8) ecl = e;

    // Bitstrom
    const bits = [];
    const push = (val, len) => { for (let i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); };
    if (eci) { push(7, 4); push(26, 8); }
    for (const s of segs) {
        push(MODE_BITS[s.mode], 4);
        push(s.count, countBits(s.mode, ver));
        for (const b of s.bits) bits.push(b);
    }
    const cap = dataCodewords(ver, ecl) * 8;
    push(0, Math.min(4, cap - bits.length));
    push(0, (8 - bits.length % 8) % 8);
    for (let pad = 0xEC; bits.length < cap; pad ^= 0xEC ^ 0x11) push(pad, 8);
    const data = [];
    for (let i = 0; i < bits.length; i += 8) {
        let b = 0;
        for (let j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
        data.push(b);
    }

    // Blöcke, Fehlerkorrektur, verschränken
    const L = layout(ver, ecl);
    const div = rsDivisor(L.eccPerBlock);
    const blocks = [];
    let k = 0;
    for (let b = 0; b < L.blocks; b++) {
        const dat = data.slice(k, k + L.blockLens[b]);
        k += L.blockLens[b];
        blocks.push({ dat, ecc: rsRemainder(dat, div) });
    }
    const codewords = L.cwInfo.map(c => (c.ec ? blocks[c.block].ecc[c.index - L.blockLens[c.block]] : blocks[c.block].dat[c.index]));

    const size = L.size;
    const base = L.mod;
    L.order.forEach((idx, bit) => {
        if (bit < codewords.length * 8) base[idx] = (codewords[bit >>> 3] >>> (7 - (bit & 7))) & 1;
    });

    let mask = opts.mask;
    let best = null;
    if (mask < 0 || mask > 7) {
        let bestScore = Infinity;
        for (let m = 0; m < 8; m++) {
            const t = base.slice();
            applyMask(t, L.type, size, m);
            drawFormat(t, size, ecl, m);
            const s = penalty(t, size);
            if (s < bestScore) { bestScore = s; mask = m; best = t; }
        }
    } else {
        best = base.slice();
        applyMask(best, L.type, size, mask);
        drawFormat(best, size, ecl, mask);
    }

    return {
        text, version: ver, size, ecl: LEVELS[ecl], mask, modules: best, type: L.type, cw: L.cw, cwInfo: L.cwInfo,
        codewords, dataCount: data.length, eccCount: codewords.length - data.length, blocks: L.blocks,
        usedBits: used, capacityBits: cap, eci,
        segments: segs.map(s => ({ mode: s.mode, count: s.count, bits: s.bits.length + 4 + countBits(s.mode, ver) })),
        isDark: (x, y) => x >= 0 && y >= 0 && x < size && y < size && best[y * size + x] === 1,
    };
}

/** Liest Fehlerkorrektur und Maske aus einem fertigen Muster (z. B. von einem gescannten Code) */
function readFormat(mod, size) {
    const [a, b] = formatPositions(size);
    let bestD = 99, best = null;
    for (const pos of [a, b]) {
        let w = 0;
        for (let i = 0; i < 15; i++) w |= (mod[pos[i][1] * size + pos[i][0]] ? 1 : 0) << i;
        for (let e = 0; e < 4; e++) for (let m = 0; m < 8; m++) {
            let x = w ^ formatWord(e, m), d = 0;
            while (x) { d += x & 1; x >>>= 1; }
            if (d < bestD) { bestD = d; best = { ecl: LEVELS[e], mask: m }; }
        }
    }
    return bestD <= 3 ? best : null;
}

/** Wie viele Zeichen passen höchstens hinein? (für die Anzeige im Generator) */
function capacity(ver, ecl) {
    const bits = dataCodewords(ver, LEVELS.indexOf(ecl)) * 8;
    const n = (mode) => {
        const free = bits - 4 - countBits(mode, ver);
        if (mode === 'N') return Math.floor(free / 10) * 3 + (free % 10 >= 7 ? 2 : free % 10 >= 4 ? 1 : 0);
        if (mode === 'A') return Math.floor(free / 11) * 2 + (free % 11 >= 6 ? 1 : 0);
        return Math.floor(free / 8);
    };
    return { numeric: n('N'), alnum: n('A'), bytes: n('B') };
}

CF.QR = {
    encode, layout, readFormat, capacity, TYPE, LEVELS, MASK_FORMULAS, MASKS,
    recovery: { L: 7, M: 15, Q: 25, H: 30 },
    utf8Bytes,
};
})(typeof self !== 'undefined' ? self : globalThis);
