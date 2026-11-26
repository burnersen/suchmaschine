/* CodeForge – Umrechner: Zahlen-Detektiv, Format-Umwandlung (auch „Reparatur“ aus Fotos) und Daten-Umrechner */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input } = CF;

/* ---------- Daten-Umrechner ---------- */

const enc = new TextEncoder();
const dec = new TextDecoder('utf-8', { fatal: false });
const B45 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

function toB64(bytes, url) {
    let s = '';
    bytes.forEach(b => { s += String.fromCharCode(b); });
    const b = btoa(s);
    return url ? b.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') : b;
}
function fromB64(s) {
    s = s.replace(/\s+/g, '').replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    const bin = atob(s);
    return Uint8Array.from(bin, c => c.charCodeAt(0));
}
function toB45(bytes) {
    let out = '';
    for (let i = 0; i < bytes.length; i += 2) {
        if (i + 1 < bytes.length) {
            let n = bytes[i] * 256 + bytes[i + 1];
            const c = n % 45; n = (n - c) / 45;
            const d = n % 45; const e = (n - d) / 45;
            out += B45[c] + B45[d] + B45[e];
        } else {
            const n = bytes[i];
            out += B45[n % 45] + B45[Math.floor(n / 45)];
        }
    }
    return out;
}
function fromB45(s) {
    const out = [];
    for (let i = 0; i < s.length; i += 3) {
        const chunk = s.substr(i, 3);
        const v = Array.from(chunk).map(c => { const k = B45.indexOf(c); if (k < 0) throw new Error('b45'); return k; });
        if (v.length === 3) { const n = v[0] + v[1] * 45 + v[2] * 2025; if (n > 65535) throw new Error('b45'); out.push(n >> 8, n & 255); }
        else if (v.length === 2) { const n = v[0] + v[1] * 45; if (n > 255) throw new Error('b45'); out.push(n); }
        else throw new Error('b45');
    }
    return Uint8Array.from(out);
}
const KINDS = {
    text: { name: 'Text (UTF-8)', to: b => dec.decode(b), from: s => enc.encode(s) },
    b64: { name: 'Base64', to: b => toB64(b), from: fromB64 },
    b64url: { name: 'Base64URL', to: b => toB64(b, true), from: fromB64 },
    hex: { name: 'Hexadezimal', to: b => Array.from(b, x => x.toString(16).padStart(2, '0')).join(' '), from: s => { const h = s.replace(/0x/gi, '').replace(/[^0-9a-f]/gi, ''); if (h.length % 2) throw new Error('hex'); return Uint8Array.from(h.match(/../g) || [], x => parseInt(x, 16)); } },
    url: { name: 'URL-kodiert', to: b => encodeURIComponent(dec.decode(b)), from: s => enc.encode(decodeURIComponent(s.replace(/\+/g, ' '))) },
    bin: { name: 'Binär', to: b => Array.from(b, x => x.toString(2).padStart(8, '0')).join(' '), from: s => { const t = s.replace(/[^01]/g, ''); if (t.length % 8) throw new Error('bin'); return Uint8Array.from(t.match(/.{8}/g) || [], x => parseInt(x, 2)); } },
    dec: { name: 'Dezimal (Bytes)', to: b => Array.from(b).join(' '), from: s => Uint8Array.from(s.trim().split(/[\s,;]+/).filter(Boolean).map(x => { const n = +x; if (!(n >= 0 && n < 256)) throw new Error('dec'); return n; })) },
    b45: { name: 'Base45 (QR)', to: toB45, from: fromB45 },
    html: { name: 'HTML-Entitäten', to: b => dec.decode(b).replace(/[&<>"' -￿]/g, c => '&#' + c.codePointAt(0) + ';'), from: s => { const t = document.createElement('textarea'); t.innerHTML = s; return enc.encode(t.value); } },
};
CF.DataKinds = KINDS;
CF.B45 = { encode: toB45, decode: fromB45 };

function dataConverter(initial) {
    let kind = 'text';
    const ta = el('textarea', { class: 'in mono', rows: 5, placeholder: T('Text, Base64, Hex … hier einfügen') });
    ta.value = initial || '';
    const kindSel = CF.select(Object.entries(KINDS).map(([k, v]) => [k, T(v.name)]), kind, v => { kind = v; render(); });
    const out = el('div', { class: 'out' });
    const meta = el('div', { class: 'small muted' });
    const guess = el('div');
    function render() {
        let bytes;
        try { bytes = KINDS[kind].from(ta.value); } catch (e) { fill(out, CF.msg('warn', T('Die Eingabe passt nicht zu „%s“.', T(KINDS[kind].name)))); meta.textContent = ''; return; }
        const txt = dec.decode(bytes);
        meta.textContent = T('%s Bytes', bytes.length) + ' · ' + T('%s Zeichen', Array.from(txt).length) + (/[\u0080-￿]/.test(txt) ? ' · ' + T('enthält Sonderzeichen') : '');
        fill(out, Object.entries(KINDS).filter(([k]) => k !== kind).map(([k, v]) => {
            let val = '';
            try { val = v.to(bytes); } catch (e) { val = '–'; }
            return el('div', { class: 'conv' }, el('span', { class: 'k', text: T(v.name) }), el('span', { class: 'v mono', text: val.length > 600 ? val.slice(0, 600) + '…' : val }), ibtn('copy', T('Kopieren'), () => CF.copy(val), 'sm'));
        }));
        // Vermutung, was die Eingabe ist
        const s = ta.value.trim();
        let g = '';
        if (kind === 'text' && s) {
            if (/^[0-9a-f\s]+$/i.test(s) && s.replace(/\s/g, '').length % 2 === 0 && s.length >= 4 && /[a-f]/i.test(s)) g = 'hex';
            else if (/^[A-Za-z0-9+/]+={0,2}$/.test(s) && s.length % 4 === 0 && s.length >= 8 && /[a-z]/.test(s) && /[A-Z]/.test(s)) g = 'b64';
            else if (/%[0-9a-f]{2}/i.test(s)) g = 'url';
            else if (/^[01\s]+$/.test(s) && s.replace(/\s/g, '').length % 8 === 0 && s.length >= 8) g = 'bin';
            else if (/^HC1:/.test(s)) g = 'b45';
        }
        fill(guess, g ? el('div', { class: 'row small' }, el('span', { class: 'muted', text: T('Sieht aus wie %s.', T(KINDS[g].name)) }), btn(T('Als %s lesen', T(KINDS[g].name)), 'btn-soft btn-sm', () => { kind = g; kindSel.value = g; if (g === 'b45') ta.value = ta.value.replace(/^HC1:/, ''); render(); })) : null);
    }
    ta.addEventListener('input', render);
    render();
    return el('div', { class: 'stack' }, el('div', { class: 'grid2' }, field(T('Eingabe ist'), kindSel), el('div')), ta, meta, guess, out);
}

/* ---------- Zahlen-Detektiv ---------- */

function detective(initial) {
    const inp = input({ class: 'in big mono', placeholder: '4006381333931', value: initial || '', autocomplete: 'off', spellcheck: 'false' });
    const out = el('div', { class: 'stack' });
    const render = () => {
        const v = inp.value.trim();
        if (!v) {
            fill(out, el('div', { class: 'small muted', text: T('Tippe oder füge eine Nummer ein: EAN, UPC, ISBN, ISSN, GTIN, SSCC, PZN, IMEI, IBAN …') }),
                el('div', { class: 'chips' }, [['4006381333931', 'EAN-13'], ['036000291452', 'UPC-A'], ['3-16-148410-X', 'ISBN-10'], ['0317-8471', 'ISSN'], ['DE89 3704 0044 0532 0130 00', 'IBAN'], ['08446538', 'PZN'], ['4006381333913', T('mit Fehler')]].map(([n, l]) =>
                    el('button', { class: 'chip', type: 'button', onclick: () => { inp.value = n; render(); } }, el('span', { class: 'mono', text: l })))));
            return;
        }
        const hits = CF.Num.detect(v, CF.lang);
        if (!hits.length) { fill(out, CF.msg('info', T('Dazu kenne ich kein Nummernformat. Tipp: Nur Ziffern (und bei IBAN Buchstaben) eingeben.'))); return; }
        fill(out, hits.map(h => {
            const head = el('div', { class: 'hit-head' }, el('b', { class: 'grow', text: T(h.name) }), h.valid === true ? CF.badge(T('gültig'), 'ok') : h.valid === false ? CF.badge(T('Prüfziffer falsch'), 'danger') : null);
            const kv = el('div', { class: 'kv' }, h.details.map(([k, val]) => [el('div', { class: 'k', text: T(k) }), el('div', { class: 'v', text: T(val) }), el('div', { class: 'a' })]));
            const fixes = h.fixes.length ? el('div', { class: 'convlist' }, el('div', { class: 'small muted', text: T('Vorschläge') }), h.fixes.map(([k, val]) =>
                el('div', { class: 'conv' }, el('span', { class: 'k', text: T(k) }), el('span', { class: 'v mono', text: val }), btn(T('übernehmen'), 'btn-soft btn-sm', () => { inp.value = val; render(); })))) : null;
            const conv = h.convert.length ? el('div', { class: 'convlist' }, el('div', { class: 'small muted', text: T('Umrechnen') }), h.convert.map(([k, val, fmt]) =>
                el('div', { class: 'conv' }, el('span', { class: 'k', text: T(k) }), el('span', { class: 'v mono', text: val }), ibtn('copy', T('Kopieren'), () => CF.copy(val), 'sm'),
                    fmt && fmt !== 'text' ? ibtn('wand', T('Als Code erstellen'), () => fmt === 'girocode' ? CF.go('create', { tpl: 'girocode', values: { iban: val } }) : CF.go('create', { text: val, format: fmt }), 'sm') : null))) : null;
            return el('div', { class: 'hit ' + (h.valid === true ? 'valid' : h.valid === false ? 'invalid' : '') }, head, kv, fixes, conv);
        }));
    };
    inp.addEventListener('input', render);
    render();
    return el('div', { class: 'stack' }, inp, out);
}

/* ---------- Format-Umwandlung ---------- */

const TARGETS = ['QRCode', 'DataMatrix', 'Aztec', 'PDF417', 'MicroQRCode', 'RMQRCode', 'Code128', 'EAN13', 'EAN8', 'UPCA', 'UPCE', 'ITF14', 'Code39', 'Code93', 'ITF', 'Codabar', 'GS1-128'];

function formatConverter(initial) {
    const ta = el('textarea', { class: 'in mono', rows: 3, placeholder: T('Inhalt eingeben oder Bild laden') });
    ta.value = initial || '';
    const grid = el('div', { class: 'formats' });
    const note = el('div');
    let token = 0;
    const render = CF.debounce(async () => {
        const text = ta.value;
        const my = ++token;
        if (!text) { fill(grid); return; }
        await CF.Engine.ready().catch(() => {});
        const cards = [];
        for (const f of TARGETS) {
            const name = CF.MATRIX[f] ? CF.MATRIX[f].name : CF.Bars.FORMATS[f].name;
            const pv = el('div', { class: 'pv' });
            const c = el('div', { class: 'fmt-card' }, pv, el('div', { class: 'nm' }, el('span', { class: 'grow', text: name })));
            cards.push([f, c, pv]);
        }
        if (my !== token) return;
        fill(grid, cards.map(x => x[1]));
        for (const [f, c, pv] of cards) {
            try {
                const g = await CF.generate(text, f, Object.assign({}, CF.ls.get('design', {}), { logo: null, frame: null, margin: CF.isLinear(f) ? 0 : 2 }));
                if (my !== token) return;
                pv.innerHTML = g.svg;
                c.querySelector('.nm').append(
                    ibtn('download', T('Als SVG laden'), () => CF.download(f.toLowerCase() + '.svg', new Blob([g.svg], { type: 'image/svg+xml' })), 'sm'),
                    ibtn('wand', T('Im Generator öffnen'), () => CF.go('create', { text, format: f }), 'sm'));
            } catch (e) {
                pv.append(el('div', { class: 'na', text: T('Passt nicht') }));
                c.style.opacity = '.55';
            }
        }
    }, 300);
    ta.addEventListener('input', render);
    const load = btn(T('Aus Bild lesen (Reparatur)'), 'btn-soft btn-sm', async () => {
        const f = await CF.pickFile('image/*');
        if (!f) return;
        const r = await CF.Engine.readBlob(f);
        if (!r.results.length) { fill(note, CF.msg('warn', T('Kein Code im Bild gefunden.'))); return; }
        ta.value = r.results[0].text;
        fill(note, CF.msg('ok', T('Erkannt: %s. Unten ist der Inhalt als sauberer, gestochen scharfer Code in allen passenden Formaten.', CF.formatName(r.results[0].format))));
        render();
    }, 'image');
    render();
    return el('div', { class: 'stack' }, el('div', { class: 'row between' }, el('div', { class: 'small muted', text: T('Ein Inhalt, alle Formate. Ideal, um einen Strichcode als QR-Code auszugeben (oder umgekehrt) oder ein verwackeltes Foto in einen sauberen Code zu verwandeln.') }), load), note, ta, grid);
}

/* ---------- Ansicht ---------- */

CF.views.convert = {
    title: 'Umrechnen', icon: 'convert',
    mount(root, params) {
        params = params || {};
        let tab = params.tab || (params.q ? 'num' : CF.ls.get('convTab', 'num'));
        const body = el('div');
        const last = CF.Store.all('h')[0];
        const draw = () => {
            CF.ls.set('convTab', tab);
            if (tab === 'num') fill(body, CF.card(T('Zahlen-Detektiv'), 'search', detective(params.q || '')));
            else if (tab === 'fmt') fill(body, CF.card(T('Format-Umwandlung'), 'convert', formatConverter(params.text || (last && last.data.t) || '')));
            else fill(body, CF.card(T('Daten-Umrechner'), 'terminal', dataConverter(params.text || '')));
        };
        const tabs = CF.seg([['num', T('Zahlen-Detektiv'), 'hash'], ['fmt', T('Format-Umwandlung'), 'convert'], ['data', T('Daten'), 'terminal']], tab, v => { tab = v; params = {}; draw(); });
        fill(root, CF.viewHead(T('Umrechnen'), T('Prüfziffern berechnen und prüfen, Nummern umrechnen, Codes in andere Formate wandeln und Daten zwischen Text, Base64, Hex und mehr übersetzen.'), tabs), body);
        draw();
    },
};
})();
