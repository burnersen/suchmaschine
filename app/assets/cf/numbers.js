/* CodeForge – Nummern: Prüfziffern, Umrechnungen und der Zahlen-Detektiv
 *
 * EAN/GTIN, UPC-A/E, ISBN, ISSN, SSCC, IBAN, PZN, IMEI und Zahlungskarten (Luhn).
 * Alles läuft im Browser, nichts verlässt das Gerät.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

const digitsOnly = s => String(s || '').replace(/[\s\-.]/g, '');

/* ---------- Prüfziffern ---------- */

/** GTIN-Familie (EAN-8, UPC-A, EAN-13, GTIN-14, SSCC): Gewichte 3 und 1 von rechts */
function gtinCheck(body) {
    let sum = 0;
    for (let i = body.length - 1, w = 3; i >= 0; i--, w = 4 - w) sum += (+body[i]) * w;
    return (10 - sum % 10) % 10;
}
function gtinValid(code) { return /^\d{8,18}$/.test(code) && gtinCheck(code.slice(0, -1)) === +code.slice(-1); }
function gtinComplete(body) { return body + gtinCheck(body); }

function isbn10Check(body9) {
    let s = 0;
    for (let i = 0; i < 9; i++) s += (+body9[i]) * (10 - i);
    const c = (11 - s % 11) % 11;
    return c === 10 ? 'X' : String(c);
}
function isbn10Valid(code) { return /^\d{9}[\dX]$/i.test(code) && isbn10Check(code.slice(0, 9)) === code.slice(-1).toUpperCase(); }

function issnCheck(body7) {
    let s = 0;
    for (let i = 0; i < 7; i++) s += (+body7[i]) * (8 - i);
    const c = (11 - s % 11) % 11;
    return c === 10 ? 'X' : String(c);
}
function issnValid(code) { return /^\d{7}[\dX]$/i.test(code) && issnCheck(code.slice(0, 7)) === code.slice(-1).toUpperCase(); }

/** PZN-8: Gewichte 1 bis 7, Rest 10 ist ungültig */
function pznCheck(body7) {
    let s = 0;
    for (let i = 0; i < 7; i++) s += (+body7[i]) * (i + 1);
    return s % 11;
}
function pznValid(code) { return /^\d{8}$/.test(code) && pznCheck(code.slice(0, 7)) === +code[7]; }

function luhnValid(code) {
    if (!/^\d{2,}$/.test(code)) return false;
    let s = 0;
    for (let i = code.length - 1, dbl = false; i >= 0; i--, dbl = !dbl) {
        let d = +code[i];
        if (dbl) { d *= 2; if (d > 9) d -= 9; }
        s += d;
    }
    return s % 10 === 0;
}
function luhnCheck(body) {
    for (let c = 0; c < 10; c++) if (luhnValid(body + c)) return c;
    return 0;
}

/* ---------- IBAN ---------- */

const IBAN_LEN = { AD: 24, AE: 23, AL: 28, AT: 20, AZ: 28, BA: 20, BE: 16, BG: 22, BH: 22, BR: 29, BY: 28, CH: 21, CR: 22, CY: 28, CZ: 24, DE: 22, DK: 18, DO: 28, EE: 20, EG: 29, ES: 24, FI: 18, FO: 18, FR: 27, GB: 22, GE: 22, GI: 23, GL: 18, GR: 27, GT: 28, HR: 21, HU: 28, IE: 22, IL: 23, IQ: 23, IS: 26, IT: 27, JO: 30, KW: 30, KZ: 20, LB: 28, LC: 32, LI: 21, LT: 20, LU: 20, LV: 21, MC: 27, MD: 24, ME: 22, MK: 19, MR: 27, MT: 31, MU: 30, NL: 18, NO: 15, PK: 24, PL: 28, PS: 29, PT: 25, QA: 29, RO: 24, RS: 22, SA: 24, SC: 31, SE: 24, SI: 19, SK: 24, SM: 27, ST: 25, SV: 28, TL: 23, TN: 24, TR: 26, UA: 29, VA: 22, VG: 24, XK: 20 };

function ibanClean(s) { return String(s || '').replace(/\s+/g, '').toUpperCase(); }
function ibanValid(iban) {
    iban = ibanClean(iban);
    if (!/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/.test(iban)) return false;
    if (IBAN_LEN[iban.substr(0, 2)] && IBAN_LEN[iban.substr(0, 2)] !== iban.length) return false;
    const r = iban.substr(4) + iban.substr(0, 4);
    let rem = 0;
    for (const ch of r) {
        const v = ch >= 'A' ? (ch.charCodeAt(0) - 55).toString() : ch;
        for (const d of v) rem = (rem * 10 + +d) % 97;
    }
    return rem === 1;
}
function ibanFormat(iban) { return ibanClean(iban).replace(/(.{4})/g, '$1 ').trim(); }

/* ---------- Umrechnungen ---------- */

function upcEToA(e) {
    // e: 8 Stellen (Zahlensystem, 6 Ziffern, Prüfziffer) oder 6/7 Stellen
    e = digitsOnly(e);
    let ns = '0', body, check = null;
    if (e.length === 6) body = e;
    else if (e.length === 7) { ns = e[0]; body = e.substr(1); }
    else if (e.length === 8) { ns = e[0]; body = e.substr(1, 6); check = e[7]; }
    else return null;
    if (ns !== '0' && ns !== '1') return null;
    const d = body.split('');
    let mfr, prod;
    const last = +d[5];
    if (last <= 2) { mfr = d[0] + d[1] + d[5] + '00'; prod = '00' + d[2] + d[3] + d[4]; }
    else if (last === 3) { mfr = d[0] + d[1] + d[2] + '00'; prod = '000' + d[3] + d[4]; }
    else if (last === 4) { mfr = d[0] + d[1] + d[2] + d[3] + '0'; prod = '0000' + d[4]; }
    else { mfr = d[0] + d[1] + d[2] + d[3] + d[4]; prod = '0000' + d[5]; }
    const a11 = ns + mfr + prod;
    const a = a11 + gtinCheck(a11);
    return { upca: a, checkOk: check === null || check === a[11] };
}

function upcAToE(a) {
    a = digitsOnly(a);
    if (a.length === 11) a += gtinCheck(a);
    if (!/^\d{12}$/.test(a) || (a[0] !== '0' && a[0] !== '1')) return null;
    const ns = a[0], m = a.substr(1, 5), p = a.substr(6, 5), c = a[11];
    let e = null;
    if (/^\d\d[0-2]00$/.test(m) && /^00\d{3}$/.test(p)) e = m[0] + m[1] + p[2] + p[3] + p[4] + m[2];
    else if (/^\d\d\d00$/.test(m) && /^000\d\d$/.test(p)) e = m[0] + m[1] + m[2] + p[3] + p[4] + '3';
    else if (/^\d{4}0$/.test(m) && /^0000\d$/.test(p)) e = m[0] + m[1] + m[2] + m[3] + p[4] + '4';
    else if (/^0000[5-9]$/.test(p)) e = m + p[4];
    return e ? ns + e + c : null;
}

function isbn10To13(i10) {
    i10 = digitsOnly(i10).toUpperCase();
    if (!/^\d{9}[\dX]$/.test(i10)) return null;
    return gtinComplete('978' + i10.substr(0, 9));
}
function isbn13To10(i13) {
    i13 = digitsOnly(i13);
    if (!/^978\d{10}$/.test(i13)) return null; // 979 hat keine ISBN-10
    const b = i13.substr(3, 9);
    return b + isbn10Check(b);
}
function issnToEan(issn, variant) {
    issn = digitsOnly(issn).toUpperCase();
    if (!/^\d{7}[\dX]$/.test(issn)) return null;
    return gtinComplete('977' + issn.substr(0, 7) + (variant || '00'));
}
function eanToIssn(ean) {
    if (!/^977\d{10}$/.test(ean)) return null;
    const b = ean.substr(3, 7);
    return b.substr(0, 4) + '-' + b.substr(4) + issnCheck(b);
}

/** ISBN mit Bindestrichen ist ohne Bereichstabelle nicht eindeutig, wir gruppieren nur grob */
function isbnPretty(i13) { return i13.substr(0, 3) + '-' + i13.substr(3, 9) + '-' + i13.substr(12); }

/* ---------- GS1-Ländercodes (Präfix sagt, wo die Nummer vergeben wurde, nicht wo das Produkt herkommt) ---------- */

const PREFIX = [
    [0, 19, 'USA / Kanada', 'USA / Canada'], [20, 29, 'Interne Nummer (Händler, z. B. Gewichtsware)', 'In-store number (e.g. variable weight)'],
    [30, 39, 'USA (Arzneimittel)', 'USA (drugs)'], [40, 49, 'Interne Nummer', 'In-store number'], [50, 59, 'Gutschein (USA)', 'Coupon (USA)'],
    [60, 139, 'USA / Kanada', 'USA / Canada'], [200, 299, 'Interne Nummer (Händler, z. B. Gewichtsware)', 'In-store number (e.g. variable weight)'],
    [300, 379, 'Frankreich / Monaco', 'France / Monaco'], [380, 380, 'Bulgarien', 'Bulgaria'], [383, 383, 'Slowenien', 'Slovenia'], [385, 385, 'Kroatien', 'Croatia'],
    [387, 387, 'Bosnien-Herzegowina', 'Bosnia and Herzegovina'], [389, 389, 'Montenegro', 'Montenegro'], [400, 440, 'Deutschland', 'Germany'],
    [450, 459, 'Japan', 'Japan'], [460, 469, 'Russland', 'Russia'], [470, 470, 'Kirgisistan', 'Kyrgyzstan'], [471, 471, 'Taiwan', 'Taiwan'],
    [474, 474, 'Estland', 'Estonia'], [475, 475, 'Lettland', 'Latvia'], [476, 476, 'Aserbaidschan', 'Azerbaijan'], [477, 477, 'Litauen', 'Lithuania'],
    [478, 478, 'Usbekistan', 'Uzbekistan'], [479, 479, 'Sri Lanka', 'Sri Lanka'], [480, 480, 'Philippinen', 'Philippines'], [481, 481, 'Belarus', 'Belarus'],
    [482, 482, 'Ukraine', 'Ukraine'], [483, 483, 'Turkmenistan', 'Turkmenistan'], [484, 484, 'Moldau', 'Moldova'], [485, 485, 'Armenien', 'Armenia'],
    [486, 486, 'Georgien', 'Georgia'], [487, 487, 'Kasachstan', 'Kazakhstan'], [488, 488, 'Tadschikistan', 'Tajikistan'], [489, 489, 'Hongkong', 'Hong Kong'],
    [490, 499, 'Japan', 'Japan'], [500, 509, 'Großbritannien', 'United Kingdom'], [520, 521, 'Griechenland', 'Greece'], [528, 528, 'Libanon', 'Lebanon'],
    [529, 529, 'Zypern', 'Cyprus'], [530, 530, 'Albanien', 'Albania'], [531, 531, 'Nordmazedonien', 'North Macedonia'], [535, 535, 'Malta', 'Malta'],
    [539, 539, 'Irland', 'Ireland'], [540, 549, 'Belgien / Luxemburg', 'Belgium / Luxembourg'], [560, 560, 'Portugal', 'Portugal'], [569, 569, 'Island', 'Iceland'],
    [570, 579, 'Dänemark', 'Denmark'], [590, 590, 'Polen', 'Poland'], [594, 594, 'Rumänien', 'Romania'], [599, 599, 'Ungarn', 'Hungary'],
    [600, 601, 'Südafrika', 'South Africa'], [603, 603, 'Ghana', 'Ghana'], [608, 608, 'Bahrain', 'Bahrain'], [609, 609, 'Mauritius', 'Mauritius'],
    [611, 611, 'Marokko', 'Morocco'], [613, 613, 'Algerien', 'Algeria'], [615, 615, 'Nigeria', 'Nigeria'], [616, 616, 'Kenia', 'Kenya'],
    [619, 619, 'Tunesien', 'Tunisia'], [621, 621, 'Syrien', 'Syria'], [622, 622, 'Ägypten', 'Egypt'], [625, 625, 'Jordanien', 'Jordan'],
    [626, 626, 'Iran', 'Iran'], [627, 627, 'Kuwait', 'Kuwait'], [628, 628, 'Saudi-Arabien', 'Saudi Arabia'], [629, 629, 'Vereinigte Arabische Emirate', 'United Arab Emirates'],
    [640, 649, 'Finnland', 'Finland'], [690, 699, 'China', 'China'], [700, 709, 'Norwegen', 'Norway'], [729, 729, 'Israel', 'Israel'],
    [730, 739, 'Schweden', 'Sweden'], [740, 746, 'Mittelamerika', 'Central America'], [750, 750, 'Mexiko', 'Mexico'], [754, 755, 'Kanada', 'Canada'],
    [759, 759, 'Venezuela', 'Venezuela'], [760, 769, 'Schweiz / Liechtenstein', 'Switzerland / Liechtenstein'], [770, 771, 'Kolumbien', 'Colombia'],
    [773, 773, 'Uruguay', 'Uruguay'], [775, 775, 'Peru', 'Peru'], [777, 777, 'Bolivien', 'Bolivia'], [778, 779, 'Argentinien', 'Argentina'],
    [780, 780, 'Chile', 'Chile'], [784, 784, 'Paraguay', 'Paraguay'], [786, 786, 'Ecuador', 'Ecuador'], [789, 790, 'Brasilien', 'Brazil'],
    [800, 839, 'Italien', 'Italy'], [840, 849, 'Spanien / Andorra', 'Spain / Andorra'], [850, 850, 'Kuba', 'Cuba'], [858, 858, 'Slowakei', 'Slovakia'],
    [859, 859, 'Tschechien', 'Czechia'], [860, 860, 'Serbien', 'Serbia'], [865, 865, 'Mongolei', 'Mongolia'], [867, 867, 'Nordkorea', 'North Korea'],
    [868, 869, 'Türkei', 'Türkiye'], [870, 879, 'Niederlande', 'Netherlands'], [880, 880, 'Südkorea', 'South Korea'], [884, 884, 'Kambodscha', 'Cambodia'],
    [885, 885, 'Thailand', 'Thailand'], [888, 888, 'Singapur', 'Singapore'], [890, 890, 'Indien', 'India'], [893, 893, 'Vietnam', 'Vietnam'],
    [896, 896, 'Pakistan', 'Pakistan'], [899, 899, 'Indonesien', 'Indonesia'], [900, 919, 'Österreich', 'Austria'], [930, 939, 'Australien', 'Australia'],
    [940, 949, 'Neuseeland', 'New Zealand'], [950, 950, 'GS1 Global Office', 'GS1 Global Office'], [955, 955, 'Malaysia', 'Malaysia'],
    [958, 958, 'Macau', 'Macau'], [960, 969, 'GS1 UK (kurze Nummern)', 'GS1 UK (short numbers)'], [977, 977, 'Zeitschrift (ISSN)', 'Periodical (ISSN)'],
    [978, 979, 'Buch (ISBN) oder Noten (ISMN)', 'Book (ISBN) or sheet music (ISMN)'], [980, 980, 'Rückgabebeleg', 'Refund receipt'],
    [981, 984, 'Gutschein', 'Coupon'], [990, 999, 'Gutschein', 'Coupon'],
];
function gs1Prefix(gtin13, lang) {
    const p = +gtin13.substr(0, 3);
    for (const r of PREFIX) if (p >= r[0] && p <= r[1]) return lang === 'en' ? r[3] : r[2];
    return null;
}

/* ---------- Fehlersuche: welche Ziffer ist vermutlich falsch? ---------- */

/** Vertauschte Nachbarziffern, die eine gültige Nummer ergeben */
function transpositionFixes(code, valid) {
    const out = [];
    for (let i = 0; i + 1 < code.length; i++) {
        if (code[i] === code[i + 1]) continue;
        const c = code.substr(0, i) + code[i + 1] + code[i] + code.substr(i + 2);
        if (valid(c)) out.push(c);
    }
    return out;
}

/* ---------- Zahlen-Detektiv ---------- */

/**
 * Was könnte diese Nummer sein? Liefert eine Liste von Treffern:
 * { id, name, valid (true/false/null), details: [[Bezeichnung, Wert]], fixes: [..], convert: [[Bezeichnung, Wert, Format]] }
 * Bezeichnungen sind deutsch, die Oberfläche übersetzt sie.
 */
function detect(input, lang) {
    const raw = String(input || '').trim();
    const s = digitsOnly(raw);
    const up = s.toUpperCase();
    const out = [];
    if (!s) return out;
    const isDigits = /^\d+$/.test(s);

    const gtinHit = (id, name, code) => {
        const ok = gtinValid(code);
        const body = code.slice(0, -1);
        const hit = { id, name, valid: ok, details: [], fixes: [], convert: [] };
        hit.details.push(['Prüfziffer', ok ? code.slice(-1) + ' ✓' : code.slice(-1) + ' ✗ → ' + gtinCheck(body)]);
        if (!ok) {
            hit.fixes.push(['Mit richtiger Prüfziffer', body + gtinCheck(body)]);
            transpositionFixes(code, gtinValid).forEach(c => hit.fixes.push(['Zwei Ziffern vertauscht?', c]));
        }
        return hit;
    };

    if (isDigits && s.length === 13) {
        const h = gtinHit('ean13', 'EAN-13 / GTIN-13', s);
        const c = gs1Prefix(s, lang);
        if (c) h.details.push(['Nummer vergeben in', c]);
        if (/^97[89]/.test(s)) {
            h.details.push(['Art', s.startsWith('9790') ? 'ISMN (Noten)' : 'ISBN (Buch)']);
            const i10 = isbn13To10(s);
            if (i10 && h.valid) h.convert.push(['ISBN-10', i10, 'text']);
        }
        if (s.startsWith('977')) { const issn = eanToIssn(s); if (issn) h.convert.push(['ISSN', issn, 'text']); }
        if (/^2/.test(s)) h.details.push(['Hinweis', 'Interne Nummer: oft Gewichts- oder Preisware, Preis/Gewicht steckt in der Nummer']);
        if (s[0] === '0' && h.valid) h.convert.push(['UPC-A', s.substr(1), 'UPCA']);
        if (h.valid) { h.convert.push(['GTIN-14', '0' + s, 'ITF14']); }
        out.push(h);
    }
    if (isDigits && s.length === 12) {
        const h = gtinHit('upca', 'UPC-A (USA / Kanada)', s);
        if (h.valid) {
            h.convert.push(['EAN-13', '0' + s, 'EAN13']);
            const e = upcAToE(s);
            if (e) h.convert.push(['UPC-E', e, 'UPCE']);
        }
        out.push(h);
        if (luhnValid(s) && !h.valid) { /* nichts */ }
    }
    if (isDigits && s.length === 8) {
        const h = gtinHit('ean8', 'EAN-8 / GTIN-8', s);
        const c = gs1Prefix('0' + s, lang);
        if (c && h.valid) h.details.push(['Nummer vergeben in', gs1Prefix(s.substr(0, 3), lang) || c]);
        if (h.valid) h.convert.push(['GTIN-14', s.padStart(14, '0'), 'ITF14']);
        out.push(h);
        // UPC-E?
        if (s[0] === '0' || s[0] === '1') {
            const a = upcEToA(s);
            if (a) {
                out.push({ id: 'upce', name: 'UPC-E (kurze UPC)', valid: a.checkOk, details: [['Ausgeschrieben (UPC-A)', a.upca]], fixes: a.checkOk ? [] : [['Mit richtiger Prüfziffer', s.substr(0, 7) + a.upca[11]]], convert: a.checkOk ? [['UPC-A', a.upca, 'UPCA'], ['EAN-13', '0' + a.upca, 'EAN13']] : [] });
            }
        }
        // PZN
        const pz = { id: 'pzn', name: 'PZN (Pharmazentralnummer)', valid: pznValid(s), details: [['Prüfziffer', pznValid(s) ? s[7] + ' ✓' : s[7] + ' ✗']], fixes: [], convert: [] };
        if (pz.valid) pz.convert.push(['Als Code 39 (PZN)', '-' + s, 'Code39']);
        else {
            const c7 = pznCheck(s.substr(0, 7));
            if (c7 < 10) pz.fixes.push(['Mit richtiger Prüfziffer', s.substr(0, 7) + c7]);
        }
        out.push(pz);
    }
    if (/^\d{7}[\dX]$/i.test(up) && (up.length === 8)) {
        const ok = issnValid(up);
        const h = { id: 'issn', name: 'ISSN (Zeitschrift)', valid: ok, details: [['Schreibweise', up.substr(0, 4) + '-' + up.substr(4)]], fixes: [], convert: [] };
        if (ok) h.convert.push(['EAN-13 für den Strichcode', issnToEan(up), 'EAN13']);
        else h.fixes.push(['Mit richtiger Prüfziffer', up.substr(0, 7) + issnCheck(up.substr(0, 7))]);
        out.push(h);
    }
    if (/^\d{9}[\dX]$/i.test(up)) {
        const ok = isbn10Valid(up);
        const h = { id: 'isbn10', name: 'ISBN-10 (Buch, alt)', valid: ok, details: [['Prüfziffer', ok ? up[9] + ' ✓' : up[9] + ' ✗ → ' + isbn10Check(up.substr(0, 9))]], fixes: [], convert: [] };
        if (ok) { const i13 = isbn10To13(up); h.convert.push(['ISBN-13 / EAN-13', i13, 'EAN13']); }
        else {
            h.fixes.push(['Mit richtiger Prüfziffer', up.substr(0, 9) + isbn10Check(up.substr(0, 9))]);
            transpositionFixes(up, isbn10Valid).forEach(c => h.fixes.push(['Zwei Ziffern vertauscht?', c]));
        }
        out.push(h);
    }
    if (isDigits && s.length === 14) {
        const h = gtinHit('gtin14', 'GTIN-14 / ITF-14 (Umverpackung)', s);
        h.details.push(['Packungsstufe', s[0] === '0' ? '0 (Einzelartikel)' : s[0] === '9' ? '9 (variable Menge)' : s[0]]);
        if (h.valid && s[0] === '0') h.convert.push(['EAN-13 (Inhalt)', s.substr(1), 'EAN13']);
        if (h.valid) h.convert.push(['ITF-14', s, 'ITF14']);
        out.push(h);
    }
    if (isDigits && s.length === 18) {
        const h = gtinHit('sscc', 'SSCC (Versandeinheit, Palette)', s);
        if (h.valid) h.convert.push(['GS1-128', '(00)' + s, 'GS1-128']);
        out.push(h);
    }
    if (isDigits && s.length === 15) {
        const ok = luhnValid(s);
        out.push({ id: 'imei', name: 'IMEI (Handy-Seriennummer)', valid: ok, details: [['Gerätetyp (TAC)', s.substr(0, 8)], ['Seriennummer', s.substr(8, 6)]], fixes: ok ? [] : [['Mit richtiger Prüfziffer', s.substr(0, 14) + luhnCheck(s.substr(0, 14))]], convert: [] });
    }
    if (isDigits && s.length >= 13 && s.length <= 19 && luhnValid(s) && s.length !== 15) {
        const iss = /^4/.test(s) ? 'Visa' : /^(5[1-5]|2[2-7])/.test(s) ? 'Mastercard' : /^3[47]/.test(s) ? 'American Express' : /^6/.test(s) ? 'Maestro / Discover' : null;
        if (iss) out.push({ id: 'card', name: 'Zahlungskarte', valid: true, details: [['Anbieter', iss], ['Hinweis', 'Kartennummern niemals in fremde QR-Codes oder Formulare tippen']], fixes: [], convert: [] });
    }
    if (/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/.test(up)) {
        const ok = ibanValid(up);
        const h = { id: 'iban', name: 'IBAN (Bankkonto)', valid: ok, details: [['Schreibweise', ibanFormat(up)], ['Land', up.substr(0, 2)]], fixes: [], convert: [] };
        if (up.startsWith('DE') && up.length === 22) { h.details.push(['Bankleitzahl', up.substr(4, 8)]); h.details.push(['Kontonummer', up.substr(12).replace(/^0+/, '')]); }
        if (!ok) transpositionFixes(up, ibanValid).forEach(c => h.fixes.push(['Zwei Zeichen vertauscht?', ibanFormat(c)]));
        if (ok) h.convert.push(['Als GiroCode erstellen', up, 'girocode']);
        out.push(h);
    }
    return out;
}

CF.Num = {
    digitsOnly, gtinCheck, gtinValid, gtinComplete, isbn10Check, isbn10Valid, isbn10To13, isbn13To10, isbnPretty,
    issnCheck, issnValid, issnToEan, eanToIssn, pznCheck, pznValid, luhnValid, luhnCheck,
    ibanValid, ibanFormat, ibanClean, upcEToA, upcAToE, gs1Prefix, detect,
};
})(typeof self !== 'undefined' ? self : globalThis);
