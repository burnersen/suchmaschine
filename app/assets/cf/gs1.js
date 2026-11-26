/* CodeForge – GS1: Datenbezeichner (AI) erkennen, zerlegen und zusammensetzen
 *
 * GS1-Daten stecken in GS1-128, GS1 DataMatrix, GS1 QR und im neuen GS1 Digital Link.
 * Beispiel: (01)04012345678901(17)261231(10)ABC123 = GTIN, Verfallsdatum, Charge.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

// [AI, Name (deutsch), Name (englisch), feste Länge der Daten oder 0 = variabel, größte Länge, Art]
// Art: n = Ziffern, an = Text, date = JJMMTT, dec = Ziffern mit Dezimalstellen (letzte Ziffer der AI)
const AIS = [
    ['00', 'SSCC (Versandeinheit)', 'SSCC (shipping container)', 18, 18, 'n'],
    ['01', 'GTIN (Artikelnummer)', 'GTIN (item number)', 14, 14, 'n'],
    ['02', 'GTIN der enthaltenen Artikel', 'GTIN of contained items', 14, 14, 'n'],
    ['10', 'Charge / Los', 'Batch / lot', 0, 20, 'an'],
    ['11', 'Herstelldatum', 'Production date', 6, 6, 'date'],
    ['12', 'Fälligkeitsdatum', 'Due date', 6, 6, 'date'],
    ['13', 'Verpackungsdatum', 'Packaging date', 6, 6, 'date'],
    ['15', 'Mindestens haltbar bis', 'Best before', 6, 6, 'date'],
    ['16', 'Verkaufen bis', 'Sell by', 6, 6, 'date'],
    ['17', 'Verfallsdatum', 'Expiry date', 6, 6, 'date'],
    ['20', 'Produktvariante', 'Variant', 2, 2, 'n'],
    ['21', 'Seriennummer', 'Serial number', 0, 20, 'an'],
    ['22', 'Zusatzdaten (Gesundheitswesen)', 'Consumer product variant', 0, 20, 'an'],
    ['235', 'Seriennummer (Dritter)', 'Third-party serial', 0, 28, 'an'],
    ['240', 'Zusätzliche Produktkennung', 'Additional product ID', 0, 30, 'an'],
    ['241', 'Kundenteilenummer', 'Customer part number', 0, 30, 'an'],
    ['242', 'Variantennummer', 'Made-to-order variation', 0, 6, 'n'],
    ['243', 'Verpackungskomponente', 'Packaging component', 0, 20, 'an'],
    ['250', 'Seriennr. Zweitprodukt', 'Secondary serial number', 0, 30, 'an'],
    ['251', 'Referenz Ursprungseinheit', 'Reference to source entity', 0, 30, 'an'],
    ['253', 'GDTI (Dokument)', 'GDTI (document)', 0, 30, 'an'],
    ['254', 'GLN-Erweiterung', 'GLN extension', 0, 20, 'an'],
    ['255', 'GCN (Gutschein)', 'GCN (coupon)', 0, 25, 'n'],
    ['30', 'Menge (Stück)', 'Count of items', 0, 8, 'n'],
    ['310', 'Nettogewicht (kg)', 'Net weight (kg)', 6, 6, 'dec'],
    ['311', 'Länge (m)', 'Length (m)', 6, 6, 'dec'],
    ['312', 'Breite (m)', 'Width (m)', 6, 6, 'dec'],
    ['313', 'Höhe (m)', 'Height (m)', 6, 6, 'dec'],
    ['314', 'Fläche (m²)', 'Area (m²)', 6, 6, 'dec'],
    ['315', 'Nettovolumen (l)', 'Net volume (l)', 6, 6, 'dec'],
    ['316', 'Nettovolumen (m³)', 'Net volume (m³)', 6, 6, 'dec'],
    ['320', 'Nettogewicht (lb)', 'Net weight (lb)', 6, 6, 'dec'],
    ['330', 'Bruttogewicht (kg)', 'Gross weight (kg)', 6, 6, 'dec'],
    ['37', 'Anzahl enthaltener Einheiten', 'Count of trade items', 0, 8, 'n'],
    ['390', 'Betrag (Landeswährung)', 'Amount payable', 0, 15, 'dec'],
    ['391', 'Betrag mit ISO-Währung', 'Amount with ISO currency', 0, 18, 'dec'],
    ['392', 'Preis (Gewichtsware)', 'Price (variable measure)', 0, 15, 'dec'],
    ['393', 'Preis mit ISO-Währung', 'Price with ISO currency', 0, 18, 'dec'],
    ['400', 'Bestellnummer des Kunden', 'Customer purchase order', 0, 30, 'an'],
    ['401', 'GINC (Sendung)', 'GINC (consignment)', 0, 30, 'an'],
    ['402', 'GSIN (Versand)', 'GSIN (shipment)', 17, 17, 'n'],
    ['403', 'Routing-Code', 'Routing code', 0, 30, 'an'],
    ['410', 'GLN Empfänger', 'Ship to GLN', 13, 13, 'n'],
    ['411', 'GLN Rechnungsempfänger', 'Bill to GLN', 13, 13, 'n'],
    ['412', 'GLN Lieferant', 'Purchased from GLN', 13, 13, 'n'],
    ['413', 'GLN Endempfänger', 'Ship for GLN', 13, 13, 'n'],
    ['414', 'GLN Standort', 'Physical location GLN', 13, 13, 'n'],
    ['415', 'GLN Rechnungssteller', 'Invoicing party GLN', 13, 13, 'n'],
    ['416', 'GLN Produktionsort', 'Production location GLN', 13, 13, 'n'],
    ['420', 'Postleitzahl Empfänger', 'Ship to postal code', 0, 20, 'an'],
    ['421', 'PLZ mit Ländercode', 'Ship to postal code with country', 0, 12, 'an'],
    ['422', 'Ursprungsland', 'Country of origin', 3, 3, 'n'],
    ['423', 'Land der ersten Verarbeitung', 'Country of initial processing', 0, 15, 'n'],
    ['424', 'Land der Verarbeitung', 'Country of processing', 3, 3, 'n'],
    ['425', 'Land der Zerlegung', 'Country of disassembly', 0, 15, 'n'],
    ['426', 'Land der gesamten Verarbeitung', 'Country of full process chain', 3, 3, 'n'],
    ['7001', 'NATO-Versorgungsnummer', 'NATO stock number', 13, 13, 'n'],
    ['7003', 'Ablaufdatum mit Uhrzeit', 'Expiry date and time', 10, 10, 'n'],
    ['7006', 'Erstes Einfrierdatum', 'First freeze date', 6, 6, 'date'],
    ['7007', 'Erntedatum', 'Harvest date', 0, 12, 'n'],
    ['7240', 'Protokoll-ID', 'Protocol ID', 0, 20, 'an'],
    ['8001', 'Rollenprodukt (Maße)', 'Roll products', 14, 14, 'n'],
    ['8002', 'Mobilfunk-Kennung', 'Cellular mobile ID', 0, 20, 'an'],
    ['8003', 'GRAI (Mehrwegbehälter)', 'GRAI (returnable asset)', 0, 30, 'an'],
    ['8004', 'GIAI (Inventarnummer)', 'GIAI (individual asset)', 0, 30, 'an'],
    ['8005', 'Preis pro Einheit', 'Price per unit', 6, 6, 'n'],
    ['8006', 'ITIP (Artikelteil)', 'ITIP (piece of item)', 18, 18, 'n'],
    ['8007', 'IBAN', 'IBAN', 0, 34, 'an'],
    ['8008', 'Produktionszeitpunkt', 'Production date and time', 0, 12, 'n'],
    ['8009', 'Sensor-Kennung', 'Sensor indicator', 0, 50, 'an'],
    ['8010', 'CPID (Komponente)', 'CPID (component)', 0, 30, 'an'],
    ['8011', 'CPID-Seriennummer', 'CPID serial', 0, 12, 'n'],
    ['8012', 'Softwareversion', 'Software version', 0, 20, 'an'],
    ['8013', 'GMN (Modellnummer)', 'GMN (model number)', 0, 25, 'an'],
    ['8017', 'GSRN Dienstleister', 'GSRN provider', 18, 18, 'n'],
    ['8018', 'GSRN Empfänger', 'GSRN recipient', 18, 18, 'n'],
    ['8019', 'Leistungsnummer', 'Service relation instance', 0, 10, 'n'],
    ['8020', 'Zahlungsreferenz', 'Payment slip reference', 0, 25, 'an'],
    ['8200', 'Produkt-URL', 'Extended packaging URL', 0, 70, 'an'],
    ['90', 'Intern (Absprache)', 'Mutually agreed', 0, 30, 'an'],
    ['91', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['92', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['93', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['94', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['95', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['96', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['97', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['98', 'Firmenintern', 'Company internal', 0, 90, 'an'],
    ['99', 'Firmenintern', 'Company internal', 0, 90, 'an'],
];
const BY_AI = {};
AIS.forEach(a => { BY_AI[a[0]] = a; });
const GS = '\x1d';

/** Findet die AI am Anfang eines Element-Strings. AIs mit Dezimalstellen haben 4 Stellen (z. B. 3103). */
function lookup(s) {
    for (const len of [4, 3, 2]) {
        const head = s.substr(0, len);
        if (len === 4 && BY_AI[head.substr(0, 3)] && BY_AI[head.substr(0, 3)][5] === 'dec' && /^\d{4}$/.test(head)) {
            return { ai: head, def: BY_AI[head.substr(0, 3)], decimals: +head[3] };
        }
        if (BY_AI[head]) return { ai: head, def: BY_AI[head], decimals: 0 };
    }
    return null;
}

function fmtDate(v, lang) {
    if (!/^\d{6}$/.test(v)) return v;
    const yy = +v.substr(0, 2), mm = +v.substr(2, 2);
    let dd = +v.substr(4, 2);
    // GS1: Jahrhundert so wählen, dass das Datum nicht mehr als 49 Jahre in der Zukunft liegt
    const now = new Date().getFullYear();
    let year = 2000 + yy;
    if (year - now > 50) year -= 100;
    if (dd === 0) dd = new Date(year, mm, 0).getDate(); // 00 = letzter Tag des Monats
    if (mm < 1 || mm > 12) return v;
    const d = new Date(year, mm - 1, dd);
    return d.toLocaleDateString(lang === 'en' ? 'en-GB' : 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function describe(ai, value, decimals, lang) {
    const def = (lookup(ai) || {}).def;
    let shown = value;
    if (def && def[5] === 'date') shown = fmtDate(value, lang);
    else if (def && def[5] === 'dec' && /^\d+$/.test(value) && (ai.length === 4)) {
        const d = decimals;
        const raw = ai.startsWith('391') || ai.startsWith('393') ? value.substr(3) : value;
        const cur = ai.startsWith('391') || ai.startsWith('393') ? ' (ISO ' + value.substr(0, 3) + ')' : '';
        const n = d > 0 ? raw.slice(0, raw.length - d).replace(/^0+(?=\d)/, '') + (lang === 'en' ? '.' : ',') + raw.slice(-d) : raw.replace(/^0+(?=\d)/, '');
        shown = n + cur;
    } else if (ai === '7003' && /^\d{10}$/.test(value)) {
        shown = fmtDate(value.substr(0, 6), lang) + ' ' + value.substr(6, 2) + ':' + value.substr(8, 2);
    }
    return { ai, name: def ? (lang === 'en' ? def[2] : def[1]) : (lang === 'en' ? 'Unknown AI' : 'Unbekannte AI'), value, shown, def };
}

/**
 * Zerlegt GS1-Daten. Erkennt drei Schreibweisen:
 *   (01)04012345678901(10)ABC         lesbare Form mit Klammern
 *   ]C1 / ]d2 / ]Q3 + 0104012345678901 10ABC<GS>...   Rohdaten mit Trennzeichen
 *   https://id.gs1.org/01/04012345678901/10/ABC?17=261231   GS1 Digital Link
 * Liefert null, wenn es nicht nach GS1 aussieht.
 */
function parse(text, lang, assumeGs1) {
    if (typeof text !== 'string' || !text) return null;
    let s = text;
    const out = [];
    // Lesbare Form
    if (/^\(\d{2,4}\)/.test(s)) {
        const re = /\((\d{2,4})\)([^(]*)/g;
        let m;
        while ((m = re.exec(s))) {
            const l = lookup(m[1] + '000000');
            out.push(describe(m[1], m[2], l && l.def[5] === 'dec' ? +m[1][3] || 0 : 0, lang));
        }
        return out.length ? { items: out, form: 'hri' } : null;
    }
    // Digital Link
    const dl = parseDigitalLink(s, lang);
    if (dl) return dl;
    // Rohdaten: Symbologie-Kennung entfernen
    let marked = false;
    const sid = /^\](C1|e0|d2|Q3|J1)/.exec(s);
    if (sid) { s = s.substr(3); marked = true; }
    if (s[0] === GS) { s = s.substr(1); marked = true; }
    if (!marked && !assumeGs1 && s.indexOf(GS) < 0) return null;
    while (s.length) {
        if (s[0] === GS) { s = s.substr(1); continue; }
        const l = lookup(s);
        if (!l) return out.length ? { items: out, form: 'raw', rest: s } : null;
        s = s.substr(l.ai.length);
        let value;
        if (l.def[3] > 0) { value = s.substr(0, l.def[3]); s = s.substr(l.def[3]); }
        else {
            const end = s.indexOf(GS);
            value = end < 0 ? s : s.substr(0, end);
            s = end < 0 ? '' : s.substr(end + 1);
        }
        out.push(describe(l.ai, value, l.decimals, lang));
    }
    return out.length ? { items: out, form: 'raw' } : null;
}

function parseDigitalLink(text, lang) {
    let u;
    try { u = new URL(text); } catch (e) { return null; }
    if (!/^https?:$/.test(u.protocol)) return null;
    const parts = u.pathname.split('/').filter(Boolean).map(p => { try { return decodeURIComponent(p); } catch (e) { return p; } });
    const idx = parts.findIndex((p, i) => (p === '01' || p === 'gtin') && /^\d{8,14}$/.test(parts[i + 1] || ''));
    if (idx < 0) return null;
    const items = [];
    const alias = { gtin: '01', lot: '10', ser: '21', cpv: '22' };
    for (let i = idx; i + 1 < parts.length; i += 2) {
        const ai = alias[parts[i]] || parts[i];
        if (!/^\d{2,4}$/.test(ai)) break;
        let v = parts[i + 1];
        if (ai === '01') v = v.padStart(14, '0');
        const l = lookup(ai + '0000000000');
        items.push(describe(ai, v, l && l.def[5] === 'dec' ? +ai[3] || 0 : 0, lang));
    }
    u.searchParams.forEach((v, k) => { if (/^\d{2,4}$/.test(k)) items.push(describe(k, v, 0, lang)); });
    return items.length ? { items, form: 'link', host: u.host } : null;
}

/**
 * Lesbare Form (01)...(10)... in Rohdaten umwandeln. Liefert eine Liste aus Zeichen und dem
 * Marker FNC1 (für Code 128) sowie den Text mit <GS> als Trenner. Wirft bei falschen Daten.
 */
function toElementString(hri) {
    const re = /\((\d{2,4})\)([^(]*)/g;
    let m, pos = 0;
    const parts = [];
    while ((m = re.exec(hri))) {
        if (m.index !== pos) throw gerr('GS1: Unerwartet: %s', hri.substr(pos, 10));
        pos = re.lastIndex;
        const l = lookup(m[1] + '0000000000');
        if (!l) throw gerr('GS1: Unbekannte AI %s', m[1]);
        const fixed = l.def[3];
        const val = m[2];
        if (fixed && val.length !== fixed) throw gerr('GS1: AI %s braucht %s Zeichen', m[1], fixed);
        if (!fixed && val.length > l.def[4]) throw gerr('GS1: AI %s höchstens %s Zeichen', m[1], l.def[4]);
        if ((l.def[5] === 'n' || l.def[5] === 'date' || l.def[5] === 'dec') && !/^\d*$/.test(val)) throw gerr('GS1: AI %s nur Ziffern', m[1]);
        parts.push({ ai: m[1], val, variable: !fixed });
    }
    if (!parts.length || pos !== hri.length) throw gerr('GS1: Daten bitte als (01)04012345678901(10)ABC eingeben');
    let text = '';
    parts.forEach((p, i) => { text += p.ai + p.val + (p.variable && i < parts.length - 1 ? GS : ''); });
    return { text, parts };
}

function gerr(msg, ...args) { const e = new Error(args.reduce((r, a) => r.replace('%s', a), msg)); e.tpl = msg; e.args = args; return e; }
function isHri(s) { return /^(\(\d{2,4}\)[^(]+)+$/.test(s); }

CF.GS1 = { parse, toElementString, isHri, lookup, describe, GS, AIS };
})(typeof self !== 'undefined' ? self : globalThis);
