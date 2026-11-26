/* CodeForge – Zeichnen: QR-Codes mit Design, andere 2D-Codes und Strichcodes als SVG, dazu PNG-Export
 *
 * Gerechnet wird in Modul-Einheiten (1 = ein Punkt des Codes). Die SVGs sind gestochen scharf
 * in jeder Größe. PNG entsteht im Browser über ein Canvas.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

const DEF = {
    fg: '#0f172a', bg: '#ffffff', transparent: false, gradient: null,
    dots: 'square', eyeFrame: 'square', eyeBall: 'square', eyeColor: '',
    margin: 4, logo: null, frame: null, round: 0,
};
const FONT = "Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
const MONO = "ui-monospace, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace";

const r3 = v => Math.round(v * 1000) / 1000;
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
let uid = 0;
const nextId = () => 'cf' + (++uid).toString(36) + Math.random().toString(36).slice(2, 6);

/** Rechteck mit eigenem Radius pro Ecke (oben links, oben rechts, unten rechts, unten links), im Uhrzeigersinn */
function rrect(x, y, w, h, rad) {
    const [a, b, c, d] = Array.isArray(rad) ? rad : [rad, rad, rad, rad];
    let p = 'M' + r3(x + a) + ' ' + r3(y) + 'H' + r3(x + w - b);
    if (b) p += 'A' + r3(b) + ' ' + r3(b) + ' 0 0 1 ' + r3(x + w) + ' ' + r3(y + b);
    p += 'V' + r3(y + h - c);
    if (c) p += 'A' + r3(c) + ' ' + r3(c) + ' 0 0 1 ' + r3(x + w - c) + ' ' + r3(y + h);
    p += 'H' + r3(x + d);
    if (d) p += 'A' + r3(d) + ' ' + r3(d) + ' 0 0 1 ' + r3(x) + ' ' + r3(y + h - d);
    p += 'V' + r3(y + a);
    if (a) p += 'A' + r3(a) + ' ' + r3(a) + ' 0 0 1 ' + r3(x + a) + ' ' + r3(y);
    return p + 'Z';
}
function circle(cx, cy, r) {
    return 'M' + r3(cx - r) + ' ' + r3(cy) + 'A' + r3(r) + ' ' + r3(r) + ' 0 1 1 ' + r3(cx + r) + ' ' + r3(cy) + 'A' + r3(r) + ' ' + r3(r) + ' 0 1 1 ' + r3(cx - r) + ' ' + r3(cy) + 'Z';
}
function diamond(cx, cy, r) {
    return 'M' + r3(cx) + ' ' + r3(cy - r) + 'L' + r3(cx + r) + ' ' + r3(cy) + 'L' + r3(cx) + ' ' + r3(cy + r) + 'L' + r3(cx - r) + ' ' + r3(cy) + 'Z';
}

/** Füllung: Farbe oder Verlauf. Liefert [fill-Wert, <defs>-Inhalt] */
function paint(st, box) {
    const g = st.gradient;
    if (!g || !g.c1 || !g.c2) return [st.fg, ''];
    const id = nextId();
    const [x, y, w, h] = box;
    if (g.type === 'radial') {
        return ['url(#' + id + ')', '<radialGradient id="' + id + '" gradientUnits="userSpaceOnUse" cx="' + r3(x + w / 2) + '" cy="' + r3(y + h / 2) + '" r="' + r3(Math.max(w, h) * 0.62) + '">'
            + '<stop offset="0" stop-color="' + esc(g.c1) + '"/><stop offset="1" stop-color="' + esc(g.c2) + '"/></radialGradient>'];
    }
    const a = ((+g.angle || 45) * Math.PI) / 180;
    const cx = x + w / 2, cy = y + h / 2, dx = Math.cos(a) * w / 2, dy = Math.sin(a) * h / 2;
    return ['url(#' + id + ')', '<linearGradient id="' + id + '" gradientUnits="userSpaceOnUse" x1="' + r3(cx - dx) + '" y1="' + r3(cy - dy) + '" x2="' + r3(cx + dx) + '" y2="' + r3(cy + dy) + '">'
        + '<stop offset="0" stop-color="' + esc(g.c1) + '"/><stop offset="1" stop-color="' + esc(g.c2) + '"/></linearGradient>'];
}

/* ---------- Rahmen mit Beschriftung („Scan mich“) ---------- */

function frameLayout(st, W) {
    const f = st.frame;
    if (!f || !f.style || f.style === 'none') return { top: 0, bottom: 0, H: W };
    const B = Math.max(5, W * 0.17);
    if (f.style === 'top') return { top: B, bottom: 0, H: W + B, B };
    return { top: 0, bottom: B, H: W + B, B };
}

function frameSvg(st, W, L) {
    const f = st.frame;
    if (!L.B) return { under: '', over: '' };
    const col = f.color || st.fg;
    const txtCol = f.textColor || '#ffffff';
    const text = String(f.text || '').slice(0, 40);
    const fs = L.B * 0.44;
    const avail = W * 0.86;
    const est = text.length * fs * 0.62;
    const fit = est > avail ? ' textLength="' + r3(avail) + '" lengthAdjust="spacingAndGlyphs"' : '';
    let under = '', over = '';
    if (f.style === 'pill') {
        const ph = L.B * 0.78, pw = Math.min(W * 0.9, Math.max(W * 0.55, est + L.B * 1.2)), px = (W - pw) / 2, py = W + (L.B - ph) / 2 - 0.4;
        over += '<path d="M' + r3(W / 2 - 1.2) + ' ' + r3(py + 0.1) + 'L' + r3(W / 2) + ' ' + r3(py - 1.3) + 'L' + r3(W / 2 + 1.2) + ' ' + r3(py + 0.1) + 'Z" fill="' + esc(col) + '"/>';
        over += '<path d="' + rrect(px, py, pw, ph, ph / 2) + '" fill="' + esc(col) + '"/>';
        over += '<text x="' + r3(W / 2) + '" y="' + r3(py + ph / 2) + '" dominant-baseline="central" text-anchor="middle" font-family="' + esc(FONT) + '" font-weight="700" font-size="' + r3(fs * 0.9) + '" fill="' + esc(txtCol) + '"' + fit + '>' + esc(text) + '</text>';
        return { under, over };
    }
    const bandY = f.style === 'top' ? 0 : W;
    const rad = Math.min(3, W * 0.06);
    // Rahmen außen herum, Band mit Text oben oder unten
    over += '<path d="' + rrect(0.5, 0.5, W - 1, L.H - 1, rad) + '" fill="none" stroke="' + esc(col) + '" stroke-width="1"/>';
    over += '<path d="' + rrect(0, bandY, W, L.B, f.style === 'top' ? [rad, rad, 0, 0] : [0, 0, rad, rad]) + '" fill="' + esc(col) + '"/>';
    over += '<text x="' + r3(W / 2) + '" y="' + r3(bandY + L.B / 2) + '" dominant-baseline="central" text-anchor="middle" font-family="' + esc(FONT) + '" font-weight="800" letter-spacing="' + r3(fs * 0.06) + '" font-size="' + r3(fs) + '" fill="' + esc(txtCol) + '"' + fit + '>' + esc(text) + '</text>';
    return { under, over };
}

function wrapSvg(W, H, defs, body, st, scale) {
    const s = scale || 10;
    const bg = st.transparent ? '' : '<path d="' + rrect(0, 0, W, H, st.round ? Math.min(W, H) * st.round : 0) + '" fill="' + esc(st.bg) + '"/>';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + r3(W) + ' ' + r3(H) + '" width="' + Math.round(W * s) + '" height="' + Math.round(H * s) + '">'
        + (defs ? '<defs>' + defs + '</defs>' : '') + bg + body + '</svg>';
}

/* ---------- QR-Code mit Design ---------- */

function eyeFrame(style, x, y, flip) {
    if (style === 'circle') return circle(x + 3.5, y + 3.5, 3.5) + circle(x + 3.5, y + 3.5, 2.5);
    if (style === 'rounded') return rrect(x, y, 7, 7, 2.2) + rrect(x + 1, y + 1, 5, 5, 1.3);
    if (style === 'leaf') {
        const o = flip === 1 ? [0, 3, 0, 3] : [3, 0, 3, 0], i = flip === 1 ? [0, 2, 0, 2] : [2, 0, 2, 0];
        return rrect(x, y, 7, 7, o) + rrect(x + 1, y + 1, 5, 5, i);
    }
    if (style === 'dots') {
        let p = '';
        for (let k = 0; k < 7; k++) for (let j = 0; j < 7; j++) if (k === 0 || j === 0 || k === 6 || j === 6) p += circle(x + j + 0.5, y + k + 0.5, 0.46);
        return p;
    }
    return 'M' + x + ' ' + y + 'h7v7h-7Z' + 'M' + (x + 1) + ' ' + (y + 1) + 'h5v5h-5Z';
}
function eyeBall(style, x, y, flip) {
    if (style === 'circle') return circle(x + 3.5, y + 3.5, 1.5);
    if (style === 'rounded') return rrect(x + 2, y + 2, 3, 3, 0.9);
    if (style === 'leaf') return rrect(x + 2, y + 2, 3, 3, flip === 1 ? [0, 1.4, 0, 1.4] : [1.4, 0, 1.4, 0]);
    if (style === 'diamond') return diamond(x + 3.5, y + 3.5, 1.9);
    return 'M' + (x + 2) + ' ' + (y + 2) + 'h3v3h-3Z';
}

/**
 * q: Ergebnis von CF.QR.encode (oder {size, modules})
 * Liefert { svg, W, H, hole } – hole ist der freigeräumte Bereich für das Logo (in Modulen)
 */
function qr(q, style) {
    const st = Object.assign({}, DEF, style || {});
    const n = q.size, m = Math.max(0, +st.margin || 0);
    const W = n + 2 * m;
    const L = frameLayout(st, W);
    const oy = L.top;
    const isFinder = (x, y) => (x < 7 && y < 7) || (x >= n - 7 && y < 7) || (x < 7 && y >= n - 7);

    let hole = null;
    if (st.logo && st.logo.src) {
        let s = Math.round(n * Math.min(0.34, Math.max(0.1, +st.logo.size || 0.22)));
        if ((n - s) % 2) s++;
        const pad = st.logo.pad == null ? 1 : +st.logo.pad;
        hole = { x: (n - s) / 2, y: (n - s) / 2, s, pad };
    }
    const inHole = (x, y) => hole && x >= hole.x - hole.pad && x < hole.x + hole.s + hole.pad && y >= hole.y - hole.pad && y < hole.y + hole.s + hole.pad;
    const dark = (x, y) => x >= 0 && y >= 0 && x < n && y < n && q.modules[y * n + x] === 1 && !isFinder(x, y) && !inHole(x, y);

    let p = '';
    const X = x => x + m, Y = y => y + m + oy;
    const dots = st.dots;
    if (dots === 'square') {
        for (let y = 0; y < n; y++) {
            for (let x = 0; x < n; x++) {
                if (!dark(x, y)) continue;
                let len = 1;
                while (dark(x + len, y)) len++;
                p += 'M' + X(x) + ' ' + Y(y) + 'h' + len + 'v1h-' + len + 'Z';
                x += len - 1;
            }
        }
    } else {
        const R = 0.5;
        for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
            if (!dark(x, y)) continue;
            const t = dark(x, y - 1), r = dark(x + 1, y), b = dark(x, y + 1), l = dark(x - 1, y);
            if (dots === 'dots') p += circle(X(x) + 0.5, Y(y) + 0.5, 0.43);
            else if (dots === 'pixel') p += 'M' + r3(X(x) + 0.1) + ' ' + r3(Y(y) + 0.1) + 'h.8v.8h-.8Z';
            else if (dots === 'diamond') p += diamond(X(x) + 0.5, Y(y) + 0.5, 0.52);
            else if (dots === 'classy') {
                p += rrect(X(x), Y(y), 1, 1, [!t && !l ? R : 0, 0, !b && !r ? R : 0, 0]);
                if (r) p += 'M' + r3(X(x) + 0.5) + ' ' + Y(y) + 'h1v1h-1Z';
                if (b) p += 'M' + X(x) + ' ' + r3(Y(y) + 0.5) + 'h1v1h-1Z';
            } else { // rounded: flüssige Formen, Ecken nur dort rund, wo kein Nachbar ist
                p += rrect(X(x), Y(y), 1, 1, [!t && !l ? R : 0, !t && !r ? R : 0, !b && !r ? R : 0, !b && !l ? R : 0]);
                if (r) p += 'M' + r3(X(x) + 0.5) + ' ' + Y(y) + 'h1v1h-1Z';
                if (b) p += 'M' + X(x) + ' ' + r3(Y(y) + 0.5) + 'h1v1h-1Z';
            }
        }
    }

    const box = [m, m + oy, n, n];
    const [fill, defs1] = paint(st, box);
    const eyeFill = st.eyeColor ? st.eyeColor : fill;
    let eyesF = '', eyesB = '';
    [[0, 0, 0], [n - 7, 0, 1], [0, n - 7, 1]].forEach(([ex, ey, flip]) => {
        eyesF += eyeFrame(st.eyeFrame, X(ex), Y(ey), flip);
        eyesB += eyeBall(st.eyeBall, X(ex), Y(ey), flip);
    });

    let body = '<path d="' + p + '" fill="' + esc(fill) + '"' + (dots === 'square' ? ' shape-rendering="crispEdges"' : '') + '/>';
    body += '<path d="' + eyesF + '" fill="' + esc(eyeFill) + '" fill-rule="evenodd"/>';
    body += '<path d="' + eyesB + '" fill="' + esc(eyeFill) + '"/>';

    if (hole) {
        const lx = X(hole.x), ly = Y(hole.y);
        if (st.logo.plate) body += '<path d="' + rrect(lx - hole.pad * 0.6, ly - hole.pad * 0.6, hole.s + hole.pad * 1.2, hole.s + hole.pad * 1.2, hole.s * 0.18) + '" fill="' + esc(st.transparent ? '#ffffff' : st.bg) + '"/>';
        body += '<image x="' + r3(lx) + '" y="' + r3(ly) + '" width="' + hole.s + '" height="' + hole.s + '" preserveAspectRatio="xMidYMid meet" href="' + esc(st.logo.src) + '"/>';
    }
    const fr = frameSvg(st, W, L);
    const svg = wrapSvg(W, L.H, defs1, fr.under + body + fr.over, st);
    return { svg, W, H: L.H, hole };
}

/* ---------- Andere 2D-Codes (DataMatrix, Aztec, PDF417, Micro QR, rMQR) aus dem Muster der Engine ---------- */

/** sym: { width, height, data } mit 0 = dunkel, 255 = hell. rowH: Höhe einer Zeile in Pixeln des Musters (PDF417: 3) */
function matrix(sym, style) {
    const st = Object.assign({}, DEF, style || {});
    const w = sym.width, h = sym.height, m = Math.max(0, st.margin == null ? 2 : +st.margin);
    const W = w + 2 * m, Hs = h + 2 * m;
    const L = frameLayout(st, W);
    const H = Hs + (L.H - W);
    const oy = L.top;
    const dark = (x, y) => x >= 0 && y >= 0 && x < w && y < h && sym.data[y * w + x] === 0;
    let p = '';
    const round = st.dots === 'dots' || st.dots === 'rounded';
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            if (!dark(x, y)) continue;
            if (st.dots === 'dots' && !sym.wide) { p += circle(x + m + 0.5, y + m + oy + 0.5, 0.45); continue; }
            if (round && !sym.wide) {
                const t = dark(x, y - 1), r = dark(x + 1, y), b = dark(x, y + 1), l = dark(x - 1, y);
                p += rrect(x + m, y + m + oy, 1, 1, [!t && !l ? 0.5 : 0, !t && !r ? 0.5 : 0, !b && !r ? 0.5 : 0, !b && !l ? 0.5 : 0]);
                if (r) p += 'M' + r3(x + m + 0.5) + ' ' + (y + m + oy) + 'h1v1h-1Z';
                if (b) p += 'M' + (x + m) + ' ' + r3(y + m + oy + 0.5) + 'h1v1h-1Z';
                continue;
            }
            let len = 1;
            while (dark(x + len, y)) len++;
            p += 'M' + (x + m) + ' ' + (y + m + oy) + 'h' + len + 'v1h-' + len + 'Z';
            x += len - 1;
        }
    }
    const [fill, defs] = paint(st, [m, m + oy, w, h]);
    const body = '<path d="' + p + '" fill="' + esc(fill) + '"' + (round ? '' : ' shape-rendering="crispEdges"') + '/>';
    const fr = frameSvg(st, W, { top: L.top, bottom: L.bottom, H, B: L.B });
    return { svg: wrapSvg(W, H, defs, fr.under + body + fr.over, st), W, H };
}

/* ---------- Strichcodes ---------- */

/** code: Ergebnis von CF.Bars.encode. style: fg, bg, transparent, height (in Modulen), text (Klarschrift ja/nein) */
function linear(code, style) {
    const st = Object.assign({ fg: '#000000', bg: '#ffffff', transparent: false, height: 55, text: true }, style || {});
    const mods = code.modules;
    const [qL, qR] = code.quiet || [10, 10];
    const W = qL + mods.length + qR;
    const top = code.bearer ? 6 : 3;
    const barH = Math.max(10, +st.height || 55);
    const isEan = !!code.guards;
    const fs = isEan ? 9.5 : Math.min(11, Math.max(7, mods.length / Math.max(8, (code.hrt[0].text || '').length) * 1.25));
    const textH = st.text ? fs + 3 : 0;
    const H = top + barH + (st.text ? textH : 0) + (code.bearer ? 6 : 3);
    const inGuard = i => code.guards && code.guards.some(([a, b]) => i >= a && i < b);
    let p = '';
    for (let i = 0; i < mods.length; i++) {
        if (mods[i] !== '1') continue;
        let len = 1;
        while (i + len < mods.length && mods[i + len] === '1' && inGuard(i + len) === inGuard(i)) len++;
        const h = barH + (st.text && isEan && inGuard(i) ? fs * 0.55 : 0);
        p += 'M' + (qL + i) + ' ' + top + 'h' + len + 'v' + r3(h) + 'h-' + len + 'Z';
        i += len - 1;
    }
    let body = '<path d="' + p + '" fill="' + esc(st.fg) + '" shape-rendering="crispEdges"/>';
    if (code.bearer) {
        // Schutzbalken oben und unten (ITF-14), schützen vor schrägem Ablesen
        const t = 2.5;
        body += '<path d="M' + r3(qL - 6) + ' ' + r3(top - t - 1) + 'h' + r3(mods.length + 12) + 'v' + t + 'h-' + r3(mods.length + 12) + 'Z'
            + 'M' + r3(qL - 6) + ' ' + r3(top + barH + 1) + 'h' + r3(mods.length + 12) + 'v' + t + 'h-' + r3(mods.length + 12) + 'Z" fill="' + esc(st.fg) + '"/>';
    }
    if (st.text) {
        const ty = top + barH + (code.bearer ? 4 : 1) + fs * 0.82;
        for (const s of code.hrt) {
            const cx = qL + s.x + s.w / 2;
            const size = s.small ? fs * 0.8 : fs;
            const est = s.text.length * size * 0.6;
            const fit = isEan && s.text.length > 1 ? ' textLength="' + r3(s.w - 3) + '" lengthAdjust="spacing"' : (est > W - 2 ? ' textLength="' + r3(W - 2) + '" lengthAdjust="spacingAndGlyphs"' : '');
            body += '<text x="' + r3(cx) + '" y="' + r3(ty) + '" text-anchor="middle" font-family="' + esc(MONO) + '" font-size="' + r3(size) + '" fill="' + esc(st.fg) + '"' + fit + '>' + esc(s.text) + '</text>';
        }
    }
    return { svg: wrapSvg(W, H, '', body, st, 3), W, H };
}

/* ---------- Export ---------- */

function svgToCanvas(svg, width, height) {
    return new Promise((resolve, reject) => {
        const blob = new Blob([svg], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        const img = new Image();
        img.onload = () => {
            const c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(width));
            c.height = Math.max(1, Math.round(height));
            const ctx = c.getContext('2d');
            ctx.imageSmoothingEnabled = true;
            ctx.drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            resolve(c);
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('svg')); };
        img.src = url;
    });
}

function svgToPng(svg, width, height) {
    return svgToCanvas(svg, width, height).then(c => new Promise(res => c.toBlob(res, 'image/png')));
}

CF.Render = { qr, matrix, linear, svgToCanvas, svgToPng, DEF, esc };
})(typeof self !== 'undefined' ? self : globalThis);
