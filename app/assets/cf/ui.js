/* Suchmaschine – Kern der Werkzeug-Apps: Sprache, Bausteine, Dialoge, Meldungen, Speicher, Server-Aufrufe.
   Übernommen aus CodeForge (AGPL, gleicher Autor) und an die Suchmaschine angepasst. */
(function () {
'use strict';
const CF = window.CF = window.CF || {};

/* ---------- Startdaten und Einstellungen ---------- */

try { CF.boot = JSON.parse(document.getElementById('cf-boot').textContent); } catch (e) { CF.boot = {}; }
CF.boot.v = CF.boot.v || '';

const LS = 'sm.cf.';
CF.ls = {
    get(k, d) { try { const v = localStorage.getItem(LS + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(LS + k, JSON.stringify(v)); return true; } catch (e) { return false; } },
    del(k) { try { localStorage.removeItem(LS + k); } catch (e) { /* egal */ } },
};

const PREF_DEFAULTS = { sound: true, vibrate: true, history: true, autocopy: false };
CF.prefs = Object.assign({}, PREF_DEFAULTS, CF.ls.get('prefs', {}));
CF.savePrefs = () => CF.ls.set('prefs', CF.prefs);
CF.lang = CF.boot.lang || (document.documentElement.lang === 'en' ? 'en' : 'de');

/* Ebene für Dialoge und Meldungen: hängt am Ende der Seite, damit die Bausteine überall gleich aussehen */
CF.layer = document.createElement('div');
CF.layer.className = 'cf cf-layer';
document.body.append(CF.layer);

/* ---------- Sprache: Deutsch steht im Code, Englisch in en.js ---------- */

function T(s, ...args) {
    let r = s;
    if (CF.lang === 'en' && CF.EN && Object.prototype.hasOwnProperty.call(CF.EN, s)) r = CF.EN[s];
    args.forEach(a => { r = r.replace('%s', a); });
    return r;
}
CF.T = T;
CF.locale = () => (CF.lang === 'en' ? 'en-GB' : 'de-DE');
CF.fmtDateTime = ts => new Date(ts).toLocaleString(CF.locale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
CF.fmtTime = ts => new Date(ts).toLocaleTimeString(CF.locale(), { hour: '2-digit', minute: '2-digit', second: '2-digit' });
CF.fmtDate = ts => new Date(ts).toLocaleDateString(CF.locale(), { day: '2-digit', month: '2-digit', year: 'numeric' });
CF.fmtNum = n => Number(n).toLocaleString(CF.locale());
CF.ago = ts => {
    const s = Math.round((Date.now() - ts) / 1000);
    if (s < 45) return T('gerade eben');
    if (s < 3600) return T('vor %s Min.', Math.round(s / 60));
    if (s < 86400) return T('vor %s Std.', Math.round(s / 3600));
    return CF.fmtDate(ts);
};

/* ---------- Symbole (im Stil von Lucide, ISC-Lizenz) ---------- */

const I = {
    scan: '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
    qr: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20v.01M17 20h4v-3"/>',
    barcode: '<path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14"/>',
    create: '<path d="M12 3v18M3 12h18"/><rect x="3" y="3" width="18" height="18" rx="4"/>',
    wand: '<path d="m15 4-1 1M4 15l-1 1M3 4l1 1M19 18l1 1M14 9 4.5 18.5a1.5 1.5 0 0 0 2 2L16 11"/><path d="M14 9l2 2M18 3v4M16 5h4M5 6v4M3 8h4"/>',
    convert: '<path d="M7 7h11l-3-3M17 17H6l3 3"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    wallet: '<rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20M6 15h4"/>',
    pair: '<rect x="2" y="4" width="13" height="10" rx="1.5"/><path d="M6 18h5M8.5 14v4"/><rect x="16" y="9" width="6" height="11" rx="1.5"/><path d="M19 17h.01"/>',
    dyn: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    xray: '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/><path d="M5 12s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3.5"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
    torch: '<path d="M18 6c0 2-2 2-2 4v10a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V10c0-2-2-2-2-4V2h12z"/><path d="M6 6h12M12 12v.01"/>',
    flip: '<path d="M20 7h-9M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
    stop: '<rect x="6" y="6" width="12" height="12" rx="2"/>',
    play: '<path d="m6 3 14 9-14 9z"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
    star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    trash: '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    external: '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    alert: '<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/><path d="M12 9v4M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    wifi: '<path d="M5 12.6a10 10 0 0 1 14 0M1.4 9a15 15 0 0 1 21.2 0M8.5 16.4a5 5 0 0 1 7 0M12 20h.01"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    sms: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    bank: '<path d="m3 9 9-6 9 6M5 9v9M9.5 9v9M14.5 9v9M19 9v9M3 21h18"/>',
    key: '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
    box: '<path d="M21 8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
    plane: '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
    coins: '<circle cx="8" cy="8" r="6"/><path d="M18.1 10.4A6 6 0 1 1 10.3 18M7 6h1v4M16.7 13.9l.7.7-2.8 2.8"/>',
    hash: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
    text: '<path d="M17 6.1H3M21 12.1H3M15.1 18H3"/>',
    ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>',
    moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    monitor: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    phoneDev: '<rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M12 18h.01"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    login: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    refresh: '<path d="M3 12a9 9 0 0 1 9-9 9.8 9.8 0 0 1 6.7 2.7L21 8"/><path d="M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.8 9.8 0 0 1-6.7-2.7L3 16"/><path d="M8 16H3v5"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    printer: '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    sparkles: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
    terminal: '<path d="m4 17 6-6-6-6M12 19h8"/>',
    keyboard: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    right: '<path d="m9 18 6-6-6-6"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    volume: '<path d="M11 5 6 9H2v6h4l5 4zM15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/>',
    cloud: '<path d="M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9Z"/>',
    offline: '<path d="m2 2 20 20M8.5 16.5a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 4.2-2.6M10.7 5.1A15 15 0 0 1 22 8.8M5 12.9a10 10 0 0 1 5.2-2.8M19 12.9a10 10 0 0 0-2.1-1.6M12 20h.01"/>',
    zoom: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/>',
    palette: '<circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/><path d="M12 2a10 10 0 0 0 0 20c.9 0 1.7-.8 1.7-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.6-1.6H16a6 6 0 0 0 6-6C22 6 17.5 2 12 2Z"/>',
    test: '<path d="M9 2v6l-5 9a3 3 0 0 0 2.6 4.5h10.8A3 3 0 0 0 20 17l-5-9V2M8 2h8M7 14h10"/>',
    home: '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    install: '<path d="M12 3v12M7 10l5 5 5-5"/><rect x="3" y="17" width="18" height="4" rx="1"/>',
    brush: '<path d="m9.1 11.1 7.6-7.6a2.1 2.1 0 1 1 3 3l-7.6 7.6M7 14a3 3 0 0 0-3 3c0 1.3-1 2-2 3 2.5 1 5 0 6-1a3 3 0 0 0-1-5Z"/>',
    dice: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h.01M16 8h.01M12 12h.01M8 16h.01M16 16h.01"/>',
    grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    chart: '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-9"/>',
    power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
};
CF.icon = (name, cls) => '<svg class="i' + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (I[name] || I.info) + '</svg>';
CF.ICONS = I;

/* ---------- Bausteine ---------- */

/** Baut ein Element. Fremde Texte kommen immer über „text“ oder als Kind hinein, nie als HTML. */
function el(tag, props, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
        if (v == null || v === false) continue;
        if (k === 'class') e.className = v;
        else if (k === 'text') e.textContent = v;
        else if (k === 'html') e.innerHTML = v; // nur für eigene SVGs
        else if (k === 'icon') e.innerHTML = CF.icon(v);
        else if (k === 'style' && typeof v === 'object') Object.assign(e.style, v);
        else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2), v);
        else if (k === 'value') e.value = v;
        else if (k === 'checked') e.checked = !!v;
        else e.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat(3)) if (kid != null && kid !== false) e.append(kid);
    return e;
}
function fill(node, ...kids) { node.replaceChildren(...kids.flat(3).filter(k => k != null && k !== false)); return node; }
function btn(label, cls, onclick, iconName, attrs) {
    const b = el('button', Object.assign({ class: 'btn ' + (cls || 'btn-second'), type: 'button', onclick }, attrs || {}));
    if (iconName) b.insertAdjacentHTML('beforeend', CF.icon(iconName));
    if (label) b.append(el('span', { text: label }));
    return b;
}
function ibtn(name, title, onclick, cls) {
    return el('button', { class: 'ibtn' + (cls ? ' ' + cls : ''), type: 'button', title, 'aria-label': title, icon: name, onclick });
}
function field(label, input, hint) {
    return el('label', { class: 'f' }, el('span', {}, label, hint ? el('span', { class: 'hint', text: ' · ' + hint }) : null), input);
}
function input(props) { return el('input', Object.assign({ class: 'in' }, props || {})); }
function select(options, value, onchange, props) {
    const s = el('select', Object.assign({ class: 'in', onchange: e => onchange && onchange(e.target.value) }, props || {}));
    options.forEach(o => {
        if (o.group) { const g = el('optgroup', { label: o.group }); o.items.forEach(i => g.append(el('option', { value: i[0], text: i[1] }))); s.append(g); }
        else s.append(el('option', { value: o[0], text: o[1] }));
    });
    s.value = value;
    return s;
}
function seg(options, value, onchange, cls) {
    const wrap = el('div', { class: 'seg' + (cls ? ' ' + cls : ''), role: 'tablist' });
    const set = v => { [...wrap.children].forEach(b => b.classList.toggle('on', b.dataset.v === v)); };
    options.forEach(([v, label, ic]) => {
        const b = el('button', { type: 'button', 'data-v': v, onclick: () => { set(v); onchange && onchange(v); } });
        if (ic) b.insertAdjacentHTML('beforeend', CF.icon(ic));
        b.append(el('span', { text: label }));
        wrap.append(b);
    });
    set(value);
    wrap.set = set;
    return wrap;
}
function toggle(label, checked, onchange, sub) {
    const inp = el('input', { type: 'checkbox', checked, onchange: e => onchange && onchange(e.target.checked) });
    return el('label', { class: 'switch' }, inp, el('span', { class: 't' }, el('span', { text: label }), sub ? el('small', { text: sub }) : null));
}
function colorField(value, onchange) {
    const txt = input({ value, maxlength: 9, spellcheck: 'false' });
    const pick = el('input', { type: 'color', value: /^#[0-9a-f]{6}$/i.test(value) ? value : '#000000' });
    pick.addEventListener('input', () => { txt.value = pick.value; onchange(pick.value); });
    txt.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(txt.value)) { pick.value = txt.value; onchange(txt.value); } });
    const w = el('div', { class: 'colorf' }, pick, txt);
    w.setValue = v => { txt.value = v; if (/^#[0-9a-f]{6}$/i.test(v)) pick.value = v; };
    return w;
}
function msg(kind, text, iconName) {
    const ic = iconName || (kind === 'ok' ? 'check' : kind === 'info' ? 'info' : kind === 'warn' || kind === 'danger' ? 'alert' : 'info');
    const m = el('div', { class: 'msg ' + kind });
    m.insertAdjacentHTML('beforeend', CF.icon(ic));
    m.append(typeof text === 'string' ? el('div', { text }) : text);
    return m;
}
function badge(text, kind) { return el('span', { class: 'badge' + (kind ? ' ' + kind : ''), text }); }
function viewHead(title, sub, tools) {
    return el('div', { class: 'vh' }, el('div', {}, el('h1', { text: title }), sub ? el('p', { text: sub }) : null), tools ? el('div', { class: 'tools' }, tools) : null);
}
function card(title, iconName, ...kids) {
    const h = title ? el('h2', {}) : null;
    if (h) { if (iconName) h.insertAdjacentHTML('beforeend', CF.icon(iconName)); h.append(el('span', { text: title })); }
    return el('div', { class: 'card' }, h, ...kids);
}
function empty(iconName, text, action) {
    const e = el('div', { class: 'empty' });
    e.insertAdjacentHTML('beforeend', CF.icon(iconName));
    e.append(el('div', { text }));
    if (action) e.append(el('div', { style: { marginTop: '1rem' } }, action));
    return e;
}
Object.assign(CF, { el, fill, btn, ibtn, field, input, select, seg, toggle, colorField, msg, badge, viewHead, card, empty });

/* ---------- Meldungen ---------- */

let toastBox = null;
CF.toast = (text, kind, ms) => {
    if (!toastBox) { toastBox = el('div', { class: 'toasts', 'aria-live': 'polite' }); CF.layer.append(toastBox); }
    const t = el('div', { class: 'toast ' + (kind || 'ok') });
    t.insertAdjacentHTML('beforeend', CF.icon(kind === 'bad' ? 'alert' : kind === 'warn' ? 'alert' : kind === 'info' ? 'info' : 'check'));
    t.append(el('span', { text }));
    toastBox.append(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 320); }, ms || 2600);
};

/* ---------- Dialoge ---------- */

const openDialogs = [];
CF.modal = (opts) => {
    const close = () => {
        if (!ov.isConnected) return;
        ov.remove();
        const i = openDialogs.indexOf(api);
        if (i >= 0) openDialogs.splice(i, 1);
        if (opts.onClose) opts.onClose();
    };
    const head = el('div', { class: 'dh' }, el('h3', { text: opts.title || '' }), ibtn('x', T('Schließen'), close));
    const body = el('div', { class: 'db' }, opts.body);
    const foot = opts.actions && opts.actions.length ? el('div', { class: 'df' }, opts.actions) : null;
    const dlg = el('div', { class: 'dialog' + (opts.wide ? ' wide' : ''), role: 'dialog', 'aria-modal': 'true' }, head, body, foot);
    const ov = el('div', { class: 'overlay', onclick: e => { if (e.target === ov && !opts.sticky) close(); } }, dlg);
    CF.layer.append(ov);
    const api = { close, body, dlg, foot };
    openDialogs.push(api);
    setTimeout(() => { const f = dlg.querySelector('[autofocus]'); if (f) f.focus(); }, 30);
    return api;
};
document.addEventListener('keydown', e => { if (e.key === 'Escape' && openDialogs.length) openDialogs[openDialogs.length - 1].close(); });

CF.confirm = (text, okLabel, danger) => new Promise(resolve => {
    let done = false;
    const m = CF.modal({
        title: T('Bitte bestätigen'),
        body: el('p', { text, style: { margin: '.2rem 0 .4rem' } }),
        actions: [btn(T('Abbrechen'), 'btn-ghost', () => { done = true; m.close(); resolve(false); }),
                  btn(okLabel || T('OK'), danger ? 'btn-danger' : 'btn-primary', () => { done = true; m.close(); resolve(true); })],
        onClose: () => { if (!done) resolve(false); },
    });
});

CF.prompt = (title, label, value, opts) => new Promise(resolve => {
    let done = false;
    const inp = input(Object.assign({ value: value || '', autofocus: true }, (opts && opts.input) || {}));
    const ok = () => { done = true; m.close(); resolve(inp.value); };
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') ok(); });
    const m = CF.modal({ title, body: el('div', { class: 'stack' }, field(label, inp)), actions: [btn(T('Abbrechen'), 'btn-ghost', () => { done = true; m.close(); resolve(null); }), btn(T('OK'), 'btn-primary', ok)], onClose: () => { if (!done) resolve(null); } });
});

/* ---------- Kleine Helfer ---------- */

CF.uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
CF.debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
CF.sleep = ms => new Promise(r => setTimeout(r, ms));

CF.copy = async (text, silent) => {
    let ok = false;
    try { await navigator.clipboard.writeText(text); ok = true; } catch (e) {
        const ta = el('textarea', { style: { position: 'fixed', top: '-1000px', opacity: '0' } });
        ta.value = text;
        document.body.append(ta);
        ta.select();
        try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
        ta.remove();
    }
    if (!silent) CF.toast(ok ? T('Kopiert') : T('Kopieren nicht möglich'), ok ? 'ok' : 'bad', 1400);
    return ok;
};
CF.download = (name, blob) => {
    const url = URL.createObjectURL(blob);
    const a = el('a', { href: url, download: name });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
};
CF.share = async (data) => {
    if (navigator.share) { try { await navigator.share(data); return true; } catch (e) { return false; } }
    return false;
};
/** Öffnet einen Link in neuem Tab, ohne der Zielseite etwas zu verraten */
CF.openUrl = (url) => {
    const a = el('a', { href: url, target: '_blank', rel: 'noopener noreferrer' });
    document.body.append(a);
    a.click();
    a.remove();
};
CF.csvCell = v => { v = String(v == null ? '' : v); if (/^[=+\-@\t\r]/.test(v)) v = "'" + v; return /[;"\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
CF.csv = rows => '﻿' + rows.map(r => r.map(CF.csvCell).join(';')).join('\r\n') + '\r\n';
CF.fileStamp = () => new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-');

let audioCtx = null;
CF.beep = (freq, ms) => {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'sine';
        o.frequency.value = freq || 1760;
        g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.18, audioCtx.currentTime + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + (ms || 90) / 1000);
        o.connect(g); g.connect(audioCtx.destination);
        o.start(); o.stop(audioCtx.currentTime + (ms || 90) / 1000 + 0.02);
    } catch (e) { /* kein Ton möglich */ }
};
CF.feedback = () => {
    if (CF.prefs.sound) CF.beep(1760, 80);
    if (CF.prefs.vibrate && navigator.vibrate) navigator.vibrate(40);
};

/** Datei auswählen (Bild usw.) */
CF.pickFile = (accept, multiple) => new Promise(resolve => {
    const inp = el('input', { type: 'file', accept: accept || 'image/*', style: { display: 'none' } });
    if (multiple) inp.multiple = true;
    inp.addEventListener('change', () => { resolve(multiple ? [...inp.files] : inp.files[0] || null); inp.remove(); });
    document.body.append(inp);
    inp.click();
});
CF.readDataUrl = (file) => new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file); });

/* ---------- Server ---------- */

CF.session = { user: CF.boot.user || null, settings: CF.boot.settings || {} };
CF.online = navigator.onLine !== false;
CF.loggedIn = () => !!CF.session.user;
CF.isAdmin = () => !!(CF.session.user && CF.session.user.admin);

/** Aufruf an den eigenen Server: ?a=tools&api=<action>, als Formular mit CSRF-Token (gleiche Regeln wie jedes andere Formular) */
CF.api = async (action, data, opts) => {
    const body = new URLSearchParams();
    body.set('csrf', CF.boot.csrf || '');
    body.set('data', JSON.stringify(data || {}));
    let res;
    try {
        res = await fetch((CF.boot.base || './') + '?a=tools&api=' + encodeURIComponent(action), { method: 'POST', body, credentials: 'same-origin', cache: 'no-store', redirect: 'manual' });
    } catch (e) {
        const err = new Error(T('Keine Verbindung zum Server.'));
        err.offline = true;
        throw err;
    }
    let j = null;
    try { j = await res.json(); } catch (e) { /* kein JSON */ }
    if (!j) throw new Error(res.type === 'opaqueredirect' ? T('Bitte neu anmelden oder die Seite neu laden.') : T('Der Server hat nicht richtig geantwortet.') + ' (' + res.status + ')');
    if (j.ok === false && !(opts && opts.raw)) {
        const err = new Error(j.message || T('Fehler'));
        err.data = j;
        throw err;
    }
    return j;
};

/* ---------- Zwischen den Werkzeugen springen ---------- */

/** Namen der Ansichten aus CodeForge → Werkzeug der Suchmaschine */
CF.TOOL_OF = { create: 'qr', scan: 'scanner', convert: 'nummern', print: 'qrdruck', passwort: 'passwort', kurzlinks: 'kurzlinks' };
CF.toolUrl = (tool) => (CF.boot.base || './') + '?a=tools&tool=' + encodeURIComponent(tool);
CF.go = (view, params) => {
    const tool = CF.TOOL_OF[view] || view;
    try { sessionStorage.setItem('sm.cf.go', JSON.stringify({ tool, params: params || {} })); } catch (e) { /* egal */ }
    if (CF.boot.tool === tool && CF.views[view] && CF.mountRoot) { CF.mount(view, params || {}); window.scrollTo(0, 0); return; }
    location.href = CF.toolUrl(tool);
};
/** Übergebene Werte für dieses Werkzeug (einmalig) */
CF.takeParams = (tool) => {
    try {
        const raw = sessionStorage.getItem('sm.cf.go');
        if (!raw) return null;
        const j = JSON.parse(raw);
        if (j.tool !== tool) return null;
        sessionStorage.removeItem('sm.cf.go');
        return j.params || {};
    } catch (e) { return null; }
};
CF.views = CF.views || {};
})();
