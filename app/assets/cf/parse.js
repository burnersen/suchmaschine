/* CodeForge – Inhalte verstehen: Was steckt in einem gescannten Code?
 *
 * Erkennt Links (mit Quishing-Prüfung), WLAN, Kontakte, Termine, Orte, Telefon, SMS, E-Mail,
 * SEPA-GiroCode, Schweizer QR-Rechnung, Zwei-Faktor-Schlüssel, GS1-Daten, Bordkarten, Krypto-Adressen,
 * Bahntickets und Nummern (Zahlen-Detektiv). Texte sind deutsch, die Oberfläche übersetzt sie.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};
// Übersetzen, sobald die Oberfläche geladen ist (Texte bleiben deutsch, wenn nicht)
const tr = (s, ...a) => (CF.T ? CF.T(s, ...a) : a.reduce((r, x) => r.replace('%s', x), s));

/* ---------- Punycode (RFC 3492), um xn--Adressen lesbar zu machen ---------- */

function punyDecode(input) {
    const base = 36, tMin = 1, tMax = 26, skew = 38, damp = 700;
    const adapt = (delta, num, first) => {
        let k = 0;
        delta = first ? Math.floor(delta / damp) : delta >> 1;
        delta += Math.floor(delta / num);
        for (; delta > ((base - tMin) * tMax) >> 1; k += base) delta = Math.floor(delta / (base - tMin));
        return Math.floor(k + ((base - tMin + 1) * delta) / (delta + skew));
    };
    const out = [];
    let i = 0, n = 128, bias = 72;
    let basic = input.lastIndexOf('-');
    if (basic < 0) basic = 0;
    for (let j = 0; j < basic; j++) out.push(input.charCodeAt(j));
    for (let idx = basic > 0 ? basic + 1 : 0; idx < input.length;) {
        const oldi = i;
        let w = 1;
        for (let k = base; ; k += base) {
            if (idx >= input.length) throw new Error('puny');
            const c = input.charCodeAt(idx++);
            const digit = c - 48 < 10 ? c - 22 : c - 65 < 26 ? c - 65 : c - 97 < 26 ? c - 97 : base;
            if (digit >= base) throw new Error('puny');
            i += digit * w;
            const t = k <= bias ? tMin : k >= bias + tMax ? tMax : k - bias;
            if (digit < t) break;
            w *= base - t;
        }
        const len = out.length + 1;
        bias = adapt(i - oldi, len, oldi === 0);
        n += Math.floor(i / len);
        i %= len;
        out.splice(i++, 0, n);
    }
    return String.fromCodePoint.apply(null, out);
}

function unicodeHost(host) {
    return host.split('.').map(l => {
        if (!/^xn--/i.test(l)) return l;
        try { return punyDecode(l.slice(4)); } catch (e) { return l; }
    }).join('.');
}

/* ---------- Quishing-Schutz: Wie vertrauenswürdig sieht ein Link aus? ---------- */

const SECOND_LEVEL = ['co.uk', 'org.uk', 'me.uk', 'ac.uk', 'gov.uk', 'ltd.uk', 'plc.uk', 'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au', 'co.nz', 'org.nz',
    'co.jp', 'ne.jp', 'or.jp', 'ac.jp', 'com.br', 'com.cn', 'net.cn', 'org.cn', 'com.tr', 'co.za', 'com.mx', 'co.in', 'co.kr', 'or.kr', 'com.sg', 'com.hk',
    'com.tw', 'co.at', 'or.at', 'gv.at', 'ac.at', 'co.il', 'com.ar', 'com.co', 'com.pe', 'com.ua', 'com.pl', 'co.id', 'com.my', 'com.ph', 'com.vn'];
// Hier kann jeder kostenlos eine Unteradresse anlegen
const FREE_HOSTS = ['github.io', 'web.app', 'firebaseapp.com', 'pages.dev', 'netlify.app', 'vercel.app', 'herokuapp.com', 'blogspot.com', 'wordpress.com',
    'azurewebsites.net', 'ngrok.io', 'ngrok-free.app', 'ngrok.app', 'glitch.me', 'repl.co', 'onrender.com', 'workers.dev', 'wixsite.com', 'weebly.com',
    'webflow.io', 'framer.website', 'square.site', 'carrd.co', 'duckdns.org', 'no-ip.org', 'ddns.net', 'translate.goog', 'r2.dev', 'appspot.com',
    'sites.google.com', 'forms.gle', 'trycloudflare.com', 'surge.sh', 'gitbook.io', 'notion.site'];
const SHORTENERS = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'is.gd', 'buff.ly', 'rebrand.ly', 'cutt.ly', 'shorturl.at', 'rb.gy', 't.ly', 'tiny.cc',
    's.id', 'lnkd.in', 'qrco.de', 'qrs.ly', 'bl.ink', 'short.io', 'shorte.st', 'adf.ly', 'v.gd', 'tr.im', 'x.co', 'soo.gd', 'clck.ru', 'u.to', 'qr.net',
    'me-qr.com', 'qr1.be', 'l.ead.me', 'linktr.ee', 'bitly.com', 'tiny.one', 'urlz.fr', 'kurzelinks.de', 'shorturl.de', 'kurzurl.net', '1url.de', 'gg.gg'];
const RISKY_TLD = ['zip', 'mov', 'top', 'xyz', 'click', 'country', 'gq', 'tk', 'ml', 'cf', 'ga', 'work', 'loan', 'men', 'cam', 'rest', 'kim', 'fit', 'icu',
    'buzz', 'monster', 'cyou', 'sbs', 'cfd', 'lol', 'quest', 'bond', 'support', 'help', 'live', 'site', 'online', 'info', 'shop', 'store', 'su'];
// Marken, die gern nachgeahmt werden. regional = echte Adressen enthalten oft Zusätze (sparkasse-koelnbonn.de)
const BRANDS = [
    ['paypal', ['paypal.com', 'paypal.de', 'paypal.me']], ['amazon', ['amazon.de', 'amazon.com', 'amazon.co.uk', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.at', 'amzn.to', 'amzn.eu', 'amazon.nl']],
    ['apple', ['apple.com', 'icloud.com']], ['icloud', ['icloud.com']], ['google', ['google.com', 'google.de', 'goo.gl', 'g.co']], ['microsoft', ['microsoft.com', 'live.com', 'office.com']],
    ['outlook', ['outlook.com', 'live.com']], ['netflix', ['netflix.com']], ['ebay', ['ebay.de', 'ebay.com', 'ebay.at', 'ebay.ch']], ['dhl', ['dhl.de', 'dhl.com']],
    ['dpd', ['dpd.de', 'dpd.com']], ['hermes', ['myhermes.de', 'hermesworld.com', 'hermes.com']], ['fedex', ['fedex.com']], ['ups', ['ups.com']],
    ['sparkasse', null, true], ['volksbank', null, true], ['raiffeisen', null, true], ['commerzbank', ['commerzbank.de', 'commerzbank.com']], ['postbank', ['postbank.de']],
    ['deutschebank', ['deutsche-bank.de', 'db.com']], ['comdirect', ['comdirect.de']], ['consorsbank', ['consorsbank.de']], ['n26', ['n26.com']], ['dkb', ['dkb.de']],
    ['targobank', ['targobank.de']], ['telekom', ['telekom.de', 't-online.de', 'telekom.com']], ['vodafone', ['vodafone.de', 'vodafone.com']], ['facebook', ['facebook.com', 'fb.com', 'fb.me']],
    ['instagram', ['instagram.com']], ['whatsapp', ['whatsapp.com', 'wa.me']], ['steam', ['steampowered.com', 'steamcommunity.com']], ['binance', ['binance.com']],
    ['coinbase', ['coinbase.com']], ['klarna', ['klarna.com', 'klarna.de']], ['elster', ['elster.de']], ['easypark', ['easypark.de', 'easypark.com', 'easyparkgroup.com']],
    ['parkster', ['parkster.com']], ['evcharging', null, true], ['ionity', ['ionity.eu']], ['tesla', ['tesla.com']], ['spotify', ['spotify.com']], ['booking', ['booking.com']],
];
const DOWNLOAD_EXT = /\.(apk|exe|msi|scr|bat|cmd|dmg|pkg|vbs|jar|ps1|com|mobileconfig|appx|xapk)(\?|#|$)/i;

function registrable(host) {
    const labels = host.split('.');
    if (labels.length <= 2) return host;
    const last2 = labels.slice(-2).join('.');
    const last3 = labels.slice(-3).join('.');
    if (FREE_HOSTS.indexOf(last3) >= 0 && labels.length >= 4) return labels.slice(-4).join('.');
    if (SECOND_LEVEL.indexOf(last2) >= 0 || FREE_HOSTS.indexOf(last2) >= 0) return last3;
    return last2;
}

function confusableNorm(s) {
    return s.toLowerCase().replace(/rn/g, 'm').replace(/vv/g, 'w').replace(/0/g, 'o').replace(/1/g, 'l').replace(/3/g, 'e').replace(/5/g, 's').replace(/[-_]/g, '').replace(/i/g, 'l');
}

function scriptsOf(label) {
    const s = new Set();
    for (const ch of label) {
        const c = ch.codePointAt(0);
        if ((c >= 0x30 && c <= 0x39) || c === 0x2d) continue;
        if (c < 0x250) s.add('latin');
        else if (c >= 0x370 && c < 0x400) s.add('greek');
        else if (c >= 0x400 && c < 0x530) s.add('cyrillic');
        else s.add('other');
    }
    return s;
}
const CYR_LOOKALIKE = /^[аеорсухіјѕԁӏԛԝһѵ\-0-9]+$/u;

function analyzeUrl(raw, ownBase) {
    const res = { level: 'ok', findings: [], url: null, scheme: '', host: '', unicodeHost: '', registrable: '', clean: null, own: false };
    const add = (level, text, ...args) => {
        res.findings.push({ level, text, args });
        const order = ['ok', 'info', 'warn', 'danger'];
        if (order.indexOf(level) > order.indexOf(res.level)) res.level = level;
    };
    let u;
    try { u = new URL(raw); } catch (e) { add('warn', 'Die Adresse ist fehlerhaft aufgebaut.'); return res; }
    res.url = u;
    res.scheme = u.protocol.replace(':', '');
    if (res.scheme === 'javascript' || res.scheme === 'data' || res.scheme === 'vbscript' || res.scheme === 'file') {
        add('danger', 'Kein normaler Link: Er würde Programmcode oder Daten direkt im Browser ausführen.');
        return res;
    }
    if (res.scheme === 'intent') add('warn', 'Android-App-Link: öffnet direkt eine App.');
    if (res.scheme !== 'http' && res.scheme !== 'https') return res;
    res.host = u.hostname.toLowerCase().replace(/\.$/, '');
    res.unicodeHost = unicodeHost(res.host);
    const isIp = /^\d+\.\d+\.\d+\.\d+$/.test(res.host) || res.host.indexOf(':') >= 0 || /^\[/.test(u.host);
    res.registrable = isIp ? res.host : registrable(res.host);
    if (ownBase) {
        try { if (new URL(ownBase).host === u.host) { res.own = true; } } catch (e) { /* egal */ }
    }
    if (res.scheme === 'http') add('warn', 'Unverschlüsselt (http): Mitlesen und Verändern unterwegs ist möglich.');
    if (u.username || u.password) add('danger', 'Täuschung: Alles vor dem @ wird ignoriert. Die echte Adresse ist %s.', res.host);
    if (isIp) add('warn', 'Statt eines Namens steht eine IP-Adresse da. Seriöse Anbieter machen das fast nie.');
    if (u.port && u.port !== '80' && u.port !== '443') add('info', 'Ungewöhnlicher Port: %s.', u.port);
    if (res.host.indexOf('xn--') >= 0) {
        const bad = res.unicodeHost.split('.').some(l => { const s = scriptsOf(l); return s.size > 1 || CYR_LOOKALIKE.test(l); });
        if (bad) add('danger', 'Gefälschte Schrift: „%s“ mischt Buchstaben aus verschiedenen Alphabeten und sieht nur aus wie eine bekannte Adresse.', res.unicodeHost);
        else add('info', 'Internationale Adresse: %s', res.unicodeHost);
    }
    const regLabel = res.registrable.split('.')[0];
    const tld = res.host.split('.').pop();
    if (SHORTENERS.indexOf(res.registrable) >= 0 || SHORTENERS.indexOf(res.host) >= 0) { res.shortener = true; add('info', 'Kurzlink: Das eigentliche Ziel ist versteckt. Lass es prüfen, bevor du öffnest.'); }
    const free = FREE_HOSTS.find(f => res.host === f || res.host.endsWith('.' + f));
    if (free) add('info', 'Kostenloser Webspace (%s): Hier kann jeder eine Seite anlegen.', free);
    if (RISKY_TLD.indexOf(tld) >= 0) add('info', 'Die Endung .%s wird oft für Betrugsseiten genutzt.', tld);
    const subLabels = res.host.slice(0, Math.max(0, res.host.length - res.registrable.length - 1)).split(/[.\-]/).filter(Boolean);
    for (const [brand, official, regional] of BRANDS) {
        const inReg = regLabel.indexOf(brand) >= 0 || res.registrable.replace(/[-.]/g, '').indexOf(brand) >= 0;
        const isOfficial = official ? official.indexOf(res.registrable) >= 0 : inReg;
        if (isOfficial) continue;
        if (subLabels.some(l => l.indexOf(brand) >= 0) || res.url.pathname.toLowerCase().split(/[\/\-_.]/).indexOf(brand) >= 0) {
            add('warn', '„%s“ steht in der Adresse, die Seite gehört aber zu %s.', brand, res.registrable);
            break;
        }
        if (official && !regional && inReg) {
            add('warn', 'Enthält „%s“, ist aber nicht die bekannte Adresse (%s).', brand, official.slice(0, 2).join(', '));
            break;
        }
        if (official && confusableNorm(regLabel) === confusableNorm(brand) && regLabel !== brand) {
            add('danger', 'Täuschend ähnlich wie „%s“, aber eine andere Adresse.', brand);
            break;
        }
    }
    if (DOWNLOAD_EXT.test(u.pathname)) add('warn', 'Der Link lädt direkt eine App oder ein Programm herunter.');
    if (res.host.split('.').length > 5) add('info', 'Sehr viele Unteradressen, das soll oft die echte Adresse verstecken.');
    // Tracking-Anhängsel
    const tracking = [];
    u.searchParams.forEach((v, k) => { if (/^(utm_|fbclid|gclid|dclid|msclkid|mc_eid|igshid|_hs|yclid|wt_|si$)/i.test(k)) tracking.push(k); });
    if (tracking.length) {
        const c = new URL(u.href);
        tracking.forEach(k => c.searchParams.delete(k));
        res.clean = c.href;
        add('info', 'Enthält Tracking-Kennungen (%s).', tracking.slice(0, 3).join(', '));
    }
    if (res.own) { res.findings = res.findings.filter(f => f.level !== 'info'); add('info', 'Link auf diese CodeForge-Installation.'); }
    if (!res.findings.length) add('ok', 'Keine Auffälligkeiten gefunden.');
    return res;
}

/* ---------- Hilfen ---------- */

function unescapeMe(v) { return v.replace(/\\([\\;,:"])/g, '$1'); }

/** WIFI: und MECARD: haben Felder K:Wert; mit \-Maskierung */
function splitFields(body) {
    const out = [];
    let cur = '', esc = false;
    for (const ch of body) {
        if (esc) { cur += ch; esc = false; continue; }
        if (ch === '\\') { esc = true; cur += ch; continue; }
        if (ch === ';') { if (cur) out.push(cur); cur = ''; continue; }
        cur += ch;
    }
    if (cur) out.push(cur);
    return out.map(f => {
        const i = f.indexOf(':');
        return i < 0 ? [f.toUpperCase(), ''] : [f.slice(0, i).toUpperCase(), unescapeMe(f.slice(i + 1))];
    });
}

function vUnescape(v) { return v.replace(/\\n/gi, '\n').replace(/\\([,;\\])/g, '$1'); }

function parseVLines(text) {
    const lines = text.replace(/\r\n/g, '\n').replace(/\n[ \t]/g, '').split('\n');
    const out = [];
    for (const line of lines) {
        const i = line.indexOf(':');
        if (i < 0) continue;
        const head = line.slice(0, i).split(';');
        const name = head[0].toUpperCase().replace(/^ITEM\d+\./, '');
        const params = {};
        head.slice(1).forEach(p => { const [k, v] = p.split('='); params[(v === undefined ? 'TYPE' : k).toUpperCase()] = (v === undefined ? k : v); });
        out.push({ name, params, value: line.slice(i + 1) });
    }
    return out;
}

function icsDate(v, lang) {
    const m = /^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})?(Z)?)?$/.exec(v || '');
    if (!m) return v || '';
    const d = m[4] ? (m[7] ? new Date(Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5])) : new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5])) : new Date(+m[1], m[2] - 1, +m[3]);
    const loc = lang === 'en' ? 'en-GB' : 'de-DE';
    return m[4] ? d.toLocaleString(loc, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : d.toLocaleDateString(loc, { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
}

function money(v, cur, lang) {
    const n = parseFloat(v);
    if (!isFinite(n)) return v;
    try { return n.toLocaleString(lang === 'en' ? 'en-GB' : 'de-DE', { style: 'currency', currency: cur || 'EUR' }); } catch (e) { return n.toFixed(2) + ' ' + (cur || ''); }
}

/* ---------- Die einzelnen Erkenner ---------- */

function parseWifi(t) {
    const f = {};
    splitFields(t.slice(5)).forEach(([k, v]) => { f[k] = v; });
    const fields = [['Netzwerk (SSID)', f.S || '', { copy: true }]];
    const sec = (f.T || '').toUpperCase();
    if (sec !== 'NOPASS' && f.P !== undefined) fields.push(['Passwort', f.P, { copy: true, secret: true, mono: true }]);
    fields.push(['Verschlüsselung', sec === 'NOPASS' || !sec ? tr('keine (offen)') : sec]);
    if (/^true$/i.test(f.H || '')) fields.push(['Versteckt', tr('ja')]);
    if (f.E) fields.push(['EAP-Methode', f.E]);
    if (f.I) fields.push(['Identität', f.I, { copy: true }]);
    const warnings = [];
    if (sec === 'WEP') warnings.push({ level: 'warn', text: 'WEP ist veraltet und unsicher.' });
    if (!sec || sec === 'NOPASS') warnings.push({ level: 'info', text: 'Offenes WLAN ohne Passwort: Daten können mitgelesen werden.' });
    return { type: 'wifi', title: 'WLAN-Zugang', summary: f.S || '', fields, warnings, data: f, actions: f.P ? ['copy-password'] : [] };
}

function parseVcard(t) {
    const lines = parseVLines(t);
    const get = n => lines.filter(l => l.name === n);
    const first = n => (get(n)[0] || {}).value || '';
    let name = vUnescape(first('FN'));
    if (!name) { const n = first('N').split(';'); name = [n[3], n[1], n[0], n[4]].filter(Boolean).map(vUnescape).join(' '); }
    const fields = [];
    if (name) fields.push(['Name', name, { copy: true }]);
    if (first('ORG')) fields.push(['Firma', vUnescape(first('ORG').replace(/;/g, ', ')), { copy: true }]);
    if (first('TITLE')) fields.push(['Funktion', vUnescape(first('TITLE'))]);
    const tels = get('TEL').map(l => l.value.replace(/^tel:/i, ''));
    get('TEL').forEach(l => fields.push(['Telefon', l.value.replace(/^tel:/i, '') + (l.params.TYPE ? ' (' + l.params.TYPE.toLowerCase() + ')' : ''), { copy: true, raw: l.value.replace(/^tel:/i, ''), href: 'tel:' + l.value.replace(/^tel:/i, '').replace(/\s/g, '') }]));
    const mails = get('EMAIL').map(l => l.value);
    mails.forEach(m => fields.push(['E-Mail', m, { copy: true, href: 'mailto:' + m }]));
    get('ADR').forEach(l => { const a = l.value.split(';').map(vUnescape).filter(Boolean).join(', '); if (a) fields.push(['Adresse', a, { copy: true }]); });
    get('URL').forEach(l => fields.push(['Webseite', l.value, { copy: true, link: true }]));
    if (first('BDAY')) fields.push(['Geburtstag', first('BDAY')]);
    if (first('NOTE')) fields.push(['Notiz', vUnescape(first('NOTE'))]);
    return { type: 'vcard', title: 'Kontakt (vCard)', summary: name || tels[0] || mails[0] || '', fields, warnings: [], data: { vcf: t, name, tel: tels[0], mail: mails[0] }, actions: ['save-vcf'] };
}

function parseMecard(t) {
    const f = {};
    splitFields(t.slice(7)).forEach(([k, v]) => { (f[k] = f[k] || []).push(v); });
    const n = (f.N || [''])[0].split(',');
    const name = n.length > 1 ? n[1].trim() + ' ' + n[0].trim() : n[0];
    let vcf = 'BEGIN:VCARD\r\nVERSION:3.0\r\nN:' + (n[0] || '') + ';' + (n[1] || '') + ';;;\r\nFN:' + name + '\r\n';
    (f.TEL || []).forEach(x => { vcf += 'TEL:' + x + '\r\n'; });
    (f.EMAIL || []).forEach(x => { vcf += 'EMAIL:' + x + '\r\n'; });
    (f.ADR || []).forEach(x => { vcf += 'ADR:;;' + x + ';;;;\r\n'; });
    (f.URL || []).forEach(x => { vcf += 'URL:' + x + '\r\n'; });
    (f.NOTE || []).forEach(x => { vcf += 'NOTE:' + x + '\r\n'; });
    vcf += 'END:VCARD\r\n';
    const p = parseVcard(vcf);
    p.title = 'Kontakt (MeCard)';
    return p;
}

function parseEvent(t, lang) {
    const lines = parseVLines(t);
    const first = n => { const l = lines.find(x => x.name === n); return l ? l.value : ''; };
    const fields = [];
    const title = vUnescape(first('SUMMARY'));
    if (title) fields.push(['Titel', title]);
    if (first('DTSTART')) fields.push(['Beginn', icsDate(first('DTSTART'), lang)]);
    if (first('DTEND')) fields.push(['Ende', icsDate(first('DTEND'), lang)]);
    if (first('LOCATION')) fields.push(['Ort', vUnescape(first('LOCATION')), { copy: true }]);
    if (first('DESCRIPTION')) fields.push(['Beschreibung', vUnescape(first('DESCRIPTION'))]);
    if (first('URL')) fields.push(['Webseite', first('URL'), { link: true }]);
    let ics = t.replace(/\r?\n/g, '\r\n');
    if (!/BEGIN:VCALENDAR/i.test(ics)) ics = 'BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//CodeForge//DE\r\n' + ics.trim() + '\r\nEND:VCALENDAR\r\n';
    return { type: 'event', title: 'Termin', summary: title, fields, warnings: [], data: { ics, title }, actions: ['save-ics'] };
}

function parseGeo(t) {
    const m = /^geo:(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,(-?\d+(?:\.\d+)?))?(?:;[^?]*)?(?:\?(.*))?$/i.exec(t.trim());
    if (!m) return null;
    const lat = +m[1], lon = +m[2];
    if (Math.abs(lat) > 90 || Math.abs(lon) > 180) return null;
    const q = m[4] ? new URLSearchParams(m[4]).get('q') : '';
    const fields = [['Breitengrad', String(lat), { mono: true }], ['Längengrad', String(lon), { mono: true }]];
    if (m[3]) fields.push(['Höhe', m[3] + ' m']);
    if (q) fields.push(['Ort', q]);
    return { type: 'geo', title: 'Ort (Koordinaten)', summary: q || lat.toFixed(5) + ', ' + lon.toFixed(5), fields, warnings: [], data: { lat, lon, q }, actions: ['map', 'copy-coords'] };
}

function parseTel(t) {
    const num = decodeURIComponent(t.replace(/^tel:/i, ''));
    return { type: 'tel', title: 'Telefonnummer', summary: num, fields: [['Nummer', num, { copy: true, mono: true }]], warnings: [], data: { num }, actions: ['call'] };
}

function parseSms(t) {
    let num = '', body = '';
    if (/^smsto:/i.test(t)) { const p = t.slice(6).split(':'); num = p.shift(); body = p.join(':'); }
    else {
        const m = /^sms:([^?]*)(?:\?(.*))?$/i.exec(t);
        if (m) { num = decodeURIComponent(m[1]); body = m[2] ? (new URLSearchParams(m[2]).get('body') || '') : ''; }
    }
    const fields = [['Empfänger', num, { copy: true, mono: true }]];
    if (body) fields.push(['Nachricht', body, { copy: true }]);
    return { type: 'sms', title: 'SMS', summary: num, fields, warnings: [{ level: 'info', text: 'SMS an unbekannte Nummern können Geld kosten (Premium-Dienste).' }], data: { num, body }, actions: ['sms'] };
}

function parseMail(t) {
    let to = '', sub = '', body = '';
    if (/^matmsg:/i.test(t)) {
        const f = {};
        splitFields(t.slice(7)).forEach(([k, v]) => { f[k] = v; });
        to = f.TO || ''; sub = f.SUB || ''; body = f.BODY || '';
    } else {
        const m = /^mailto:([^?]*)(?:\?(.*))?$/i.exec(t);
        if (!m) return null;
        to = decodeURIComponent(m[1]);
        if (m[2]) { const q = new URLSearchParams(m[2]); sub = q.get('subject') || ''; body = q.get('body') || ''; }
    }
    const fields = [['An', to, { copy: true }]];
    if (sub) fields.push(['Betreff', sub]);
    if (body) fields.push(['Text', body]);
    const href = 'mailto:' + encodeURIComponent(to).replace(/%40/g, '@') + (sub || body ? '?' + [sub ? 'subject=' + encodeURIComponent(sub) : '', body ? 'body=' + encodeURIComponent(body) : ''].filter(Boolean).join('&') : '');
    return { type: 'email', title: 'E-Mail', summary: to, fields, warnings: [], data: { to, sub, body, href }, actions: ['mail'] };
}

function parseGiro(t, lang) {
    const l = t.replace(/\r\n/g, '\n').split('\n');
    if (l[0] !== 'BCD' || l.length < 7) return null;
    const iban = (l[6] || '').replace(/\s/g, '');
    const amount = /^([A-Z]{3})(\d+(?:\.\d{1,2})?)$/.exec(l[7] || '');
    const fields = [['Empfänger', l[5] || '', { copy: true }], ['IBAN', CF.Num.ibanFormat(iban), { copy: true, mono: true, raw: iban }]];
    if (l[4]) fields.push(['BIC', l[4], { copy: true, mono: true }]);
    fields.push(['Betrag', amount ? money(amount[2], amount[1], lang) : tr('frei (in der Banking-App eingeben)')]);
    if (l[9]) fields.push(['Referenz', l[9], { copy: true }]);
    if (l[10]) fields.push(['Verwendungszweck', l[10], { copy: true }]);
    if (l[11]) fields.push(['Hinweis', l[11]]);
    if (l[3] === 'INST') fields.push(['Art', tr('Echtzeit-Überweisung')]);
    const warnings = [];
    if (!CF.Num.ibanValid(iban)) warnings.push({ level: 'danger', text: 'Die IBAN ist ungültig (Prüfsumme falsch). Nicht überweisen!' });
    warnings.push({ level: 'info', text: 'Bezahlen: Den Code in deiner Banking-App mit „Foto-Überweisung“ oder „GiroCode“ scannen. Empfänger vorher genau prüfen.' });
    return { type: 'girocode', title: 'SEPA-Überweisung (GiroCode)', summary: (l[5] || '') + (amount ? ' · ' + money(amount[2], amount[1], lang) : ''), fields, warnings, data: { iban, name: l[5], amount: amount ? amount[2] : '' }, actions: ['copy-iban'] };
}

function parseSwiss(t, lang) {
    const l = t.replace(/\r\n/g, '\n').split('\n');
    if (l[0] !== 'SPC' || l.length < 28) return null;
    const fields = [['Empfänger', l[5] || '', { copy: true }], ['IBAN', CF.Num.ibanFormat(l[3] || ''), { copy: true, mono: true }]];
    const addr = [l[6], l[7], [l[8], l[9]].filter(Boolean).join(' '), l[10]].filter(Boolean).join(', ');
    if (addr) fields.push(['Adresse', addr]);
    fields.push(['Betrag', l[18] ? money(l[18], l[19], lang) : tr('frei')]);
    if (l[28]) fields.push(['Referenz', l[28], { copy: true, mono: true }]);
    if (l[29]) fields.push(['Mitteilung', l[29]]);
    const warnings = CF.Num.ibanValid(l[3] || '') ? [] : [{ level: 'danger', text: 'Die IBAN ist ungültig (Prüfsumme falsch).' }];
    return { type: 'swissqr', title: 'Schweizer QR-Rechnung', summary: (l[5] || '') + (l[18] ? ' · ' + money(l[18], l[19], lang) : ''), fields, warnings, data: {}, actions: [] };
}

function parseOtp(t) {
    let u;
    try { u = new URL(t); } catch (e) { return null; }
    const kind = u.host.toLowerCase();
    const label = decodeURIComponent(u.pathname.replace(/^\//, ''));
    const p = u.searchParams;
    const issuer = p.get('issuer') || (label.indexOf(':') > 0 ? label.split(':')[0] : '');
    const account = label.indexOf(':') > 0 ? label.split(':').slice(1).join(':') : label;
    const secret = (p.get('secret') || '').replace(/\s/g, '').toUpperCase();
    const fields = [];
    if (issuer) fields.push(['Dienst', issuer]);
    if (account) fields.push(['Konto', account, { copy: true }]);
    fields.push(['Art', kind === 'hotp' ? tr('HOTP (Zähler)') : tr('TOTP (Zeit)')]);
    fields.push(['Geheimer Schlüssel', secret, { copy: true, secret: true, mono: true }]);
    fields.push(['Stellen', p.get('digits') || '6']);
    if (kind !== 'hotp') fields.push(['Intervall', (p.get('period') || '30') + ' s']);
    if (p.get('algorithm')) fields.push(['Verfahren', p.get('algorithm')]);
    return {
        type: 'otp', title: 'Zwei-Faktor-Schlüssel', summary: [issuer, account].filter(Boolean).join(' · '), fields,
        warnings: [{ level: 'warn', text: 'Enthält einen geheimen Schlüssel. Wer ihn hat, kann deine Anmeldecodes erzeugen. Nicht teilen!' }],
        data: { secret, digits: +(p.get('digits') || 6), period: +(p.get('period') || 30), algorithm: (p.get('algorithm') || 'SHA1').toUpperCase(), kind }, actions: kind === 'totp' ? ['totp'] : [],
    };
}

const CABIN = { F: 'First', A: 'First', P: 'First', J: 'Business', C: 'Business', D: 'Business', I: 'Business', Z: 'Business', W: 'Premium Economy', E: 'Premium Economy', Y: 'Economy' };
function parseBcbp(t, lang) {
    if (!/^M[1-4]/.test(t) || t.length < 58) return null;
    const name = t.substr(2, 20).trim();
    const pnr = t.substr(23, 7).trim();
    const from = t.substr(30, 3), to = t.substr(33, 3);
    const carrier = t.substr(36, 3).trim(), flight = t.substr(39, 5).trim().replace(/^0+/, '');
    const jd = parseInt(t.substr(44, 3), 10);
    const cabin = t.substr(47, 1);
    const seat = t.substr(48, 4).trim().replace(/^0+/, '');
    const seq = t.substr(52, 5).trim().replace(/^0+/, '');
    if (!/^[A-Z]{3}$/.test(from) || !/^[A-Z]{3}$/.test(to)) return null;
    let date = '';
    if (jd >= 1 && jd <= 366) {
        // Das Jahr steht nicht drin: das späteste Datum nehmen, das höchstens 60 Tage in der Zukunft liegt
        const now = new Date();
        let d = null;
        for (const y of [now.getFullYear() + 1, now.getFullYear(), now.getFullYear() - 1]) {
            const c = new Date(y, 0, jd);
            if (c - now <= 60 * 86400000) { d = c; break; }
        }
        date = d.toLocaleDateString(lang === 'en' ? 'en-GB' : 'de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    const fields = [['Passagier', name.replace('/', ', ')], ['Flug', carrier + ' ' + flight], ['Strecke', from + ' → ' + to], ['Datum', date], ['Sitz', seat || '–'],
        ['Klasse', (CABIN[cabin] || '') + ' (' + cabin + ')'], ['Buchungscode', pnr, { copy: true, mono: true }], ['Check-in-Nummer', seq]];
    if (t[1] !== '1') fields.push(['Teilstrecken', t[1]]);
    return { type: 'bcbp', title: 'Bordkarte', summary: carrier + ' ' + flight + ' · ' + from + ' → ' + to, fields, warnings: [{ level: 'warn', text: 'Mit Name und Buchungscode kann man die Buchung ändern oder stornieren. Bordkarten nicht öffentlich zeigen.' }], data: {}, actions: [] };
}

function parseCrypto(t) {
    const m = /^(bitcoin|ethereum|litecoin|monero|dogecoin|bitcoincash|solana):([^?]+)(?:\?(.*))?$/i.exec(t.trim());
    if (!m) return null;
    const q = m[3] ? new URLSearchParams(m[3]) : null;
    const fields = [['Währung', m[1].charAt(0).toUpperCase() + m[1].slice(1)], ['Adresse', m[2], { copy: true, mono: true }]];
    if (q && (q.get('amount') || q.get('value'))) fields.push(['Betrag', q.get('amount') || q.get('value')]);
    if (q && (q.get('label') || q.get('message'))) fields.push(['Hinweis', q.get('label') || q.get('message')]);
    return { type: 'crypto', title: 'Krypto-Zahlung', summary: m[2].slice(0, 12) + '…', fields, warnings: [{ level: 'warn', text: 'Krypto-Zahlungen lassen sich nicht zurückholen. Adresse genau prüfen.' }], data: {}, actions: [] };
}

function parseGs1(t, meta, lang) {
    const isGs1 = meta && (meta.contentType === 'GS1' || /^\](C1|d2|Q3|e0|J1)/.test(meta.sid || ''));
    const g = CF.GS1.parse(t, lang, isGs1);
    if (!g) return null;
    const fields = g.items.map(it => {
        let v = it.shown;
        if ((it.ai === '01' || it.ai === '02') && /^\d{14}$/.test(it.value)) {
            v += CF.Num.gtinValid(it.value) ? ' ✓' : ' ✗';
            const pre = CF.Num.gs1Prefix(it.value.substr(1), lang);
            if (pre) v += ' · ' + pre;
        }
        return [it.name + ' (' + it.ai + ')', v, { copy: true, raw: it.value }];
    });
    const warnings = [];
    const exp = g.items.find(i => i.ai === '17' || i.ai === '15');
    if (exp && /^\d{6}$/.test(exp.value)) {
        const y = 2000 + +exp.value.substr(0, 2), mo = +exp.value.substr(2, 2), d = +exp.value.substr(4, 2) || new Date(y, mo, 0).getDate();
        if (new Date(y, mo - 1, d, 23, 59) < new Date()) warnings.push({ level: 'warn', text: exp.ai === '17' ? 'Das Verfallsdatum ist überschritten.' : 'Das Mindesthaltbarkeitsdatum ist überschritten.' });
    }
    const gtin = g.items.find(i => i.ai === '01');
    return { type: 'gs1', title: g.form === 'link' ? 'GS1 Digital Link' : 'GS1-Daten', summary: g.items.map(i => '(' + i.ai + ')' + i.value).join('').slice(0, 60), fields, warnings, data: { gtin: gtin && gtin.value, items: g.items, link: g.form === 'link' }, actions: g.form === 'link' ? ['open'] : [] };
}

function parseNumber(t, lang) {
    const hits = CF.Num.detect(t, lang);
    if (!hits.length) return null;
    const best = hits.find(h => h.valid) || hits[0];
    const fields = [['Nummer', t, { copy: true, mono: true }]];
    best.details.forEach(d => fields.push([d[0], tr(d[1])]));
    return { type: 'number', title: best.name, summary: t, fields, warnings: best.valid === false ? [{ level: 'warn', text: 'Prüfziffer passt nicht.' }] : [], data: { hits }, actions: ['detective'] };
}

function parseTicket(bytes, kind) {
    if (kind === 'vdv') {
        return { type: 'vdv', title: 'VDV-Ticket (z. B. Deutschlandticket)', summary: tr('VDV-Barcode'), fields: [['Format', 'VDV-KA (ÖPNV)']],
            warnings: [{ level: 'info', text: 'Dieses Ticket ist signiert und verschlüsselt. Den Inhalt können nur Kontrollgeräte mit den Schlüsseln der Verkehrsverbünde lesen.' }], data: {}, actions: [] };
    }
    const company = CF.Ticket ? CF.Ticket.RICS[String.fromCharCode(bytes[5], bytes[6], bytes[7], bytes[8])] : '';
    return {
        type: 'ticket', title: 'Bahnticket (UIC 918.3)', summary: company || 'UIC 918.3',
        fields: [], warnings: [{ level: 'info', text: 'Die Echtheit (Signatur) kann CodeForge nicht prüfen. Gezeigt wird, was im Ticket steht.' }], data: { bytes }, actions: [],
    };
}

function latin1Bytes(t) { return Uint8Array.from(Array.from(t), c => c.charCodeAt(0) & 255); }

/**
 * Hauptfunktion. meta: { format, contentType, sid } von der Engine (optional)
 * Liefert { type, title, summary, fields: [[Bezeichnung, Wert, opts]], warnings, data, actions, safety? }
 */
function content(text, meta, lang) {
    meta = meta || {};
    const t = String(text == null ? '' : text);
    const trimmed = t.trim();
    const base = (CF.boot && CF.boot.base) || '';
    let r = null;

    // Eigene Kopplung?
    if (base && trimmed.indexOf(base) === 0) {
        try {
            const u = new URL(trimmed);
            if (u.searchParams.get('p')) return { type: 'pair', title: 'Kopplung mit einem Empfänger', summary: '', fields: [], warnings: [], data: { code: u.searchParams.get('p') }, actions: ['pair'] };
        } catch (e) { /* weiter */ }
    }
    if (/^WIFI:/i.test(trimmed)) r = parseWifi(trimmed);
    else if (/^BEGIN:VCARD/i.test(trimmed)) r = parseVcard(trimmed);
    else if (/^MECARD:/i.test(trimmed)) r = parseMecard(trimmed);
    else if (/^BEGIN:(VCALENDAR|VEVENT)/i.test(trimmed)) r = parseEvent(trimmed, lang);
    else if (/^geo:/i.test(trimmed)) r = parseGeo(trimmed);
    else if (/^tel:/i.test(trimmed)) r = parseTel(trimmed);
    else if (/^(sms|smsto):/i.test(trimmed)) r = parseSms(trimmed);
    else if (/^(mailto|matmsg):/i.test(trimmed)) r = parseMail(trimmed);
    else if (/^BCD\r?\n/.test(t)) r = parseGiro(t, lang);
    else if (/^SPC\r?\n/.test(t)) r = parseSwiss(t, lang);
    else if (/^otpauth:\/\//i.test(trimmed)) r = parseOtp(trimmed);
    else if (/^(bitcoin|ethereum|litecoin|monero|dogecoin|bitcoincash|solana):/i.test(trimmed)) r = parseCrypto(trimmed);
    else if (/^M[1-4]/.test(t) && t.length >= 58) r = parseBcbp(t, lang);
    if (r) return r;

    // Binärdaten: Bahnticket (UIC 918.3) oder VDV-Barcode?
    if (CF.Ticket) {
        let bytes = null;
        if (meta.bytes) { try { bytes = CF.Ticket.b64bytes(meta.bytes); } catch (e) { bytes = null; } }
        else if (/^#UT0[12]/.test(t)) bytes = latin1Bytes(t);
        const kind = bytes && CF.Ticket.detect(bytes);
        if (kind) return parseTicket(bytes, kind);
    }
    // QR-Funk-Bild
    if (/^CFX:[0-9A-Z]{4}:\d+\/\d+:/.test(t)) return { type: 'fx', title: 'QR-Funk-Übertragung', summary: t.slice(0, 20), fields: [], warnings: [{ level: 'info', text: 'Das ist ein Teil einer QR-Funk-Übertragung. Zum Empfangen „QR-Funk → Empfangen“ öffnen.' }], data: {}, actions: ['fx'] };

    r = parseGs1(trimmed, meta, lang);
    if (r && r.data.link) {
        r.safety = analyzeUrl(trimmed, base);
        r.data.url = trimmed;
        return r;
    }
    if (r) return r;

    // MEBKM (Lesezeichen)
    const bm = /^MEBKM:/i.test(trimmed) ? splitFields(trimmed.slice(6)).find(([k]) => k === 'URL') : null;
    let url = bm ? bm[1] : trimmed;
    if (/^www\.[^\s]+\.[a-z]{2,}/i.test(url)) url = 'https://' + url;
    if (/^[a-z][a-z0-9+.\-]*:\/\/\S+$/i.test(url) || /^(javascript|data|intent):/i.test(url)) {
        const safety = analyzeUrl(url, base);
        const summary = safety.host ? (safety.unicodeHost || safety.host) + safety.url.pathname.replace(/\/$/, '') : url;
        const fields = [['Adresse', url, { copy: true, mono: true }]];
        if (safety.host) fields.push(['Domain', safety.registrable, { strong: true }]);
        return { type: 'url', title: safety.own ? 'Link (eigene Installation)' : 'Link', summary, fields, warnings: [], data: { url, clean: safety.clean }, actions: ['open', 'resolve'], safety };
    }
    if (/^[\d\s\-]{6,24}$/.test(trimmed) || /^[A-Z]{2}\d{2}[A-Z0-9 ]{10,40}$/i.test(trimmed) || /^\d{9}[\dX]$/i.test(trimmed) || /^\d{7}[\dX]$/i.test(trimmed)) {
        r = parseNumber(trimmed, lang);
        if (r) return r;
    }
    if (meta.bytes && meta.contentType === 'Binary') {
        return { type: 'binary', title: 'Binärdaten', summary: tr('%s Bytes', meta.bytesLen || '?'), fields: [['Inhalt', tr('Binärdaten (siehe Umrechner: Hex)')]], warnings: [], data: {}, actions: [] };
    }
    const lines = t.split('\n').length;
    return { type: 'text', title: 'Text', summary: trimmed.slice(0, 80), fields: [['Text', t, { copy: true, pre: true }], ['Länge', tr('%s Zeichen', Array.from(t).length) + (lines > 1 ? ', ' + tr('%s Zeilen', lines) : '')]], warnings: [], data: {}, actions: [] };
}

/* ---------- TOTP (für die Live-Anzeige bei Zwei-Faktor-Schlüsseln) ---------- */

function base32(s) {
    const abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = 0, val = 0;
    const out = [];
    for (const ch of s.replace(/=+$/, '')) {
        const i = abc.indexOf(ch);
        if (i < 0) continue;
        val = (val << 5) | i; bits += 5;
        if (bits >= 8) { out.push((val >>> (bits - 8)) & 255); bits -= 8; }
    }
    return new Uint8Array(out);
}

async function totp(secret, digits, period, algorithm) {
    if (!root.crypto || !root.crypto.subtle) return null;
    const algo = { SHA1: 'SHA-1', SHA256: 'SHA-256', SHA512: 'SHA-512' }[algorithm] || 'SHA-1';
    const key = await crypto.subtle.importKey('raw', base32(secret), { name: 'HMAC', hash: algo }, false, ['sign']);
    const counter = Math.floor(Date.now() / 1000 / (period || 30));
    const msg = new ArrayBuffer(8);
    const dv = new DataView(msg);
    dv.setUint32(0, Math.floor(counter / 4294967296));
    dv.setUint32(4, counter >>> 0);
    const h = new Uint8Array(await crypto.subtle.sign('HMAC', key, msg));
    const o = h[h.length - 1] & 15;
    const bin = ((h[o] & 127) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
    return String(bin % Math.pow(10, digits || 6)).padStart(digits || 6, '0');
}

CF.Parse = { content, analyzeUrl, unicodeHost, punyDecode, registrable, totp };
})(typeof self !== 'undefined' ? self : globalThis);
