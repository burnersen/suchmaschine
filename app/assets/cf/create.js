/* Suchmaschine (aus CodeForge) – QR-Studio: Vorlagen, alle Formate, QR-Designer, Lesetest mit Druckgrößen-Empfehlung, Export.
   Neu hier: 2FA-Schlüssel und dynamische QR-Codes über die eigenen Kurzlinks (Ziel später änderbar). */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

/* ---------- Formate ---------- */

const MATRIX = {
    QRCode: { name: 'QR Code', own: true },
    MicroQRCode: { name: 'Micro QR', zx: 'MicroQRCode', hint: 'sehr kleine Codes, kurze Inhalte' },
    RMQRCode: { name: 'rMQR (rechteckig)', zx: 'rMQRCode', hint: 'schmale Flächen' },
    DataMatrix: { name: 'Data Matrix', zx: 'DataMatrix', hint: 'Industrie, Pharma, Post' },
    Aztec: { name: 'Aztec', zx: 'Aztec', hint: 'Tickets' },
    PDF417: { name: 'PDF417', zx: 'PDF417', hint: 'Ausweise, Bordkarten', wide: true },
};
const FORMAT_GROUPS = [
    { group: '2D-Codes', items: Object.entries(MATRIX).map(([k, v]) => [k, v.name]) },
    { group: 'Strichcodes (1D)', items: Object.entries(CF.Bars.FORMATS).map(([k, v]) => [k, v.name]) },
];
CF.isLinear = f => !!CF.Bars.FORMATS[f];
CF.isMatrix = f => !!MATRIX[f];
CF.MATRIX = MATRIX;

/* ---------- Vorlagen: Formular → Inhalt ---------- */

const esc = s => String(s || '').replace(/([\\;,:"])/g, '\\$1');
const vesc = s => String(s || '').replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/([,;])/g, '\\$1');
const icsDate = (d, t) => (d ? d.replace(/-/g, '') + (t ? 'T' + t.replace(':', '') + '00' : '') : '');

const TEMPLATES = {
    text: { name: 'Text', icon: 'text', fields: [['t', 'Text', 'area']], build: v => v.t || '' },
    url: { name: 'Link', icon: 'link', fields: [['u', 'Adresse', 'url', 'https://']], build: v => { const u = (v.u || '').trim(); return u && !/^[a-z][a-z0-9+.\-]*:/i.test(u) ? 'https://' + u : u; } },
    wifi: { name: 'WLAN', icon: 'wifi', fields: [['s', 'Netzwerkname (SSID)'], ['p', 'Passwort', 'password'], ['e', 'Verschlüsselung', 'select', [['WPA', 'WPA/WPA2/WPA3'], ['WEP', 'WEP (alt)'], ['nopass', 'Keine']]], ['h', 'Verstecktes Netzwerk', 'check']],
        build: v => v.s ? 'WIFI:T:' + (v.e || 'WPA') + ';S:' + esc(v.s) + ';' + (v.e !== 'nopass' && v.p ? 'P:' + esc(v.p) + ';' : '') + (v.h ? 'H:true;' : '') + ';' : '' },
    vcard: { name: 'Kontakt', icon: 'user', fields: [['fn', 'Vorname'], ['ln', 'Nachname'], ['org', 'Firma'], ['title', 'Funktion'], ['tel', 'Telefon', 'tel'], ['mob', 'Mobil', 'tel'], ['mail', 'E-Mail', 'email'], ['web', 'Webseite', 'url'], ['street', 'Straße'], ['zip', 'PLZ'], ['city', 'Ort'], ['country', 'Land'], ['note', 'Notiz', 'area']],
        build: v => {
            if (!v.fn && !v.ln && !v.org) return '';
            const L = ['BEGIN:VCARD', 'VERSION:3.0', 'N:' + vesc(v.ln) + ';' + vesc(v.fn) + ';;;', 'FN:' + vesc([v.fn, v.ln].filter(Boolean).join(' ') || v.org)];
            if (v.org) L.push('ORG:' + vesc(v.org));
            if (v.title) L.push('TITLE:' + vesc(v.title));
            if (v.tel) L.push('TEL;TYPE=WORK,VOICE:' + v.tel);
            if (v.mob) L.push('TEL;TYPE=CELL:' + v.mob);
            if (v.mail) L.push('EMAIL:' + v.mail);
            if (v.web) L.push('URL:' + v.web);
            if (v.street || v.city) L.push('ADR;TYPE=WORK:;;' + vesc(v.street) + ';' + vesc(v.city) + ';;' + vesc(v.zip) + ';' + vesc(v.country));
            if (v.note) L.push('NOTE:' + vesc(v.note));
            L.push('END:VCARD');
            return L.join('\r\n');
        } },
    tel: { name: 'Telefon', icon: 'phone', fields: [['n', 'Telefonnummer', 'tel', '+49 …']], build: v => v.n ? 'tel:' + v.n.replace(/[^\d+]/g, '') : '' },
    sms: { name: 'SMS', icon: 'sms', fields: [['n', 'Telefonnummer', 'tel'], ['m', 'Nachricht', 'area']], build: v => v.n ? 'SMSTO:' + v.n.replace(/[^\d+]/g, '') + ':' + (v.m || '') : '' },
    email: { name: 'E-Mail', icon: 'mail', fields: [['to', 'An', 'email'], ['sub', 'Betreff'], ['body', 'Text', 'area']],
        build: v => v.to ? 'mailto:' + v.to + ((v.sub || v.body) ? '?' + [v.sub ? 'subject=' + encodeURIComponent(v.sub) : '', v.body ? 'body=' + encodeURIComponent(v.body) : ''].filter(Boolean).join('&') : '') : '' },
    event: { name: 'Termin', icon: 'calendar', fields: [['title', 'Titel'], ['d1', 'Beginn (Datum)', 'date'], ['t1', 'Uhrzeit', 'time'], ['d2', 'Ende (Datum)', 'date'], ['t2', 'Uhrzeit', 'time'], ['loc', 'Ort'], ['desc', 'Beschreibung', 'area']],
        build: v => {
            if (!v.title || !v.d1) return '';
            const L = ['BEGIN:VEVENT', 'SUMMARY:' + vesc(v.title), 'DTSTART' + (v.t1 ? '' : ';VALUE=DATE') + ':' + icsDate(v.d1, v.t1)];
            if (v.d2 || v.t2) L.push('DTEND' + (v.t2 ? '' : ';VALUE=DATE') + ':' + icsDate(v.d2 || v.d1, v.t2));
            if (v.loc) L.push('LOCATION:' + vesc(v.loc));
            if (v.desc) L.push('DESCRIPTION:' + vesc(v.desc));
            L.push('END:VEVENT');
            return L.join('\r\n');
        } },
    geo: { name: 'Ort', icon: 'pin', fields: [['lat', 'Breitengrad', 'num', '52.5200'], ['lon', 'Längengrad', 'num', '13.4050'], ['q', 'Bezeichnung (optional)']],
        build: v => (v.lat && v.lon && isFinite(+v.lat) && isFinite(+v.lon)) ? 'geo:' + (+v.lat) + ',' + (+v.lon) + (v.q ? '?q=' + encodeURIComponent(v.q) : '') : '' },
    girocode: { name: 'GiroCode', icon: 'bank', fields: [['name', 'Empfänger'], ['iban', 'IBAN', 'text', 'DE…'], ['bic', 'BIC (optional)'], ['amount', 'Betrag in Euro (optional)', 'num'], ['purpose', 'Verwendungszweck']],
        build: v => {
            if (!v.name || !v.iban) return '';
            const amt = v.amount ? (+String(v.amount).replace(',', '.')).toFixed(2) : '';
            return ['BCD', '002', '1', 'SCT', (v.bic || '').toUpperCase().replace(/\s/g, ''), v.name.slice(0, 70), CF.Num.ibanClean(v.iban), amt && +amt > 0 ? 'EUR' + amt : '', '', '', (v.purpose || '').slice(0, 140), ''].join('\n').replace(/\n+$/, '');
        },
        check: v => (v.iban && !CF.Num.ibanValid(v.iban) ? 'Die IBAN ist ungültig (Prüfsumme falsch).' : '') },
    dyn: { name: 'Dynamisch', icon: 'refresh', fields: [], build: v => v.short || '' },
    otp: { name: '2FA-Schlüssel', icon: 'key', fields: [['issuer', 'Dienst (z. B. Nextcloud)'], ['account', 'Konto (z. B. name@example.org)'], ['secret', 'Geheimer Schlüssel (Base32)', 'text', 'JBSWY3DPEHPK3PXP'],
        ['digits', 'Stellen', 'select', [['6', '6'], ['8', '8']]], ['period', 'Gültig (Sekunden)', 'select', [['30', '30'], ['60', '60']]]],
        build: v => {
            const sec = String(v.secret || '').toUpperCase().replace(/[\s=]/g, '');
            if (!sec || !v.account) return '';
            const label = encodeURIComponent((v.issuer ? v.issuer + ':' : '') + v.account);
            return 'otpauth://totp/' + label + '?secret=' + sec + (v.issuer ? '&issuer=' + encodeURIComponent(v.issuer) : '') + (v.digits && v.digits !== '6' ? '&digits=' + v.digits : '') + (v.period && v.period !== '30' ? '&period=' + v.period : '');
        },
        check: v => (v.secret && !/^[A-Z2-7]+$/.test(String(v.secret).toUpperCase().replace(/[\s=]/g, '')) ? 'Der Schlüssel darf nur A bis Z und 2 bis 7 enthalten (Base32).' : '') },
    gs1: { name: 'Produkt (GS1)', icon: 'box', fields: [['gtin', 'GTIN / EAN', 'num'], ['lot', 'Charge (optional)'], ['exp', 'Verfallsdatum (optional)', 'date'], ['ser', 'Seriennummer (optional)'], ['mode', 'Art', 'select', [['dl', 'GS1 Digital Link (QR)'], ['hri', 'GS1-Elemente (DataMatrix, GS1-128)']]]],
        build: v => {
            let g = String(v.gtin || '').replace(/\D/g, '');
            if (!g) return '';
            if (g.length === 7 || g.length === 11 || g.length === 12 || g.length === 13) g = g.length === 13 && CF.Num.gtinValid(g) ? g : (g.length < 13 && !CF.Num.gtinValid(g) ? CF.Num.gtinComplete(g) : g);
            g = g.padStart(14, '0');
            const exp = v.exp ? v.exp.slice(2).replace(/-/g, '') : '';
            if (v.mode === 'hri') return '(01)' + g + (exp ? '(17)' + exp : '') + (v.lot ? '(10)' + v.lot : '') + (v.ser ? '(21)' + v.ser : '');
            return 'https://id.gs1.org/01/' + g + (v.lot ? '/10/' + encodeURIComponent(v.lot) : '') + (v.ser ? '/21/' + encodeURIComponent(v.ser) : '') + (exp ? '?17=' + exp : '');
        } },
};

/* ---------- Design-Vorgaben ---------- */

const PRESETS = [
    { id: 'classic', name: 'Klassisch', st: { fg: '#0f172a', bg: '#ffffff', gradient: null, dots: 'square', eyeFrame: 'square', eyeBall: 'square', eyeColor: '' } },
    { id: 'modern', name: 'Modern', st: { fg: '#4f46e5', bg: '#ffffff', gradient: { type: 'linear', c1: '#06b6d4', c2: '#7c3aed', angle: 45 }, dots: 'rounded', eyeFrame: 'rounded', eyeBall: 'rounded', eyeColor: '' } },
    { id: 'dots', name: 'Punkte', st: { fg: '#111827', bg: '#ffffff', gradient: null, dots: 'dots', eyeFrame: 'circle', eyeBall: 'circle', eyeColor: '#e11d48' } },
    { id: 'leaf', name: 'Blatt', st: { fg: '#065f46', bg: '#ecfdf5', gradient: { type: 'radial', c1: '#059669', c2: '#064e3b' }, dots: 'classy', eyeFrame: 'leaf', eyeBall: 'leaf', eyeColor: '' } },
    { id: 'neon', name: 'Neon', st: { fg: '#22d3ee', bg: '#0b1020', gradient: { type: 'linear', c1: '#22d3ee', c2: '#e879f9', angle: 135 }, dots: 'dots', eyeFrame: 'rounded', eyeBall: 'circle', eyeColor: '' } },
    { id: 'sunset', name: 'Sunset', st: { fg: '#ea580c', bg: '#fff7ed', gradient: { type: 'linear', c1: '#f43f5e', c2: '#f59e0b', angle: 90 }, dots: 'diamond', eyeFrame: 'rounded', eyeBall: 'diamond', eyeColor: '' } },
    { id: 'mono', name: 'Minimal', st: { fg: '#000000', bg: '#ffffff', gradient: null, dots: 'pixel', eyeFrame: 'dots', eyeBall: 'square', eyeColor: '' } },
];
const DOT_STYLES = [['square', 'Eckig'], ['rounded', 'Fließend'], ['dots', 'Punkte'], ['classy', 'Elegant'], ['diamond', 'Raute'], ['pixel', 'Pixel']];
const EYE_FRAMES = [['square', 'Eckig'], ['rounded', 'Rund'], ['circle', 'Kreis'], ['leaf', 'Blatt'], ['dots', 'Punkte']];
const EYE_BALLS = [['square', 'Eckig'], ['rounded', 'Rund'], ['circle', 'Kreis'], ['leaf', 'Blatt'], ['diamond', 'Raute']];

/** Vorschau für die Eckformen: ein einzelnes Suchmuster (7×7) */
function styleThumb(kind, v) {
    const st = { margin: 0, fg: 'currentColor', transparent: true, eyeFrame: kind === 'frame' ? v : 'square', eyeBall: kind === 'ball' ? v : 'square' };
    return CF.Render.qr({ size: 7, modules: new Uint8Array(49) }, st).svg;
}
function miniDots(v) {
    const pat = [1, 1, 0, 1, 1, 0, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 1, 0, 1, 1, 1, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 1, 1, 0, 1, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 1, 1, 0, 1];
    // QR-Renderer mit einem 21er-Feld, in dessen Mitte das Muster liegt (Suchmuster werden ausgeblendet)
    const n = 21, m = new Uint8Array(n * n);
    for (let y = 0; y < 7; y++) for (let x = 0; x < 7; x++) m[(y + 7) * n + x + 7] = pat[y * 7 + x];
    const svg = CF.Render.qr({ size: n, modules: m }, { margin: 0, fg: 'currentColor', transparent: true, dots: v, eyeFrame: 'square', eyeBall: 'square' }).svg;
    return svg.replace(/viewBox="0 0 21 21"/, 'viewBox="7 7 7 7"').replace(/<path d="[^"]*" fill="currentColor" fill-rule="evenodd"\/><path d="[^"]*" fill="currentColor"\/>/, '');
}

/* ---------- Erzeugen: Inhalt + Format + Design → SVG ---------- */

/**
 * Liefert { svg, W, H, info, kind, qr?, symbol?, bars? } oder wirft einen Fehler mit verständlicher Meldung
 */
async function generate(text, format, design) {
    if (format === 'QRCode') {
        const st = Object.assign({}, design);
        let ecl = st.ecl || 'M';
        if (st.logo && st.logo.src && (ecl === 'L' || ecl === 'M')) ecl = 'H';
        const q = CF.QR.encode(text, { ecl, boost: true, eci: !!st.eci });
        const r = CF.Render.qr(q, st);
        return { svg: r.svg, W: r.W, H: r.H, kind: 'qr', qr: q, modules: q.size, quiet: st.margin,
            info: T('Version %s', q.version) + ' · ' + q.size + '×' + q.size + ' · ' + T('Fehlerkorrektur %s', q.ecl) + ' (' + CF.QR.recovery[q.ecl] + ' %) · ' + Math.ceil(q.usedBits / 8) + '/' + (q.capacityBits / 8) + ' Bytes' };
    }
    if (MATRIX[format]) {
        const m = MATRIX[format];
        let opts = '';
        let payload = text;
        if (CF.GS1.isHri(text) && (format === 'DataMatrix' || format === 'QRCode' || format === 'Aztec')) opts = 'gs1';
        if (format === 'DataMatrix' && !design.dmRect) opts += (opts ? ',' : '') + 'forceSquare';
        if (format !== 'PDF417' && format !== 'Aztec' && design.ecl && (format === 'MicroQRCode' || format === 'RMQRCode')) opts += (opts ? ',' : '') + 'ecLevel=' + (format === 'RMQRCode' && design.ecl === 'L' ? 'M' : format === 'RMQRCode' && design.ecl === 'Q' ? 'H' : design.ecl);
        let sym;
        try { sym = await CF.Engine.write(payload, m.zx, opts); } catch (e) { throw new Error(T('Passt nicht in dieses Format oder enthält unzulässige Zeichen.') + (/too long|Input too long|requires/i.test(e.message) ? ' ' + T('Der Inhalt ist zu lang.') : '')); }
        if (format === 'PDF417') sym.wide = true;
        const st = Object.assign({}, design, { logo: null, eyeFrame: 'square', eyeBall: 'square', margin: design.margin == null ? 2 : Math.min(design.margin, 4) });
        if (format === 'PDF417') st.dots = 'square';
        const r = CF.Render.matrix(sym, st);
        return { svg: r.svg, W: r.W, H: r.H, kind: 'matrix', symbol: sym, modules: Math.max(sym.width, sym.height), quiet: st.margin, info: m.name + ' · ' + sym.width + '×' + (format === 'PDF417' ? sym.height / 3 + ' ' + T('Zeilen') : sym.height) };
    }
    const code = CF.Bars.encode(format, text);
    const r = CF.Render.linear(code, { fg: design.barFg || '#000000', bg: design.transparent ? 'transparent' : (design.barBg || '#ffffff'), transparent: !!design.transparent, height: design.barHeight || 55, text: design.barText !== false });
    return { svg: r.svg, W: r.W, H: r.H, kind: 'linear', bars: code, modules: code.modules.length, info: CF.Bars.FORMATS[format].name + ' · ' + code.modules.length + ' ' + T('Module') + (code.value !== text ? ' · ' + T('Prüfziffer ergänzt: %s', code.value) : '') };
}
CF.generate = generate;

/* ---------- Lesetest: Code gezielt verschlechtern und prüfen, ob er noch lesbar ist ---------- */

function noise(ctx, w, h, amount) {
    const d = ctx.getImageData(0, 0, w, h);
    for (let i = 0; i < d.data.length; i += 4) {
        const n = (Math.random() - 0.5) * amount;
        d.data[i] += n; d.data[i + 1] += n; d.data[i + 2] += n;
    }
    ctx.putImageData(d, 0, 0);
}

async function readTest(gen, text, format) {
    const linear = gen.kind === 'linear';
    // Grundbild: 6 Pixel pro Modul, Code in eine helle Fläche gesetzt (wie ein Ausdruck)
    const ppm = linear ? 3 : 6;
    const base = await CF.Render.svgToCanvas(gen.svg, gen.W * ppm, gen.H * ppm);
    const pad = Math.round(Math.max(base.width, base.height) * 0.15);
    const mk = (scale, fn) => {
        const w = Math.max(8, Math.round((base.width + pad * 2) * scale)), h = Math.max(8, Math.round((base.height + pad * 2) * scale));
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        const g = c.getContext('2d', { willReadFrequently: true });
        g.fillStyle = '#f2f2ee';
        g.fillRect(0, 0, w, h);
        g.imageSmoothingEnabled = true;
        fn(g, w, h, scale);
        return c;
    };
    const plain = (g, w, h, s) => g.drawImage(base, pad * s, pad * s, base.width * s, base.height * s);
    const tests = [
        { id: 'clean', label: 'Original', c: () => mk(linear ? 0.7 : 0.6, plain) },
        { id: 'small', label: 'Klein gedruckt', c: () => mk((linear ? 1.7 : 2.2) / ppm, plain) },
        { id: 'tiny', label: 'Winzig', c: () => mk((linear ? 1.25 : 1.5) / ppm, plain) },
        { id: 'blur', label: 'Unscharf', c: () => {
            const s = (linear ? 2 : 3) / ppm;
            const sharp = mk(s, plain);
            const out = document.createElement('canvas');
            out.width = sharp.width; out.height = sharp.height;
            const g = out.getContext('2d', { willReadFrequently: true });
            if ('filter' in g) { g.filter = 'blur(' + (linear ? 1.1 : 1.6) + 'px)'; g.drawImage(sharp, 0, 0); g.filter = 'none'; }
            else { const t = document.createElement('canvas'); t.width = sharp.width / 3; t.height = sharp.height / 3; t.getContext('2d').drawImage(sharp, 0, 0, t.width, t.height); g.drawImage(t, 0, 0, out.width, out.height); }
            return out;
        } },
        { id: 'tilt', label: 'Schräg', c: () => mk((linear ? 2 : 3) / ppm, (g, w, h, s) => {
            g.translate(w / 2, h / 2); g.rotate((linear ? 12 : 28) * Math.PI / 180); g.transform(1, 0, linear ? 0.12 : 0.22, 1, 0, 0);
            g.drawImage(base, -base.width * s / 2, -base.height * s / 2, base.width * s, base.height * s);
        }) },
        { id: 'dark', label: 'Wenig Licht', c: () => mk((linear ? 2 : 3) / ppm, (g, w, h, s) => {
            plain(g, w, h, s);
            g.fillStyle = 'rgba(70,70,80,.62)'; g.fillRect(0, 0, w, h);
            noise(g, w, h, 34);
        }) },
        { id: 'glare', label: 'Spiegelung', c: () => mk((linear ? 2 : 3) / ppm, (g, w, h, s) => {
            plain(g, w, h, s);
            const gr = g.createRadialGradient(w * 0.62, h * 0.4, 0, w * 0.62, h * 0.4, Math.max(w, h) * 0.3);
            gr.addColorStop(0, 'rgba(255,255,255,.82)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
            g.fillStyle = gr; g.fillRect(0, 0, w, h);
        }) },
        { id: 'noise', label: 'Körnig', c: () => mk((linear ? 2 : 3) / ppm, (g, w, h, s) => { plain(g, w, h, s); noise(g, w, h, 100); }) },
    ];
    const want = CF.Render && format;
    const zxFormat = format === 'QRCode' ? 'QRCode' : format === 'GS1-128' ? 'Code128' : (MATRIX[format] && MATRIX[format].zx === 'rMQRCode' ? 'RMQRCode' : (MATRIX[format] ? MATRIX[format].zx : format));
    const results = [];
    for (const t of tests) {
        let ok = false;
        try {
            const c = t.c();
            const res = await CF.Engine.readCanvas(c, { tryHarder: true, formats: [], symbol: false, max: 4 });
            ok = res.some(r => r.text === text || normalize(r.text, format) === normalize(text, format) || (gen.bars && accepts(gen.bars, r.text)));
        } catch (e) { ok = false; }
        results.push({ id: t.id, label: t.label, ok });
    }
    return results;
}
/** Liest die Engine diesen Strichcode? (UPC-E kommt ausgeschrieben zurück, UPC-A mit führender 0) */
function accepts(code, t) {
    const v = code.value;
    if (t === v) return true;
    if (code.format === 'UPCA' && t === '0' + v) return true;
    if (code.format === 'UPCE') { const a = CF.Num.upcEToA(v); return !!a && (t === a.upca || t === '0' + a.upca); }
    if (code.format === 'Codabar') return t.replace(/^[ABCD]|[ABCD]$/g, '') === v.replace(/^[ABCD]|[ABCD]$/g, '');
    return false;
}
function normalize(s, format) {
    if (format === 'Code39') return s.toUpperCase();
    if (format === 'UPCA' || format === 'UPCE') return s.replace(/^0+/, '');
    return s;
}

function verdict(res, gen, format) {
    const passed = res.filter(r => r.ok).length;
    const score = Math.round(passed / res.length * 100);
    const get = id => (res.find(r => r.id === id) || {}).ok;
    let label, kind;
    if (!get('clean')) { label = T('Nicht lesbar'); kind = 'danger'; }
    else if (score >= 85) { label = T('Sehr robust'); kind = 'ok'; }
    else if (score >= 60) { label = T('Gut lesbar'); kind = 'ok'; }
    else { label = T('Empfindlich'); kind = 'warn'; }
    // Druckgröße: Modulgröße in mm je nach Testergebnis
    let size = '';
    if (get('clean')) {
        if (gen.kind === 'linear') {
            if (/^(EAN13|UPCA)$/.test(format)) size = T('Druckbreite ab ca. %s cm (80 % der GS1-Nenngröße), aus bis zu ca. %s cm lesbar.', '3,0', '30');
            else if (/^(EAN8|UPCE)$/.test(format)) size = T('Druckbreite ab ca. %s cm (80 % der GS1-Nenngröße), aus bis zu ca. %s cm lesbar.', '2,2', '25');
            else {
                const mm = get('tiny') ? 0.25 : get('small') ? 0.33 : 0.4;
                const w = (gen.modules + 20) * mm / 10;
                size = T('Druckbreite ab ca. %s cm, aus bis zu ca. %s cm lesbar.', w.toFixed(1).replace('.', CF.lang === 'en' ? '.' : ','), Math.round(w * 8));
            }
        } else {
            let mm = get('tiny') ? 0.35 : get('small') ? 0.45 : 0.6;
            if (!get('blur')) mm += 0.1;
            if (!get('dark')) mm += 0.1;
            const total = (gen.modules + 2 * Math.max(2, gen.quiet || 4)) * mm / 10;
            const cm = Math.max(1.2, total);
            size = T('Druckgröße ab ca. %s cm Kantenlänge, aus bis zu ca. %s cm lesbar.', cm.toFixed(1).replace('.', CF.lang === 'en' ? '.' : ','), Math.round(cm * 10));
        }
    }
    return { passed, score, label, kind, size };
}

/* ---------- Ansicht: Erstellen ---------- */

const DEFAULT_DESIGN = Object.assign({ ecl: 'M', margin: 4, logo: null, frame: { style: 'none', text: 'SCAN MICH', color: '#0f172a', textColor: '#ffffff' }, transparent: false,
    barFg: '#000000', barBg: '#ffffff', barHeight: 55, barText: true }, PRESETS[0].st);

CF.views.create = {
    title: 'Erstellen', icon: 'wand',
    mount(root, params) {
        params = params || {};
        let tpl = CF.ls.get('createTpl', 'url');
        let values = CF.ls.get('createValues', {});
        let format = CF.ls.get('createFormat', 'QRCode');
        let design = Object.assign({}, DEFAULT_DESIGN, CF.ls.get('design', {}));
        design.logo = null; // Logos nicht dauerhaft speichern (Größe)
        let current = null, testToken = 0;

        if (params.qr && !params.text) params.text = params.qr;
        if (params.text) {
            tpl = 'text';
            values = Object.assign({}, values, { text: { t: params.text } });
            if (params.format && (MATRIX[params.format] || CF.Bars.FORMATS[params.format])) format = params.format;
            else if (params.format === 'EANUPC' || params.format === 'ISBN') format = 'EAN13';
            else if (params.format === 'Code39Std' || params.format === 'PZN') format = 'Code39';
            else if (params.format && !MATRIX[params.format] && !CF.Bars.FORMATS[params.format]) format = 'QRCode';
        }
        if (params.format && !params.text && (MATRIX[params.format] || CF.Bars.FORMATS[params.format])) format = params.format;
        if (params.tpl && TEMPLATES[params.tpl]) {
            tpl = params.tpl;
            values = Object.assign({}, values);
            values[tpl] = Object.assign({}, values[tpl] || {}, params.values || {});
            if (CF.isLinear(format)) format = 'QRCode';
        }

        const tplBox = el('div', { class: 'tpls' });
        const formBox = el('div', { class: 'stack' });
        const fmtSel = select(FORMAT_GROUPS.map(g => ({ group: T(g.group), items: g.items })), format, v => { format = v; CF.ls.set('createFormat', v); renderAll(); });
        const fmtHint = el('div', { class: 'tiny faint' });
        const designBox = el('div', { class: 'stack' });
        const preview = el('div', { class: 'preview' });
        const info = el('div', { class: 'small muted', style: { textAlign: 'center' } });
        const testBox = el('div', { class: 'stack' });
        const exportBox = el('div', { class: 'stack' });

        function content() {
            if (CF.isLinear(format) || (format !== 'QRCode' && tpl === 'text')) return (values.text && values.text.t) || '';
            return TEMPLATES[tpl].build(values[tpl] || {});
        }

        function renderTemplates() {
            const linear = CF.isLinear(format);
            tplBox.hidden = linear;
            fill(tplBox, Object.entries(TEMPLATES).map(([k, t]) => {
                const b = el('button', { type: 'button', class: 'tpl' + (k === tpl && !linear ? ' on' : ''), onclick: () => { tpl = k; CF.ls.set('createTpl', k); renderTemplates(); renderForm(); update(); } });
                b.insertAdjacentHTML('beforeend', CF.icon(t.icon));
                b.append(el('span', { text: T(t.name) }));
                if (linear) b.disabled = true, b.style.opacity = .4;
                return b;
            }));
        }

        function renderForm() {
            const linear = CF.isLinear(format);
            if (linear) {
                const f = CF.Bars.FORMATS[format];
                const v = (values.text && values.text.t) || '';
                const inp = input({ value: v, placeholder: f.example, class: 'in big mono', inputmode: f.numeric ? 'numeric' : 'text', autocomplete: 'off' });
                inp.addEventListener('input', () => { values.text = { t: inp.value }; save(); update(); });
                fill(formBox, field(T('Inhalt'), inp, T(f.hint)),
                    el('div', { class: 'row' }, btn(T('Beispiel'), 'btn-ghost btn-sm', () => { inp.value = f.example; values.text = { t: f.example }; save(); update(); }, 'sparkles')));
                return;
            }
            const t = TEMPLATES[tpl];
            const v = values[tpl] = values[tpl] || {};
            if (tpl === 'dyn') { fill(formBox, dynForm(v)); return; }
            if (tpl === 'otp') {
                // Geheimen Schlüssel auf Wunsch erzeugen (160 Bit, wie es die meisten Dienste machen)
                setTimeout(() => {
                    const inp = formBox.querySelectorAll('input')[2];
                    if (!inp || inp.dataset.gen) return;
                    inp.dataset.gen = '1';
                    inp.parentNode.append(el('div', { class: 'row' }, btn(T('Neuen Schlüssel erzeugen'), 'btn-ghost btn-sm', () => {
                        const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', b = crypto.getRandomValues(new Uint8Array(32));
                        inp.value = Array.from(b, x => a[x & 31]).join('');
                        v.secret = inp.value; save(); update();
                    }, 'refresh')));
                }, 0);
            }
            const rows = t.fields.map(([key, label, type, extra]) => {
                let inp;
                if (type === 'area') { inp = el('textarea', { class: 'in', rows: tpl === 'text' ? 5 : 3, value: v[key] || '' }); inp.addEventListener('input', () => { v[key] = inp.value; save(); update(); }); }
                else if (type === 'select') { inp = select(extra.map(o => [o[0], T(o[1])]), v[key] || extra[0][0], val => { v[key] = val; save(); update(); }); }
                else if (type === 'check') return CF.toggle(T(label), !!v[key], c => { v[key] = c; save(); update(); });
                else {
                    inp = input({ type: type === 'num' ? 'text' : type === 'password' ? 'text' : (type || 'text'), value: v[key] || '', placeholder: extra || '', inputmode: type === 'num' ? 'decimal' : undefined, autocomplete: 'off', spellcheck: type === 'password' ? 'false' : undefined });
                    inp.addEventListener('input', () => { v[key] = inp.value; save(); update(); });
                }
                return field(T(label), inp);
            });
            const half = tpl === 'vcard' || tpl === 'event';
            fill(formBox, half ? el('div', { class: 'grid2' }, rows) : rows);
        }

        function save() { CF.ls.set('createValues', values); }

        /** Dynamischer QR-Code: zeigt auf einen eigenen Kurzlink, dessen Ziel sich später ändern lässt */
        function dynForm(v) {
            if (!CF.loggedIn() || !(CF.session.settings && CF.session.settings.shortener)) {
                return [CF.msg('info', CF.loggedIn() ? T('Kurzlinks sind ausgeschaltet.') : T('Dynamische QR-Codes laufen über deine Kurzlinks und gehen nach dem Anmelden.'))];
            }
            const url = input({ type: 'url', value: v.url || '', placeholder: 'https://example.org/speisekarte', autocomplete: 'off', spellcheck: 'false' });
            const code = input({ value: v.code || '', placeholder: T('frei lassen = zufällig'), maxlength: 40, autocomplete: 'off', spellcheck: 'false' });
            const out = el('div', { class: 'stack' });
            const show = () => fill(out, v.short ? CF.msg('ok', el('div', {}, el('b', { text: v.short }), el('div', { class: 'small', text: T('Der Code zeigt auf diesen Kurzlink. Das Ziel änderst du jederzeit unter Kurzlinks, der gedruckte Code bleibt gültig.') }),
                el('div', { class: 'row', style: { marginTop: '.4rem' } }, btn(T('Ziel ändern'), 'btn-second btn-sm', () => CF.go('kurzlinks', { edit: v.code }), 'edit'),
                    btn(T('Neuer Kurzlink'), 'btn-ghost btn-sm', () => { v.short = ''; v.code = ''; save(); show(); update(); }, 'plus')))) : null);
            const make = btn(T('Kurzlink anlegen'), 'btn-primary', async () => {
                make.disabled = true;
                try {
                    const r = await CF.api('short_create', { url: url.value, code: code.value, strip: 1 });
                    v.url = url.value; v.code = r.link.code; v.short = r.link.short;
                    save(); show(); update();
                    CF.toast(T('Kurzlink angelegt'));
                } catch (e) { CF.toast(e.message, 'bad', 4000); }
                make.disabled = false;
            }, 'link');
            show();
            return [CF.msg('info', T('Einmal drucken, Ziel später ändern: ideal für Speisekarten, Flyer und Aufkleber.'), 'refresh'),
                field(T('Ziel-Adresse'), url), field(T('Wunschname'), code, T('optional')), el('div', { class: 'row' }, make), out];
        }
        function saveDesign() { const d = Object.assign({}, design, { logo: null }); CF.ls.set('design', d); }

        function styleGrid(list, key, kind) {
            return el('div', { class: 'styles' }, list.map(([v, label]) => {
                const b = el('button', { type: 'button', class: 'sty' + (design[key] === v ? ' on' : ''), title: T(label), onclick: () => { design[key] = v; saveDesign(); renderDesign(); update(); } });
                b.innerHTML = kind === 'dots' ? miniDots(v) : styleThumb(kind, v);
                b.append(el('span', { text: T(label) }));
                return b;
            }));
        }

        function renderDesign() {
            const linear = CF.isLinear(format), qr = format === 'QRCode';
            if (linear) {
                fill(designBox,
                    el('div', { class: 'grid2' }, field(T('Strichfarbe'), CF.colorField(design.barFg, c => { design.barFg = c; saveDesign(); update(); })),
                        field(T('Hintergrund'), CF.colorField(design.barBg, c => { design.barBg = c; saveDesign(); update(); }))),
                    field(T('Höhe'), el('input', { type: 'range', min: 20, max: 120, value: design.barHeight, oninput: e => { design.barHeight = +e.target.value; saveDesign(); update(); } })),
                    CF.toggle(T('Klarschrift unter dem Code'), design.barText !== false, c => { design.barText = c; saveDesign(); update(); }),
                    CF.toggle(T('Transparenter Hintergrund'), !!design.transparent, c => { design.transparent = c; saveDesign(); update(); }));
                return;
            }
            const presets = el('div', { class: 'presets' }, PRESETS.map(p => {
                const sw = el('span', { class: 'sw', style: { background: p.st.gradient ? 'linear-gradient(135deg,' + p.st.gradient.c1 + ',' + p.st.gradient.c2 + ')' : p.st.fg, boxShadow: 'inset 0 0 0 6px ' + p.st.bg } });
                return el('button', { type: 'button', class: 'preset', onclick: () => { Object.assign(design, JSON.parse(JSON.stringify(p.st))); saveDesign(); renderDesign(); update(); } }, sw, el('span', { text: T(p.name) }));
            }));
            const grad = design.gradient || { type: 'linear', c1: '#06b6d4', c2: '#7c3aed', angle: 45 };
            const colors = el('div', { class: 'stack' },
                el('div', { class: 'grid2' },
                    field(T('Vordergrund'), CF.colorField(design.fg, c => { design.fg = c; if (design.gradient) design.gradient = null; saveDesign(); renderDesign(); update(); })),
                    field(T('Hintergrund'), CF.colorField(design.bg, c => { design.bg = c; saveDesign(); update(); }))),
                CF.toggle(T('Farbverlauf'), !!design.gradient, c => { design.gradient = c ? grad : null; saveDesign(); renderDesign(); update(); }),
                design.gradient ? el('div', { class: 'grid3' },
                    field(T('Von'), CF.colorField(grad.c1, c => { design.gradient.c1 = c; saveDesign(); update(); })),
                    field(T('Nach'), CF.colorField(grad.c2, c => { design.gradient.c2 = c; saveDesign(); update(); })),
                    field(T('Art'), select([['linear', T('Linear')], ['radial', T('Kreisförmig')]], grad.type, v => { design.gradient.type = v; saveDesign(); update(); }))) : null,
                CF.toggle(T('Transparenter Hintergrund'), !!design.transparent, c => { design.transparent = c; saveDesign(); update(); }));
            const logoInfo = el('div', { class: 'small muted' });
            const logo = el('div', { class: 'stack' },
                el('div', { class: 'row' },
                    btn(design.logo ? T('Anderes Logo') : T('Logo hochladen'), 'btn-second btn-sm', async () => {
                        const f = await CF.pickFile('image/*');
                        if (!f) return;
                        if (f.size > 1500000) { CF.toast(T('Das Bild ist zu groß (höchstens 1,5 MB).'), 'warn'); return; }
                        design.logo = { src: await CF.readDataUrl(f), size: (design.logo && design.logo.size) || 0.22, pad: 1, plate: true };
                        renderDesign(); update();
                    }, 'image'),
                    design.logo ? btn(T('Entfernen'), 'btn-ghost btn-sm', () => { design.logo = null; renderDesign(); update(); }, 'trash') : null),
                design.logo ? field(T('Logo-Größe'), el('input', { type: 'range', min: 0.12, max: 0.32, step: 0.01, value: design.logo.size, oninput: e => { design.logo.size = +e.target.value; update(); } })) : null,
                design.logo ? CF.toggle(T('Heller Untergrund hinter dem Logo'), design.logo.plate !== false, c => { design.logo.plate = c; update(); }) : null,
                logoInfo);
            if (design.logo) logoInfo.textContent = T('Mit Logo wird automatisch die höchste Fehlerkorrektur (H) genutzt. Der Lesetest zeigt, ob es noch passt.');
            const frame = design.frame || (design.frame = { style: 'none', text: 'SCAN MICH', color: '#0f172a', textColor: '#ffffff' });
            const frameBox = el('div', { class: 'stack' },
                CF.seg([['none', T('Ohne')], ['bottom', T('Unten')], ['top', T('Oben')], ['pill', T('Sprechblase')]], frame.style, v => { frame.style = v; saveDesign(); renderDesign(); update(); }, 'full'),
                frame.style !== 'none' ? el('div', { class: 'grid3' },
                    field(T('Text'), (() => { const i = input({ value: frame.text, maxlength: 40 }); i.addEventListener('input', () => { frame.text = i.value; saveDesign(); update(); }); return i; })()),
                    field(T('Farbe'), CF.colorField(frame.color, c => { frame.color = c; saveDesign(); update(); })),
                    field(T('Schrift'), CF.colorField(frame.textColor, c => { frame.textColor = c; saveDesign(); update(); }))) : null);
            const adv = el('details', { class: 'adv' }, el('summary', {}, T('Feineinstellungen')), el('div', { class: 'stack' },
                field(T('Fehlerkorrektur'), CF.seg([['L', 'L · 7 %'], ['M', 'M · 15 %'], ['Q', 'Q · 25 %'], ['H', 'H · 30 %']], design.ecl, v => { design.ecl = v; saveDesign(); update(); }, 'full'), T('höher = robuster, aber dichter')),
                field(T('Ruhezone (Rand)'), el('input', { type: 'range', min: 0, max: 8, value: design.margin, oninput: e => { design.margin = +e.target.value; saveDesign(); update(); } }), T('mindestens 4 empfohlen')),
                qr ? CF.toggle(T('UTF-8 kennzeichnen (ECI)'), !!design.eci, c => { design.eci = c; saveDesign(); update(); }, T('hilft alten Lesegeräten bei Umlauten')) : null,
                btn(T('Design zurücksetzen'), 'btn-ghost btn-sm', () => { design = Object.assign({}, DEFAULT_DESIGN); saveDesign(); renderDesign(); update(); }, 'refresh')));

            if (qr) {
                fill(designBox,
                    el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Vorlagen') }), presets),
                    el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Punkte') }), styleGrid(DOT_STYLES, 'dots', 'dots')),
                    el('div', { class: 'grid2' },
                        el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Ecken außen') }), styleGrid(EYE_FRAMES, 'eyeFrame', 'frame')),
                        el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Ecken innen') }), styleGrid(EYE_BALLS, 'eyeBall', 'ball'))),
                    field(T('Eigene Farbe für die Ecken'), el('div', { class: 'row nw' }, CF.colorField(design.eyeColor || design.fg, c => { design.eyeColor = c; saveDesign(); update(); }),
                        design.eyeColor ? btn(T('wie Punkte'), 'btn-ghost btn-sm', () => { design.eyeColor = ''; saveDesign(); renderDesign(); update(); }) : null)),
                    colors,
                    el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Logo in der Mitte') }), logo),
                    el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Rahmen mit Text') }), frameBox),
                    adv);
            } else {
                fill(designBox, colors,
                    format !== 'PDF417' ? el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Punkte') }),
                        el('div', { class: 'styles' }, [['square', 'Eckig'], ['rounded', 'Fließend'], ['dots', 'Punkte']].map(([v, l]) => {
                            const b = el('button', { type: 'button', class: 'sty' + (design.dots === v ? ' on' : ''), onclick: () => { design.dots = v; saveDesign(); renderDesign(); update(); } });
                            b.innerHTML = miniDots(v);
                            b.append(el('span', { text: T(l) }));
                            return b;
                        }))) : null,
                    el('div', {}, el('div', { class: 'small muted', style: { marginBottom: '.4rem' }, text: T('Rahmen mit Text') }), frameBox),
                    (format === 'MicroQRCode' || format === 'RMQRCode') ? field(T('Fehlerkorrektur'), CF.seg([['L', 'L'], ['M', 'M'], ['Q', 'Q'], ['H', 'H']], design.ecl, v => { design.ecl = v; saveDesign(); update(); }, 'full')) : null);
            }
        }

        const update = CF.debounce(async () => {
            const text = content();
            const token = ++testToken;
            fmtHint.textContent = MATRIX[format] && MATRIX[format].hint ? T(MATRIX[format].hint) : (CF.Bars.FORMATS[format] ? T(CF.Bars.FORMATS[format].hint) : '');
            preview.classList.toggle('linear', CF.isLinear(format) || format === 'PDF417' || format === 'RMQRCode');
            const t = TEMPLATES[tpl];
            const warn = !CF.isLinear(format) && t.check ? t.check(values[tpl] || {}) : '';
            if (!text) {
                current = null;
                fill(preview, el('div', { class: 'err', style: { color: '#64748b' }, text: T('Gib links einen Inhalt ein, die Vorschau erscheint sofort.') }));
                info.textContent = '';
                fill(testBox); renderExport();
                return;
            }
            try {
                if (MATRIX[format] && !MATRIX[format].own) await CF.Engine.ready();
                current = await generate(text, format, design);
                current.text = text;
                if (token !== testToken) return;
                preview.innerHTML = current.svg;
                info.textContent = current.info;
            } catch (e) {
                current = null;
                const m = e.code === 'too_long' ? T('Der Inhalt ist zu lang für einen QR-Code (höchstens etwa 2.900 Bytes).') : (e.tpl ? T(e.tpl, ...(e.args || [])) : T(e.message || String(e)));
                fill(preview, el('div', { class: 'err', text: m }));
                info.textContent = '';
                fill(testBox); renderExport();
                return;
            }
            renderExport();
            if (warn) fill(testBox, CF.msg('warn', T(warn)));
            else fill(testBox, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Lesetest läuft …')));
            try {
                await CF.Engine.ready();
                const res = await readTest(current, text, format);
                if (token !== testToken) return;
                const v = verdict(res, current, format);
                fill(testBox, warn ? CF.msg('warn', T(warn)) : null,
                    el('div', { class: 'verdict' }, el('div', { class: 'score', text: v.score + ' %' }), el('div', { class: 'grow' }, el('b', { text: v.label }), el('div', { class: 'meter', style: { marginTop: '.35rem' } }, el('span', { style: { width: v.score + '%' } })))),
                    el('div', { class: 'test' }, res.map(r => CF.badge((r.ok ? '✓ ' : '✗ ') + T(r.label), r.ok ? 'ok' : 'danger'))),
                    v.size ? CF.msg(v.kind === 'danger' ? 'danger' : 'info', v.size, 'printer') : null,
                    v.kind === 'danger' ? CF.msg('danger', T('Die Scan-Engine kann diesen Code nicht lesen. Kontrast erhöhen, Logo verkleinern oder ein schlichteres Design wählen.')) : null);
            } catch (e) {
                if (token === testToken) fill(testBox, CF.msg('info', T('Lesetest nicht möglich: Die Scan-Engine ist noch nicht geladen.')));
            }
        }, 250);

        function renderExport() {
            const has = !!current;
            const sizeSel = select([['512', '512 px'], ['1024', '1024 px'], ['2048', '2048 px'], ['4096', '4096 px']], CF.ls.get('pngSize', '1024'), v => CF.ls.set('pngSize', v), { style: { width: 'auto' } });
            const base = (format + '-' + CF.fileStamp()).toLowerCase();
            fill(exportBox,
                el('div', { class: 'row' },
                    btn('PNG', 'btn-primary', async () => {
                        const px = +sizeSel.value;
                        const k = px / Math.max(current.W, current.H);
                        CF.download(base + '.png', await CF.Render.svgToPng(current.svg, current.W * k, current.H * k));
                    }, 'download', { disabled: !has }),
                    sizeSel,
                    btn('SVG', 'btn-second', () => CF.download(base + '.svg', new Blob([current.svg], { type: 'image/svg+xml' })), 'download', { disabled: !has })),
                el('div', { class: 'row' },
                    btn(T('Kopieren'), 'btn-second btn-sm', async () => {
                        try {
                            const k = 1024 / Math.max(current.W, current.H);
                            const blob = await CF.Render.svgToPng(current.svg, current.W * k, current.H * k);
                            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                            CF.toast(T('Bild kopiert'));
                        } catch (e) { CF.toast(T('Kopieren von Bildern unterstützt dieser Browser nicht.'), 'warn'); }
                    }, 'copy', { disabled: !has }),
                    btn(T('Drucken'), 'btn-second btn-sm', () => {
                        let pa = document.getElementById('print-area');
                        if (!pa) { pa = el('div', { id: 'print-area' }); document.body.append(pa); }
                        pa.className = '';
                        const pst = document.getElementById('print-page-style');
                        if (pst) pst.textContent = '';
                        pa.innerHTML = current.svg;
                        document.body.classList.add('print-area');
                        window.print();
                        window.addEventListener('afterprint', function () { document.body.classList.remove('print-area'); }, { once: true });
                    }, 'printer', { disabled: !has }),
                    btn(T('Im Verlauf merken'), 'btn-ghost btn-sm', () => { const id = CF.Store.put('h', { t: current.text, f: format, src: 'create', type: CF.Parse.content(current.text, {}, CF.lang).type, title: CF.Parse.content(current.text, {}, CF.lang).summary, fav: true }); if (id) CF.toast(T('Im Verlauf gespeichert (mit Stern)')); }, 'star', { disabled: !has }),
                    btn(T('Als Etiketten'), 'btn-ghost btn-sm', () => {
                        const c = CF.ls.get('labels', {});
                        Object.assign(c, { source: 'same', same: current.text, sameLabel: '', count: c.count || 24, format: ['QRCode', 'DataMatrix', 'Code128', 'EAN13', 'EAN8', 'Code39', 'ITF14', 'GS1-128', 'Aztec'].indexOf(format) >= 0 ? format : 'QRCode' });
                        CF.ls.set('labels', c);
                        CF.go('print', { tab: 'labels' });
                    }, 'grid', { disabled: !has })));
        }

        function renderAll() { renderTemplates(); renderForm(); renderDesign(); update(); }

        fill(root,
            CF.viewHead(T('Erstellen'), T('QR-Codes mit eigenem Design, dazu Data Matrix, Aztec, PDF417 und alle gängigen Strichcodes. Jeder Code wird automatisch auf Lesbarkeit geprüft.')),
            el('div', { class: 'layout' },
                el('div', { class: 'stack lg' },
                    CF.card(T('Inhalt'), 'edit', el('div', { class: 'stack' }, field(T('Format'), fmtSel), fmtHint, tplBox, formBox)),
                    CF.card(T('Design'), 'palette', designBox)),
                el('div', { class: 'sticky stack lg' },
                    CF.card(T('Vorschau'), 'eye', el('div', { class: 'stack' }, preview, info, exportBox)),
                    CF.card(T('Lesetest'), 'test', testBox))));
        renderAll();
        if (MATRIX[format] && !MATRIX[format].own) CF.Engine.ready().catch(() => {});
        return () => { testToken++; };
    },
};
})();
