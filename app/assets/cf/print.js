/* CodeForge – Drucken: fertige Druckvorlagen (WLAN-Aufsteller, Visitenkarte, Spendenschild, Tischkarte, Poster)
 * und Etikettenbögen (Avery/Herma-Raster, Etikettendrucker, Seriennummern, Listen, ZIP-Export).
 * Gerechnet wird in Millimetern, gedruckt über den Druckdialog des Browsers (dort auch „Als PDF speichern“).
 */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;
const esc = s => CF.Render.esc(s);
const FONT = "Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
const MONO = "ui-monospace, Menlo, Consolas, monospace";

/* ---------- ZIP (ohne Kompression, reicht für Bilder und SVG) ---------- */

const CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
function crc32(b) { let c = 0xFFFFFFFF; for (let i = 0; i < b.length; i++) c = CRC[(c ^ b[i]) & 255] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; }
/** files: [{ name, data: Uint8Array }] → Blob */
function zip(files) {
    const enc = new TextEncoder();
    const parts = [], central = [];
    let offset = 0;
    const d = new Date();
    const time = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1);
    const date = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
    for (const f of files) {
        const name = enc.encode(f.name);
        const crc = crc32(f.data);
        const h = new DataView(new ArrayBuffer(30));
        h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint16(8, 0, true);
        h.setUint16(10, time, true); h.setUint16(12, date, true); h.setUint32(14, crc, true);
        h.setUint32(18, f.data.length, true); h.setUint32(22, f.data.length, true); h.setUint16(26, name.length, true); h.setUint16(28, 0, true);
        parts.push(new Uint8Array(h.buffer), name, f.data);
        const c = new DataView(new ArrayBuffer(46));
        c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true); c.setUint16(10, 0, true);
        c.setUint16(12, time, true); c.setUint16(14, date, true); c.setUint32(16, crc, true); c.setUint32(20, f.data.length, true); c.setUint32(24, f.data.length, true);
        c.setUint16(28, name.length, true); c.setUint32(42, offset, true);
        central.push(new Uint8Array(c.buffer), name);
        offset += 30 + name.length + f.data.length;
    }
    const size = central.reduce((a, b) => a + b.length, 0);
    const e = new DataView(new ArrayBuffer(22));
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true); e.setUint32(12, size, true); e.setUint32(16, offset, true);
    return new Blob([...parts, ...central, new Uint8Array(e.buffer)], { type: 'application/zip' });
}
CF.zip = zip;
CF.crc32 = crc32;

/* ---------- Hilfen ---------- */

/** Code-SVG in eine Fläche (mm) einsetzen: das innere <svg> bekommt Lage und Größe */
function place(svg, x, y, w, h) {
    return svg.replace(/^<svg xmlns="http:\/\/www.w3.org\/2000\/svg"/, '<svg x="' + x + '" y="' + y + '" preserveAspectRatio="xMidYMid meet"').replace(/ width="\d+" height="\d+"/, ' width="' + w + '" height="' + h + '"');
}
function txt(x, y, s, size, opts) {
    opts = opts || {};
    const fit = opts.max && s.length * size * (opts.mono ? 0.6 : 0.55) > opts.max ? ' textLength="' + opts.max + '" lengthAdjust="spacingAndGlyphs"' : '';
    return '<text x="' + x + '" y="' + y + '" font-family="' + esc(opts.mono ? MONO : FONT) + '" font-size="' + size + '" font-weight="' + (opts.weight || 400) + '" fill="' + esc(opts.color || '#0f172a') + '"'
        + (opts.anchor ? ' text-anchor="' + opts.anchor + '"' : '') + (opts.spacing ? ' letter-spacing="' + opts.spacing + '"' : '') + fit + '>' + esc(s) + '</text>';
}
function page(w, h, body) {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + w + ' ' + h + '" width="' + w + 'mm" height="' + h + 'mm">' + body + '</svg>';
}
const qrStyle = (color) => ({ margin: 1, dots: 'rounded', eyeFrame: 'rounded', eyeBall: 'rounded', fg: color, bg: '#ffffff', transparent: true, ecl: 'M' });
function qrSvg(text, color, ecl) {
    const q = CF.QR.encode(text, { ecl: ecl || 'M' });
    return CF.Render.qr(q, qrStyle(color)).svg;
}

/** Druckt eine oder mehrere Seiten (SVG mit Maßen in mm). pageCss z. B. 'A4' oder '62mm 29mm' */
function printPages(svgs, pageCss, margin) {
    let pa = document.getElementById('print-area');
    if (!pa) { pa = el('div', { id: 'print-area' }); document.body.append(pa); }
    pa.className = 'pages';
    pa.innerHTML = svgs.join('');
    let st = document.getElementById('print-page-style');
    if (!st) { st = el('style', { id: 'print-page-style' }); document.head.append(st); }
    st.textContent = '@page { size: ' + pageCss + '; margin: ' + (margin || '0') + '; }';
    window.print();
}
CF.printPages = printPages;

async function svgsToPngZip(svgs, names, dpi, onProgress) {
    const files = [];
    for (let i = 0; i < svgs.length; i++) {
        const m = /viewBox="0 0 ([\d.]+) ([\d.]+)"/.exec(svgs[i]);
        const w = +m[1], h = +m[2];
        const k = dpi / 25.4;
        const blob = await CF.Render.svgToPng(svgs[i], w * k, h * k);
        files.push({ name: names[i] + '.png', data: new Uint8Array(await blob.arrayBuffer()) });
        if (onProgress) onProgress(i + 1, svgs.length);
    }
    return zip(files);
}

/* ---------- Druckvorlagen ---------- */

const PALETTES = [
    ['indigo', '#4f46e5', '#eef2ff'], ['teal', '#0f766e', '#ecfdf5'], ['rose', '#be123c', '#fff1f2'], ['amber', '#b45309', '#fffbeb'], ['slate', '#0f172a', '#f1f5f9'], ['sky', '#0369a1', '#f0f9ff'],
];

const TEMPLATES = {
    wifi: {
        name: 'WLAN für Gäste', icon: 'wifi', size: [105, 148], page: 'A6',
        fields: [['title', 'Überschrift', 'Gäste-WLAN'], ['ssid', 'Netzwerkname (SSID)', ''], ['pass', 'Passwort', ''], ['enc', 'Verschlüsselung', 'WPA', [['WPA', 'WPA/WPA2/WPA3'], ['WEP', 'WEP'], ['nopass', 'Keine']]], ['hint', 'Hinweis', 'Kamera auf den Code halten und verbinden']],
        content: v => v.ssid ? 'WIFI:T:' + v.enc + ';S:' + v.ssid.replace(/([\\;,:"])/g, '\\$1') + ';' + (v.enc !== 'nopass' && v.pass ? 'P:' + v.pass.replace(/([\\;,:"])/g, '\\$1') + ';' : '') + ';' : '',
        draw(v, c, qr) {
            let b = '<rect width="105" height="148" fill="' + c[2] + '"/><rect x="6" y="6" width="93" height="136" rx="6" fill="#fff" stroke="' + c[1] + '" stroke-width=".6"/>';
            b += '<path d="M40 22a20 20 0 0 1 25 0M44 26.5a13 13 0 0 1 17 0M48 31a6 6 0 0 1 9 0" fill="none" stroke="' + c[1] + '" stroke-width="2.2" stroke-linecap="round"/><circle cx="52.5" cy="34.5" r="1.6" fill="' + c[1] + '"/>';
            b += txt(52.5, 48, v.title, 9, { anchor: 'middle', weight: 800, color: c[1], max: 85 });
            b += place(qr, 25, 54, 55, 55);
            b += txt(52.5, 119, T('Netzwerk') + ': ' + v.ssid, 4.2, { anchor: 'middle', weight: 700, max: 85 });
            if (v.enc !== 'nopass' && v.pass) b += txt(52.5, 126, T('Passwort') + ': ' + v.pass, 4.2, { anchor: 'middle', mono: true, max: 85 });
            b += txt(52.5, 135, v.hint, 3.2, { anchor: 'middle', color: '#64748b', max: 85 });
            return b;
        },
    },
    vcard: {
        name: 'Visitenkarte', icon: 'user', size: [85, 55], page: '85mm 55mm',
        fields: [['name', 'Name', ''], ['role', 'Funktion', ''], ['org', 'Firma', ''], ['tel', 'Telefon', ''], ['mail', 'E-Mail', ''], ['web', 'Webseite', '']],
        content: v => {
            if (!v.name) return '';
            const n = v.name.trim().split(/\s+/);
            const last = n.length > 1 ? n.pop() : '';
            const L = ['BEGIN:VCARD', 'VERSION:3.0', 'N:' + last + ';' + n.join(' ') + ';;;', 'FN:' + v.name];
            if (v.org) L.push('ORG:' + v.org); if (v.role) L.push('TITLE:' + v.role); if (v.tel) L.push('TEL:' + v.tel); if (v.mail) L.push('EMAIL:' + v.mail); if (v.web) L.push('URL:' + v.web);
            L.push('END:VCARD');
            return L.join('\r\n');
        },
        draw(v, c, qr) {
            let b = '<rect width="85" height="55" fill="#fff"/><rect width="4" height="55" fill="' + c[1] + '"/>';
            b += txt(9, 15, v.name, 5.2, { weight: 800, max: 42 });
            if (v.role) b += txt(9, 21, v.role, 3, { color: c[1], weight: 600, max: 42 });
            if (v.org) b += txt(9, 25.5, v.org, 3, { color: '#64748b', max: 42 });
            let y = 35;
            [v.tel, v.mail, v.web].filter(Boolean).forEach(s => { b += txt(9, y, s, 2.7, { max: 42 }); y += 4.6; });
            b += place(qr, 53, 12.5, 29, 29);
            return b;
        },
    },
    pay: {
        name: 'Spenden & Bezahlen', icon: 'bank', size: [105, 148], page: 'A6',
        fields: [['title', 'Überschrift', 'Danke für deine Unterstützung!'], ['name', 'Empfänger', ''], ['iban', 'IBAN', ''], ['amount', 'Betrag in Euro (optional)', ''], ['purpose', 'Verwendungszweck', 'Spende']],
        content: v => (v.name && v.iban ? ['BCD', '002', '1', 'SCT', '', v.name.slice(0, 70), CF.Num.ibanClean(v.iban), v.amount && +String(v.amount).replace(',', '.') > 0 ? 'EUR' + (+String(v.amount).replace(',', '.')).toFixed(2) : '', '', '', (v.purpose || '').slice(0, 140)].join('\n') : ''),
        check: v => (v.iban && !CF.Num.ibanValid(v.iban) ? 'Die IBAN ist ungültig (Prüfsumme falsch).' : ''),
        draw(v, c, qr) {
            let b = '<rect width="105" height="148" fill="#fff"/><rect width="105" height="34" fill="' + c[1] + '"/>';
            b += txt(52.5, 16, v.title, 6.2, { anchor: 'middle', weight: 800, color: '#fff', max: 92 });
            b += txt(52.5, 25, T('Mit der Banking-App scannen'), 3.6, { anchor: 'middle', color: '#fff', max: 90 });
            b += place(qr, 22.5, 40, 60, 60);
            b += txt(52.5, 110, v.name, 4.6, { anchor: 'middle', weight: 700, max: 92 });
            b += txt(52.5, 117, CF.Num.ibanFormat(v.iban), 3.6, { anchor: 'middle', mono: true, max: 92 });
            if (v.amount) b += txt(52.5, 126, (+String(v.amount).replace(',', '.')).toLocaleString(CF.locale(), { style: 'currency', currency: 'EUR' }), 6, { anchor: 'middle', weight: 800, color: c[1] });
            b += txt(52.5, 138, T('GiroCode: funktioniert mit fast allen Banking-Apps'), 2.8, { anchor: 'middle', color: '#64748b', max: 92 });
            return b;
        },
    },
    menu: {
        name: 'Tischkarte', icon: 'qr', size: [105, 148], page: 'A6',
        fields: [['title', 'Überschrift', 'Speisekarte'], ['sub', 'Unterzeile', 'Einfach scannen und bestellen'], ['url', 'Adresse (Link)', 'https://'], ['table', 'Tischnummer (optional)', '']],
        content: v => (v.url && v.url !== 'https://' ? v.url : ''),
        draw(v, c, qr) {
            let b = '<rect width="105" height="148" fill="' + c[2] + '"/><circle cx="105" cy="0" r="46" fill="' + c[1] + '" opacity=".12"/><circle cx="0" cy="148" r="38" fill="' + c[1] + '" opacity=".1"/>';
            b += txt(52.5, 28, v.title, 11, { anchor: 'middle', weight: 800, color: c[1], max: 90 });
            b += txt(52.5, 37, v.sub, 3.8, { anchor: 'middle', color: '#475569', max: 90 });
            b += '<rect x="20" y="44" width="65" height="65" rx="5" fill="#fff"/>' + place(qr, 23, 47, 59, 59);
            if (v.table) b += '<rect x="35" y="118" width="35" height="14" rx="7" fill="' + c[1] + '"/>' + txt(52.5, 127.6, T('Tisch %s', v.table), 5, { anchor: 'middle', weight: 800, color: '#fff' });
            return b;
        },
    },
    review: {
        name: 'Bewertung', icon: 'star', size: [105, 148], page: 'A6',
        fields: [['title', 'Überschrift', 'Hat es dir gefallen?'], ['sub', 'Unterzeile', 'Wir freuen uns über deine Bewertung'], ['url', 'Adresse (Link)', 'https://']],
        content: v => (v.url && v.url !== 'https://' ? v.url : ''),
        draw(v, c, qr) {
            const star = (x, y, s) => '<path transform="translate(' + x + ' ' + y + ') scale(' + s + ')" d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z" fill="#fbbf24"/>';
            let b = '<rect width="105" height="148" fill="#fff"/><rect x="0" y="0" width="105" height="6" fill="' + c[1] + '"/>';
            b += star(25, 14, 0.9) + star(35.5, 14, 0.9) + star(46, 14, 0.9) + star(56.5, 14, 0.9) + star(67, 14, 0.9);
            b += txt(52.5, 44, v.title, 7.5, { anchor: 'middle', weight: 800, max: 92 });
            b += txt(52.5, 52, v.sub, 3.8, { anchor: 'middle', color: '#475569', max: 92 });
            b += place(qr, 25, 58, 55, 55);
            b += txt(52.5, 126, T('Kamera auf den Code halten'), 3.6, { anchor: 'middle', color: c[1], weight: 700 });
            return b;
        },
    },
    poster: {
        name: 'Poster (A4)', icon: 'image', size: [210, 297], page: 'A4',
        fields: [['title', 'Überschrift', 'Jetzt scannen'], ['sub', 'Text', ''], ['url', 'Inhalt (Link oder Text)', 'https://'], ['foot', 'Fußzeile', '']],
        content: v => (v.url && v.url !== 'https://' ? v.url : ''),
        draw(v, c, qr) {
            let b = '<rect width="210" height="297" fill="#fff"/><rect width="210" height="70" fill="' + c[1] + '"/>';
            b += txt(105, 42, v.title, 18, { anchor: 'middle', weight: 800, color: '#fff', max: 190 });
            if (v.sub) b += txt(105, 58, v.sub, 6.5, { anchor: 'middle', color: '#fff', max: 190 });
            b += place(qr, 45, 90, 120, 120);
            if (v.foot) b += txt(105, 270, v.foot, 7, { anchor: 'middle', color: '#475569', max: 190 });
            return b;
        },
    },
};

function templatesPanel() {
    let tpl = CF.ls.get('printTpl', 'wifi');
    let values = CF.ls.get('printValues', {});
    let pal = CF.ls.get('printPal', 'indigo');
    let current = null;
    const tplBox = el('div', { class: 'tpls' });
    const form = el('div', { class: 'stack' });
    const prev = el('div', { class: 'preview', style: { minHeight: '22rem' } });
    const note = el('div');
    const palBox = el('div', { class: 'swatches' });
    const acts = el('div', { class: 'row' });

    const render = CF.debounce(() => {
        const t = TEMPLATES[tpl];
        const v = Object.assign({}, Object.fromEntries(t.fields.map(f => [f[0], f[2]])), values[tpl] || {});
        const c = PALETTES.find(p => p[0] === pal) || PALETTES[0];
        const content = t.content(v);
        fill(note, t.check && t.check(v) ? CF.msg('warn', T(t.check(v))) : null);
        if (!content) { current = null; fill(prev, el('div', { class: 'err', style: { color: '#64748b' }, text: T('Bitte die Felder links ausfüllen.') })); acts.querySelectorAll('button').forEach(b => { b.disabled = true; }); return; }
        let qr;
        try { qr = qrSvg(content, c[1], tpl === 'vcard' ? 'L' : 'M'); } catch (e) { fill(prev, el('div', { class: 'err', text: T('Der Inhalt ist zu lang für einen QR-Code (höchstens etwa 2.900 Bytes).') })); return; }
        current = { svg: page(t.size[0], t.size[1], t.draw(v, c, qr)), t };
        prev.innerHTML = current.svg;
        const s = prev.querySelector('svg');
        s.style.width = '100%'; s.style.height = 'auto'; s.style.maxWidth = t.size[0] > t.size[1] ? '26rem' : '18rem'; s.style.background = '#fff'; s.style.borderRadius = '.4rem';
        acts.querySelectorAll('button').forEach(b => { b.disabled = false; });
    }, 120);

    function renderForm() {
        const t = TEMPLATES[tpl];
        const v = values[tpl] = values[tpl] || {};
        fill(form, t.fields.map(([k, label, def, opts]) => {
            if (opts) return field(T(label), select(opts.map(o => [o[0], T(o[1])]), v[k] || def, x => { v[k] = x; save(); render(); }));
            const i = input({ value: v[k] != null ? v[k] : T(def) });
            if (v[k] == null) v[k] = T(def);
            i.addEventListener('input', () => { v[k] = i.value; save(); render(); });
            return field(T(label), i);
        }));
    }
    function save() { CF.ls.set('printValues', values); }
    function renderTpls() {
        fill(tplBox, Object.entries(TEMPLATES).map(([k, t]) => {
            const b = el('button', { type: 'button', class: 'tpl' + (k === tpl ? ' on' : ''), onclick: () => { tpl = k; CF.ls.set('printTpl', k); renderTpls(); renderForm(); render(); } });
            b.insertAdjacentHTML('beforeend', CF.icon(t.icon));
            b.append(el('span', { text: T(t.name) }));
            return b;
        }));
        fill(palBox, PALETTES.map(p => el('button', { type: 'button', class: p[0] === pal ? 'on' : '', style: { background: p[1] }, 'aria-label': p[0], onclick: () => { pal = p[0]; CF.ls.set('printPal', pal); renderTpls(); render(); } })));
    }
    fill(acts,
        btn(T('Drucken / PDF'), 'btn-primary', () => printPages([current.svg], current.t.page), 'printer'),
        btn('PNG (300 dpi)', 'btn-second', async () => { const k = 300 / 25.4; CF.download('vorlage-' + tpl + '.png', await CF.Render.svgToPng(current.svg, current.t.size[0] * k, current.t.size[1] * k)); }, 'download'),
        btn('SVG', 'btn-second', () => CF.download('vorlage-' + tpl + '.svg', new Blob([current.svg], { type: 'image/svg+xml' })), 'download'));
    renderTpls(); renderForm(); render();
    return el('div', { class: 'layout' },
        el('div', { class: 'stack lg' }, CF.card(T('Vorlage'), 'sparkles', el('div', { class: 'stack' }, tplBox, form, field(T('Farbe'), palBox), note))),
        el('div', { class: 'sticky stack lg' }, CF.card(T('Vorschau'), 'eye', el('div', { class: 'stack' }, prev, acts,
            el('div', { class: 'small muted', text: T('Tipp: Im Druckdialog „Ränder: Keine“ und „Skalierung: 100 %“ wählen. Als PDF speichern geht dort auch.') })))));
}

/* ---------- Etikettenbögen ---------- */

// [Name, Seite (mm), Spalten, Zeilen, Etikett B×H, Rand oben/links, Abstand x/y]
const SHEETS = {
    a4_3x8: ['A4 · 3 × 8 · 70 × 37 mm (Avery 3474, Herma 4453)', [210, 297], 3, 8, [70, 37], [0.5, 0], [0, 0]],
    a4_3x7: ['A4 · 3 × 7 · 70 × 42,3 mm (Avery 3652, Herma 4426)', [210, 297], 3, 7, [70, 42.3], [0.45, 0], [0, 0]],
    a4_2x8: ['A4 · 2 × 8 · 105 × 37 mm (Avery 3484)', [210, 297], 2, 8, [105, 37], [0.5, 0], [0, 0]],
    a4_4x10: ['A4 · 4 × 10 · 48,5 × 25,4 mm (Avery 3657, Herma 4608)', [210, 297], 4, 10, [48.5, 25.4], [21.5, 8], [0, 0]],
    a4_5x13: ['A4 · 5 × 13 · 38,1 × 21,2 mm (Avery L7651)', [210, 297], 5, 13, [38.1, 21.2], [10.7, 4.75], [2.5, 0]],
    a4_3x7r: ['A4 · 3 × 7 · 63,5 × 38,1 mm (Avery L7160)', [210, 297], 3, 7, [63.5, 38.1], [15.15, 7.25], [2.5, 0]],
    a4_2x7: ['A4 · 2 × 7 · 99,1 × 38,1 mm (Avery L7163)', [210, 297], 2, 7, [99.1, 38.1], [15.15, 4.65], [2.5, 0]],
    a4_6x16: ['A4 · 4 × 16 · 45,7 × 16,9 mm (Avery L7656)', [210, 297], 4, 16, [45.7, 16.9], [13.3, 9.65], [2.5, 0]],
    lp_62x29: ['Etikettendrucker · 62 × 29 mm (Brother DK-11209)', [62, 29], 1, 1, [62, 29], [0, 0], [0, 0]],
    lp_62x100: ['Etikettendrucker · 62 × 100 mm (Brother DK-11202)', [62, 100], 1, 1, [62, 100], [0, 0], [0, 0]],
    lp_54x25: ['Etikettendrucker · 54 × 25 mm (Dymo 11352)', [54, 25], 1, 1, [54, 25], [0, 0], [0, 0]],
    lp_57x32: ['Etikettendrucker · 57 × 32 mm (Dymo 11354)', [57, 32], 1, 1, [57, 32], [0, 0], [0, 0]],
    lp_50x30: ['Etikettendrucker · 50 × 30 mm (Zebra, allgemein)', [50, 30], 1, 1, [50, 30], [0, 0], [0, 0]],
    lp_100x150: ['Versandetikett · 100 × 150 mm (4 × 6 Zoll)', [100, 150], 1, 1, [100, 150], [0, 0], [0, 0]],
};
const LABEL_FORMATS = [['QRCode', 'QR Code'], ['DataMatrix', 'Data Matrix'], ['Code128', 'Code 128'], ['EAN13', 'EAN-13'], ['EAN8', 'EAN-8'], ['Code39', 'Code 39'], ['ITF14', 'ITF-14'], ['GS1-128', 'GS1-128'], ['Aztec', 'Aztec']];

/** Einträge aus der Datenquelle: [{ code, label }] */
function entries(cfg) {
    if (cfg.source === 'serial') {
        const out = [];
        const n = Math.max(1, Math.min(5000, +cfg.count || 1));
        const start = +cfg.start || 0;
        const digits = Math.max(0, Math.min(18, +cfg.digits || 0));
        for (let i = 0; i < n; i++) {
            const num = String(start + i).padStart(digits, '0');
            const code = (cfg.prefix || '') + num + (cfg.suffix || '');
            out.push({ code, label: code });
        }
        return out;
    }
    if (cfg.source === 'same') {
        const n = Math.max(1, Math.min(5000, +cfg.count || 1));
        return Array.from({ length: n }, () => ({ code: cfg.same || '', label: cfg.sameLabel || cfg.same || '' })).filter(e => e.code);
    }
    return String(cfg.list || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean).slice(0, 5000).map(l => {
        const m = /^("?)(.*?)\1\s*[;\t]\s*(.*)$/.exec(l);
        if (m && !/^https?:/.test(l)) return { code: m[2], label: m[3].replace(/^"|"$/g, '') };
        return { code: l, label: l };
    });
}

async function labelSvg(entry, cfg, w, h, cache) {
    const pad = Math.min(w, h) * 0.08;
    const linear = CF.isLinear(cfg.format);
    const showText = cfg.text !== 'none' && (entry.label || '').length;
    const side = !linear && w / h > 1.6;
    let g = cache.get(entry.code);
    if (!g) {
        g = await CF.generate(entry.code, cfg.format, { margin: linear ? 0 : 1, ecl: 'M', dots: 'square', eyeFrame: 'square', eyeBall: 'square', fg: '#000000', bg: '#ffffff', transparent: true, frame: null, logo: null, barFg: '#000000', barBg: '#ffffff', barHeight: 40, barText: cfg.text === 'hrt', barTextOnly: false });
        if (cache.size < 300) cache.set(entry.code, g);
    }
    let body = '';
    const fs = Math.max(2, Math.min(h * 0.13, 5));
    if (side) {
        const s = h - 2 * pad;
        body += place(g.svg, pad, pad, s, s);
        if (showText) {
            const tx = pad * 2 + s, tw = w - tx - pad;
            const lines = wrap(entry.label, Math.max(6, Math.floor(tw / (fs * 0.55))), 4);
            const y0 = h / 2 - (lines.length - 1) * fs * 0.6 + fs * 0.35;
            lines.forEach((l, i) => { body += txt(tx, y0 + i * fs * 1.2, l, fs, { weight: i === 0 ? 700 : 400, max: tw }); });
        }
    } else {
        const th = showText && cfg.text !== 'hrt' ? fs * 1.6 : 0;
        const ah = h - 2 * pad - th, aw = w - 2 * pad;
        if (linear) body += place(g.svg, pad, pad, aw, ah);
        else { const s = Math.min(ah, aw); body += place(g.svg, (w - s) / 2, pad, s, s); }
        if (th) body += txt(w / 2, h - pad - fs * 0.3, entry.label, fs, { anchor: 'middle', weight: 600, max: w - 2 * pad });
    }
    return body;
}
function wrap(s, n, max) {
    const out = [];
    let cur = '';
    for (const word of String(s).split(/\s+/)) {
        if ((cur + ' ' + word).trim().length > n && cur) { out.push(cur); cur = word; } else cur = (cur + ' ' + word).trim();
        if (out.length >= max) break;
    }
    if (cur && out.length < max) out.push(cur);
    return out.map(l => (l.length > n ? l.slice(0, n - 1) + '…' : l));
}

async function buildSheets(cfg, onProgress) {
    const sh = cfg.sheet === 'custom' ? ['', [+cfg.pw || 210, +cfg.ph || 297], +cfg.cols || 3, +cfg.rows || 8, [+cfg.lw || 70, +cfg.lh || 37], [+cfg.mt || 0, +cfg.ml || 0], [+cfg.gx || 0, +cfg.gy || 0]] : SHEETS[cfg.sheet];
    const [, [pw, ph], cols, rows, [lw, lh], [mt, ml], [gx, gy]] = sh;
    const list = entries(cfg);
    const per = cols * rows;
    const skip = Math.max(0, Math.min(per - 1, (+cfg.skip || 1) - 1));
    const pages = [];
    const singles = [];
    const cache = new Map();
    let slot = skip, body = '';
    for (let i = 0; i < list.length; i++) {
        const c = slot % cols, r = Math.floor(slot / cols) % rows;
        const x = ml + c * (lw + gx), y = mt + r * (lh + gy);
        let inner;
        try { inner = await labelSvg(list[i], cfg, lw, lh, cache); } catch (e) { inner = txt(lw / 2, lh / 2, '⚠ ' + list[i].code.slice(0, 20), 3, { anchor: 'middle', color: '#dc2626' }); }
        if (cfg.cutLines) inner += '<rect x=".1" y=".1" width="' + (lw - 0.2) + '" height="' + (lh - 0.2) + '" rx="1.5" fill="none" stroke="#cbd5e1" stroke-width=".2"/>';
        body += '<g transform="translate(' + x + ' ' + y + ')">' + inner + '</g>';
        singles.push(page(lw, lh, '<rect width="' + lw + '" height="' + lh + '" fill="#fff"/>' + inner));
        slot++;
        if (slot === per || i === list.length - 1) { pages.push(page(pw, ph, body)); body = ''; slot = 0; }
        if (onProgress && i % 20 === 0) { onProgress(i + 1, list.length); await CF.sleep(0); }
    }
    return { pages, singles, size: [pw, ph], labels: list.length, per, page: pw === 210 && ph === 297 ? 'A4' : pw + 'mm ' + ph + 'mm' };
}

function labelsPanel() {
    const cfg = Object.assign({ source: 'list', list: '4006381333931;Artikel A\n9783161484100;Buch B\nhttps://example.org/inventar/0815;Laptop 0815', prefix: 'INV-', start: 1, count: 24, digits: 4, suffix: '', same: '', sameLabel: '',
        format: 'QRCode', sheet: 'a4_3x8', text: 'label', skip: 1, cutLines: true, pw: 210, ph: 297, cols: 3, rows: 8, lw: 70, lh: 37, mt: 0, ml: 0, gx: 0, gy: 0 }, CF.ls.get('labels', {}));
    const save = () => CF.ls.set('labels', cfg);
    const srcBox = el('div', { class: 'stack' });
    const customBox = el('div');
    const prevBox = el('div', { class: 'stack' });
    const info = el('div', { class: 'small muted' });
    let built = null, token = 0;
    const num = (k, label, hint) => { const i = input({ type: 'number', value: cfg[k], step: 'any' }); i.addEventListener('input', () => { cfg[k] = i.value; save(); update(); }); return field(T(label), i, hint ? T(hint) : null); };
    const txtIn = (k, label, hint) => { const i = input({ value: cfg[k] }); i.addEventListener('input', () => { cfg[k] = i.value; save(); update(); }); return field(T(label), i, hint ? T(hint) : null); };

    function renderSource() {
        if (cfg.source === 'list') {
            const ta = el('textarea', { class: 'in mono', rows: 7 });
            ta.value = cfg.list;
            ta.addEventListener('input', () => { cfg.list = ta.value; save(); update(); });
            fill(srcBox, field(T('Ein Eintrag pro Zeile'), ta, T('optional mit ; danach der Text fürs Etikett')),
                el('div', { class: 'row' }, btn(T('CSV-Datei laden'), 'btn-ghost btn-sm', async () => { const f = await CF.pickFile('.csv,.txt,text/csv,text/plain'); if (f) { cfg.list = (await f.text()).replace(/^﻿/, ''); save(); renderSource(); update(); } }, 'upload'),
                    btn(T('Aus dem Verlauf'), 'btn-ghost btn-sm', () => { cfg.list = CF.Store.all('h').slice(0, 200).map(h => h.data.t.replace(/[\r\n]+/g, ' ')).join('\n'); save(); renderSource(); update(); }, 'history')));
        } else if (cfg.source === 'serial') {
            fill(srcBox, el('div', { class: 'grid2' }, txtIn('prefix', 'Präfix'), txtIn('suffix', 'Suffix'), num('start', 'Startnummer'), num('count', 'Anzahl'), num('digits', 'Stellen', 'mit führenden Nullen')));
        } else {
            fill(srcBox, txtIn('same', 'Inhalt'), el('div', { class: 'grid2' }, txtIn('sameLabel', 'Text auf dem Etikett'), num('count', 'Anzahl')));
        }
    }
    function renderCustom() {
        fill(customBox, cfg.sheet === 'custom' ? el('div', { class: 'grid3' }, num('pw', 'Seite Breite (mm)'), num('ph', 'Seite Höhe (mm)'), num('cols', 'Spalten'), num('rows', 'Zeilen'), num('lw', 'Etikett Breite (mm)'), num('lh', 'Etikett Höhe (mm)'),
            num('mt', 'Rand oben (mm)'), num('ml', 'Rand links (mm)'), num('gx', 'Abstand waagerecht (mm)'), num('gy', 'Abstand senkrecht (mm)')) : null);
    }

    const update = CF.debounce(async () => {
        const my = ++token;
        fill(info, el('span', { class: 'spinner' }));
        try {
            built = await buildSheets(cfg, (i, n) => { if (my === token) info.textContent = T('Erzeuge Etikett %s von %s …', i, n); });
        } catch (e) { built = null; fill(info, CF.msg('warn', e.message)); return; }
        if (my !== token) return;
        info.textContent = T('%s Etiketten auf %s Seite(n)', built.labels, built.pages.length);
        fill(prevBox, built.pages.slice(0, 3).map(s => { const d = el('div', { class: 'sheet-prev', html: s }); const v = d.querySelector('svg'); v.removeAttribute('width'); v.removeAttribute('height'); return d; }),
            built.pages.length > 3 ? el('div', { class: 'small muted', text: T('… und %s weitere Seite(n)', built.pages.length - 3) }) : null);
    }, 350);

    const srcSeg = CF.seg([['list', T('Liste'), 'list'], ['serial', T('Seriennummern'), 'hash'], ['same', T('Gleicher Code'), 'copy']], cfg.source, v => { cfg.source = v; save(); renderSource(); update(); }, 'full');
    const sheetSel = select(Object.entries(SHEETS).map(([k, s]) => [k, T(s[0])]).concat([['custom', T('Eigenes Raster …')]]), cfg.sheet, v => { cfg.sheet = v; save(); renderCustom(); update(); });
    const fmtSel = select(LABEL_FORMATS.map(([k, n]) => [k, n]), cfg.format, v => { cfg.format = v; save(); update(); });
    const textSel = select([['label', T('Text unter dem Code')], ['hrt', T('Klarschrift (nur Strichcodes)')], ['none', T('Kein Text')]], cfg.text, v => { cfg.text = v; save(); update(); });

    renderSource(); renderCustom(); update();
    const zipBtn = (kind) => btn(kind === 'svg' ? T('ZIP (SVG)') : T('ZIP (PNG, 300 dpi)'), 'btn-second btn-sm', async (e) => {
        if (!built) return;
        const b = e.currentTarget; b.disabled = true;
        const names = built.singles.map((_, i) => 'etikett-' + String(i + 1).padStart(4, '0'));
        let blob;
        if (kind === 'svg') blob = zip(built.singles.map((s, i) => ({ name: names[i] + '.svg', data: new TextEncoder().encode(s) })));
        else blob = await svgsToPngZip(built.singles, names, 300, (i, n) => { info.textContent = T('PNG %s von %s …', i, n); });
        CF.download('etiketten-' + CF.fileStamp() + '.zip', blob);
        info.textContent = T('%s Etiketten auf %s Seite(n)', built.labels, built.pages.length);
        b.disabled = false;
    }, 'download');

    return el('div', { class: 'layout' },
        el('div', { class: 'stack lg' },
            CF.card(T('Inhalt'), 'list', el('div', { class: 'stack' }, srcSeg, srcBox)),
            CF.card(T('Etikett'), 'settings', el('div', { class: 'stack' },
                el('div', { class: 'grid2' }, field(T('Format'), fmtSel), field(T('Text'), textSel)),
                field(T('Bogen oder Etikettendrucker'), sheetSel), customBox,
                el('div', { class: 'grid2' }, num('skip', 'Beginnen bei Etikett', 'angebrochene Bögen weiter nutzen'), el('div', { style: { alignSelf: 'end' } }, CF.toggle(T('Schnittkanten zeigen'), !!cfg.cutLines, v => { cfg.cutLines = v; save(); update(); })))))),
        el('div', { class: 'sticky stack lg' }, CF.card(T('Vorschau'), 'eye', el('div', { class: 'stack' }, info, prevBox,
            el('div', { class: 'row' }, btn(T('Drucken / PDF'), 'btn-primary', () => { if (built) printPages(built.pages, built.page); }, 'printer'), zipBtn('svg'), zipBtn('png')),
            el('div', { class: 'small muted', text: T('Tipp: Im Druckdialog „Ränder: Keine“ und „Skalierung: 100 %“ wählen, sonst verrutschen die Etiketten. Erst auf normalem Papier probedrucken und gegen den Bogen halten.') })))));
}

CF.views.print = {
    title: 'Drucken', icon: 'printer',
    mount(root, params) {
        let tab = (params && params.tab) || CF.ls.get('printTab', 'tpl');
        const body = el('div');
        const draw = () => { CF.ls.set('printTab', tab); fill(body, tab === 'tpl' ? templatesPanel() : labelsPanel()); };
        fill(root, CF.viewHead(T('Drucken'), T('Fertige Druckvorlagen wie WLAN-Aufsteller, Visitenkarte und Spendenschild. Dazu Etikettenbögen mit Seriennummern, Listen und für Etikettendrucker.'),
            CF.seg([['tpl', T('Vorlagen'), 'sparkles'], ['labels', T('Etiketten'), 'grid']], tab, v => { tab = v; draw(); })), body);
        draw();
    },
};
})();
