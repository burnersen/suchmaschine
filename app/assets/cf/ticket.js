/* CodeForge – Bahntickets lesen (UIC 918.3), soweit das ohne Schlüssel geht
 *
 * Aufbau: „#UT“, Version, Bahn-Code (RICS), Schlüssel-Nr., Signatur, Länge, dann zlib-gepackte Datensätze:
 *   U_HEAD  Ausgeber, Ticket-Nummer, Ausstellungszeit
 *   U_TLAY  der Ticket-Ausdruck als Textfelder (Strecke, Name, Gültigkeit …)
 *   0080BL, 0080VU, U_FLEX …  bahnspezifische Daten (werden nur aufgelistet)
 * Die Signatur wird NICHT geprüft, dafür fehlen die öffentlichen Schlüssel der Bahnen.
 * VDV-Barcodes (z. B. Deutschlandticket) sind zusätzlich verschlüsselt und werden nur erkannt.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

const ascii = (b, a, n) => String.fromCharCode.apply(null, Array.from(b.subarray(a, a + n)));
function text(bytes) {
    try { return new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch (e) { return new TextDecoder('iso-8859-1').decode(bytes); }
}
function b64bytes(s) { const bin = atob(s); return Uint8Array.from(bin, c => c.charCodeAt(0)); }

const RICS = { '1080': 'Deutsche Bahn (DB Fernverkehr)', '0080': 'Deutsche Bahn', '1181': 'ÖBB (Österreich)', '0081': 'ÖBB (Österreich)', '1185': 'SBB (Schweiz)', '0085': 'SBB (Schweiz)',
    '1184': 'NS (Niederlande)', '0084': 'NS (Niederlande)', '1187': 'SNCF (Frankreich)', '0087': 'SNCF (Frankreich)', '1088': 'SNCB (Belgien)', '0088': 'SNCB (Belgien)', '1154': 'ČD (Tschechien)',
    '1183': 'Trenitalia (Italien)', '0083': 'Trenitalia (Italien)', '1151': 'PKP Intercity (Polen)', '1086': 'DSB (Dänemark)', '1076': 'SJ (Schweden)', '1182': 'CFL (Luxemburg)', '3509': 'FlixTrain', '9901': 'Eurostar' };

function detect(bytes) {
    if (!bytes || bytes.length < 20) return null;
    if (bytes[0] === 0x23 && bytes[1] === 0x55 && bytes[2] === 0x54) return 'uic';
    if (bytes[0] === 0x9E && (bytes[1] === 0x81 || bytes[1] === 0x82)) return 'vdv';
    return null;
}

async function inflate(data) {
    const s = new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate'));
    return new Uint8Array(await new Response(s).arrayBuffer());
}

/** Liest ein UIC-918.3-Ticket. Liefert { version, rics, company, key, records: [...], head, layout } */
async function decodeUic(bytes) {
    const version = ascii(bytes, 3, 2);
    const rics = ascii(bytes, 5, 4);
    const key = ascii(bytes, 9, 5);
    const sigLen = version === '02' ? 64 : 50;
    const lenPos = 14 + sigLen;
    const len = parseInt(ascii(bytes, lenPos, 4), 10);
    if (!(len > 0)) throw new Error('length');
    const packed = bytes.subarray(lenPos + 4, lenPos + 4 + len);
    if (!root.DecompressionStream) throw new Error('nodecomp');
    const data = await inflate(packed);
    const out = { version, rics, company: RICS[rics] || '', key, records: [], head: null, layout: null };
    for (let p = 0; p + 12 <= data.length;) {
        const tag = ascii(data, p, 6), ver = ascii(data, p + 6, 2), rlen = parseInt(ascii(data, p + 8, 4), 10);
        if (!(rlen >= 12) || p + rlen > data.length) break;
        const body = data.subarray(p + 12, p + rlen);
        out.records.push({ tag, version: ver, length: rlen });
        if (tag === 'U_HEAD' && body.length >= 41) {
            const t = ascii(body, 24, 12);
            out.head = { company: ascii(body, 0, 4), ticket: ascii(body, 4, 20).trim(),
                issued: /^\d{12}$/.test(t) ? new Date(+t.substr(4, 4), +t.substr(2, 2) - 1, +t.substr(0, 2), +t.substr(8, 2), +t.substr(10, 2)) : null,
                lang: ascii(body, 37, 2) };
        }
        if (tag === 'U_TLAY' && body.length >= 8) {
            const std = ascii(body, 0, 4), count = parseInt(ascii(body, 4, 4), 10);
            const fields = [];
            let q = 8;
            for (let i = 0; i < count && q + 13 <= body.length; i++) {
                const line = +ascii(body, q, 2), col = +ascii(body, q + 2, 2), h = +ascii(body, q + 4, 2), w = +ascii(body, q + 6, 2), fmt = ascii(body, q + 8, 1), tl = +ascii(body, q + 9, 4);
                const tx = text(body.subarray(q + 13, q + 13 + tl));
                fields.push({ line, col, h, w, fmt, text: tx });
                q += 13 + tl;
            }
            out.layout = { standard: std, fields };
        }
        p += rlen;
    }
    return out;
}

/** Ticket-Ausdruck als Text-Raster (RCT2: Zeilen × 72 Spalten) */
function layoutText(layout) {
    if (!layout || !layout.fields.length) return '';
    const W = Math.max(72, ...layout.fields.map(f => f.col + f.w));
    const H = Math.max(...layout.fields.map(f => f.line + Math.max(1, f.h)));
    const grid = Array.from({ length: H }, () => Array(W).fill(' '));
    for (const f of layout.fields) {
        const lines = [];
        f.text.split(/\r?\n/).forEach(l => { for (let i = 0; i < l.length || i === 0; i += Math.max(1, f.w)) lines.push(l.substr(i, Math.max(1, f.w))); });
        lines.slice(0, Math.max(1, f.h)).forEach((l, k) => { for (let i = 0; i < l.length; i++) if (grid[f.line + k] && f.col + i < W) grid[f.line + k][f.col + i] = l[i]; });
    }
    return grid.map(r => r.join('').replace(/\s+$/, '')).join('\n').replace(/^\n+|\n+$/g, '');
}

CF.Ticket = { detect, decodeUic, layoutText, b64bytes, RICS };
})(typeof self !== 'undefined' ? self : globalThis);
