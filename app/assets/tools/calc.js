/* Suchmaschine – Rechnen und Umrechnen im Browser: Rechner (beim Tippen, Verlauf, wissenschaftlich), Prozent und MwSt,
   Einheiten (live), Währungen (EZB-Kurse, mehrere Ziele, Kursverlauf), Zinsen/Sparplan/Kredit, Sprit und Rechnung teilen, Römische Zahlen, Morse */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

const DE = CF.lang !== 'en';
/** Zahl lesen: Komma oder Punkt, Tausenderpunkte, Leerzeichen */
function num(s) {
    s = String(s == null ? '' : s).trim().replace(/[\s'’]/g, '');
    if (!s) return NaN;
    const dot = s.lastIndexOf('.'), com = s.lastIndexOf(',');
    if (dot >= 0 && com >= 0) s = dot > com ? s.replace(/,/g, '') : s.replace(/\./g, '').replace(',', '.');
    else if (com >= 0) s = (s.split(',').length > 2 || /,\d{3}$/.test(s) && !DE) ? s.replace(/,/g, '') : s.replace(',', '.');
    else if (dot >= 0 && s.split('.').length > 2) s = s.replace(/\./g, '');
    return /^-?\d*\.?\d+(e-?\d+)?$/i.test(s) ? parseFloat(s) : NaN;
}
/** Zahl hübsch: bis zu 10 Nachkommastellen, ohne Rundungsrauschen */
function fmt(v, maxFrac) {
    if (!isFinite(v)) return isNaN(v) ? '–' : (v > 0 ? '∞' : '−∞');
    if (Math.abs(v) >= 1e15 || (Math.abs(v) < 1e-7 && v !== 0)) return v.toExponential(8).replace(/\.?0+e/, 'e').replace('.', DE ? ',' : '.');
    return v.toLocaleString(CF.locale(), { maximumFractionDigits: maxFrac == null ? 10 : maxFrac });
}
const money = v => isFinite(v) ? v.toLocaleString(CF.locale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '–';
const numIn = (val, ph, extra) => { const i = input(Object.assign({ value: val == null ? '' : String(val).replace('.', DE ? ',' : '.'), inputmode: 'decimal', placeholder: ph || '', autocomplete: 'off' }, extra || {})); return i; };
function kvBig(rows) {
    const g = el('div', { class: 'kv' });
    rows.filter(Boolean).forEach(([k, v, strong]) => g.append(el('div', { class: 'k', text: k }), el('div', { class: 'v' + (strong ? ' strong' : ''), text: v }), el('div', { class: 'a' }, ibtn('copy', T('Kopieren'), () => CF.copy(v), 'sm'))));
    return g;
}
const two = (a, b) => el('div', { class: 'layout' }, el('div', { class: 'stack lg' }, a), el('div', { class: 'stack lg sticky' }, b));

/* ---------- Rechner: eigener Parser, kein eval ---------- */
const FUNCS = {
    sqrt: Math.sqrt, wurzel: Math.sqrt, cbrt: Math.cbrt, abs: Math.abs, exp: Math.exp, ln: Math.log, log: Math.log10, lg: Math.log10, log2: Math.log2,
    round: Math.round, floor: Math.floor, ceil: Math.ceil, sign: Math.sign,
};
const TRIG = { sin: Math.sin, cos: Math.cos, tan: Math.tan, asin: Math.asin, acos: Math.acos, atan: Math.atan, sinh: Math.sinh, cosh: Math.cosh, tanh: Math.tanh };
function fact(n) { if (n < 0 || n !== Math.floor(n)) return gamma(n + 1); if (n > 170) return Infinity; let r = 1; for (let i = 2; i <= n; i++) r *= i; return r; }
function gamma(z) { if (z < .5) return Math.PI / (Math.sin(Math.PI * z) * gamma(1 - z)); z -= 1; const g = 7, c = [.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313, -176.61502916214059, 12.507343278686905, -.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7]; let x = c[0]; for (let i = 1; i < g + 2; i++) x += c[i] / (z + i); const t = z + g + .5; return Math.sqrt(2 * Math.PI) * Math.pow(t, z + .5) * Math.exp(-t) * x; }

function evaluate(src, env) {
    let s = String(src).replace(/×/g, '*').replace(/÷/g, '/').replace(/−/g, '-').replace(/π/g, 'pi').replace(/√/g, 'sqrt').trim();
    // „15% von 80“, „15 % of 80“
    s = s.replace(/([\d.,]+)\s*%\s*(von|of)\s*/gi, '($1/100)*');
    // Dezimalkomma: „3,5“ → 3.5 (Komma als Trenner nur in Funktionen mit mehreren Argumenten gibt es hier nicht)
    s = s.replace(/(\d)\.(?=\d{3}(\D|$))/g, DE ? '$1' : '$1.').replace(/(\d),(\d)/g, '$1.$2');
    let i = 0;
    const peek = () => s[i];
    const ws = () => { while (s[i] === ' ') i++; };
    const err = m => { const e = new Error(m); throw e; };
    function primary() {
        ws();
        const c = peek();
        if (c === '(') { i++; const v = expr(); ws(); if (s[i] !== ')') err(T('Klammer fehlt')); i++; return { v }; }
        if (c === '-') { i++; const p = power(); return { v: -p.v }; }
        if (c === '+') { i++; return power(); }
        const m = /^(\d*\.?\d+(?:e[+-]?\d+)?)/i.exec(s.slice(i));
        if (m) { i += m[1].length; return { v: parseFloat(m[1]) }; }
        const w = /^([a-zäöü_][a-zäöü0-9_]*)/i.exec(s.slice(i));
        if (w) {
            const name = w[1].toLowerCase();
            i += w[1].length;
            if (name === 'pi') return { v: Math.PI };
            if (name === 'e') return { v: Math.E };
            if (name === 'tau') return { v: 2 * Math.PI };
            if (name === 'ans') return { v: env.ans || 0 };
            if (env.vars && env.vars[name] !== undefined) return { v: env.vars[name] };
            const f = FUNCS[name] || TRIG[name];
            if (!f) err(T('Unbekannt: %s', name));
            ws();
            const arg = s[i] === '(' ? primary().v : power().v;
            if (TRIG[name]) {
                if (/^a/.test(name) && !/h$/.test(name)) return { v: env.deg ? f(arg) * 180 / Math.PI : f(arg) };
                return { v: f(env.deg && !/h$/.test(name) ? arg * Math.PI / 180 : arg) };
            }
            return { v: f(arg) };
        }
        err(c ? T('Unerwartetes Zeichen: %s', c) : T('Rechnung unvollständig'));
    }
    function postfix() {
        let p = primary();
        for (;;) {
            ws();
            if (s[i] === '!') { i++; p = { v: fact(p.v) }; continue; }
            if (s[i] === '%') { i++; p = { v: p.v / 100, pct: true }; continue; }
            return p;
        }
    }
    function power() {
        const b = postfix();
        ws();
        if (s[i] === '^' || (s[i] === '*' && s[i + 1] === '*')) { i += s[i] === '^' ? 1 : 2; const e = power(); return { v: Math.pow(b.v, e.v) }; }
        return b;
    }
    function term() {
        let a = power();
        for (;;) {
            ws();
            const c = s[i];
            if (c === '*' || c === '/' || c === ':') { i++; const b = power(); a = { v: c === '*' ? a.v * b.v : a.v / b.v }; continue; }
            if (c && /[a-z(]/i.test(c) && c !== 'm') { const b = power(); a = { v: a.v * b.v }; continue; }   // 2pi, 3(4+5)
            if (s.slice(i, i + 3) === 'mod') { i += 3; const b = power(); a = { v: ((a.v % b.v) + b.v) % b.v }; continue; }
            return a;
        }
    }
    function expr() {
        let a = term();
        for (;;) {
            ws();
            const c = s[i];
            if (c !== '+' && c !== '-') return a.v;
            i++;
            const b = term();
            // „200 + 19%“: Prozent vom linken Wert
            const bv = b.pct ? a.v * b.v : b.v;
            a = { v: c === '+' ? a.v + bv : a.v - bv };
        }
    }
    if (!s) return NaN;
    const v = expr();
    ws();
    if (i < s.length) err(T('Unerwartetes Zeichen: %s', s[i]));
    return v;
}
CF.calc = evaluate;

CF.views.rechner = {
    mount(root, params) {
        const env = { ans: CF.ls.get('calc.ans', 0), deg: CF.ls.get('calc.deg', true) };
        let hist = CF.ls.get('calc.hist', []);
        const inp = input({ class: 'in big mono calc-in', value: params.x || '', placeholder: '(12,5 + 7) * 3', autocomplete: 'off', spellcheck: 'false', inputmode: 'decimal' });
        const res = el('div', { class: 'calc-res' });
        const histBox = el('div', { class: 'stack' });
        function live() {
            const v = inp.value.trim();
            if (!v) { fill(res, el('span', { class: 'faint', text: '0' })); return null; }
            try { const r = evaluate(v, env); fill(res, el('span', { text: '= ' + fmt(r) })); return r; } catch (e) { fill(res, el('span', { class: 'faint small', text: e.message })); return null; }
        }
        function commit() {
            const r = live();
            if (r === null || !isFinite(r)) return;
            env.ans = r;
            CF.ls.set('calc.ans', r);
            hist = [{ e: inp.value.trim(), r }].concat(hist.filter(h => h.e !== inp.value.trim())).slice(0, 30);
            CF.ls.set('calc.hist', hist);
            renderHist();
            inp.value = String(fmt(r)).replace(/\s/g, '');
            CF.focus(inp, true);
        }
        function renderHist() {
            fill(histBox, hist.length ? [hist.map(h => el('button', { type: 'button', class: 'calc-h', onclick: () => { inp.value = h.e; live(); CF.focus(inp); } }, el('span', { class: 'mono', text: h.e }), el('b', { class: 'mono', text: '= ' + fmt(h.r) }))),
                btn(T('Verlauf löschen'), 'btn-ghost btn-sm', () => { hist = []; CF.ls.set('calc.hist', []); renderHist(); }, 'trash')] : el('p', { class: 'small muted', text: T('Noch nichts gerechnet. Enter speichert eine Rechnung im Verlauf.') }));
        }
        const put = k => {
            const st = inp.selectionStart == null ? inp.value.length : inp.selectionStart, en = inp.selectionEnd == null ? st : inp.selectionEnd;
            if (k === 'C') inp.value = '';
            else if (k === '⌫') { inp.value = inp.value.slice(0, Math.max(0, st - 1)) + inp.value.slice(en); }
            else if (k === '=') { commit(); return; }
            else { inp.value = inp.value.slice(0, st) + k + inp.value.slice(en); const p = st + k.length; setTimeout(() => inp.setSelectionRange(p, p), 0); }
            live();
            CF.focus(inp);   // am Handy keine Tastatur, nur weil eine Taste des Rechners gedrückt wurde
        };
        const key = (k, cls, label) => el('button', { type: 'button', class: 'calc-k ' + (cls || ''), onclick: () => put(k), text: label || k });
        const sci = [['sin(', 'sin'], ['cos(', 'cos'], ['tan(', 'tan'], ['ln(', 'ln'], ['log(', 'log'], ['sqrt(', '√'], ['^', 'xʸ'], ['^2', 'x²'], ['!', 'n!'], ['pi', 'π'], ['e', 'e'], ['ans', 'Ans'], ['asin(', 'sin⁻¹'], ['acos(', 'cos⁻¹'], ['atan(', 'tan⁻¹']];
        const pad = el('div', { class: 'calc-pad' },
            ['7', '8', '9', '/', '(', '4', '5', '6', '*', ')', '1', '2', '3', '-', '%', '0', DE ? ',' : '.', '⌫', '+', 'C'].map(k => key(k, /[\/*\-+%()]/.test(k) ? 'op' : k === 'C' || k === '⌫' ? 'clr' : '', { '/': '÷', '*': '×', '-': '−' }[k])),
            key('=', 'eq'));
        const sciPad = el('div', { class: 'calc-pad calc-pad--sci' }, sci.map(([k, l]) => key(k, 'fn', l)));
        const degSeg = CF.seg([['deg', T('Grad')], ['rad', 'Rad']], env.deg ? 'deg' : 'rad', v => { env.deg = v === 'deg'; CF.ls.set('calc.deg', env.deg); live(); });
        inp.addEventListener('input', live);
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } else if (e.key === 'Escape') { inp.value = ''; live(); } });
        fill(root, two(
            [el('div', { class: 'calc-screen' }, inp, res), el('div', { class: 'row between' }, degSeg, el('span', { class: 'tiny faint', text: T('Enter = ausrechnen und merken') })), pad, el('details', { class: 'adv', open: CF.ls.get('calc.sci', false) ? true : null, ontoggle: e => CF.ls.set('calc.sci', e.target.open) }, el('summary', {}, T('Wissenschaftlich')), sciPad)],
            [CF.card(T('Verlauf'), 'history', histBox), CF.card(T('Beispiele'), 'sparkles', el('div', { class: 'chips' }, ['200 + 19%', DE ? '15% von 80' : '15% of 80', '2^10', 'sqrt(2)', '5!', 'sin(30)', '(1+0,05)^10', '17 mod 5'].map(x => el('button', { type: 'button', class: 'chip mono', text: x, onclick: () => { inp.value = x; live(); } }))))]));
        renderHist();
        live();
        setTimeout(() => CF.focus(inp), 30);
    },
};

/* ---------- Prozent und MwSt ---------- */
CF.views.prozent = {
    mount(root) {
        let mode = CF.ls.get('pct.mode', 'vat');
        const box = el('div', { class: 'stack' });
        const out = el('div');
        function render() {
            CF.ls.set('pct.mode', mode);
            const forms = {
                vat: () => {
                    const amt = numIn(CF.ls.get('pct.amt', '100'));
                    let rate = CF.ls.get('pct.rate', 19), dir = CF.ls.get('pct.dir', 'net');
                    const rateIn = numIn(rate, '', { style: { width: '6rem' } });
                    const calc = () => {
                        const a = num(amt.value), r = num(rateIn.value);
                        CF.ls.set('pct.amt', amt.value); CF.ls.set('pct.rate', r);
                        if (!isFinite(a) || !isFinite(r)) { fill(out); return; }
                        const net = dir === 'net' ? a : a / (1 + r / 100), gross = dir === 'net' ? a * (1 + r / 100) : a;
                        fill(out, kvBig([[T('Netto'), money(net)], [T('MwSt (%s %)', fmt(r)), money(gross - net)], [T('Brutto'), money(gross), true]]));
                    };
                    [amt, rateIn].forEach(x => x.addEventListener('input', calc));
                    setTimeout(calc, 0);
                    return [field(T('Betrag'), amt), field(T('Der Betrag ist'), CF.seg([['net', T('netto')], ['gross', T('brutto')]], dir, v => { dir = v; CF.ls.set('pct.dir', v); calc(); })),
                        field(T('Steuersatz'), el('div', { class: 'row' }, rateIn, el('div', { class: 'chips' }, [19, 7, 0, 20, 8.1, 21].map(x => el('button', { type: 'button', class: 'chip', text: fmt(x) + ' %', onclick: () => { rateIn.value = fmt(x); calc(); } })))))];
                },
                discount: () => {
                    const price = numIn(CF.ls.get('pct.price', '79,90')), off = numIn(CF.ls.get('pct.off', '25'));
                    const calc = () => { const p = num(price.value), o = num(off.value); CF.ls.set('pct.price', price.value); CF.ls.set('pct.off', off.value); if (!isFinite(p) || !isFinite(o)) { fill(out); return; } fill(out, kvBig([[T('Ersparnis'), money(p * o / 100)], [T('Neuer Preis'), money(p * (1 - o / 100)), true]])); };
                    [price, off].forEach(x => x.addEventListener('input', calc)); setTimeout(calc, 0);
                    return [field(T('Preis'), price), field(T('Rabatt in %'), off)];
                },
                change: () => {
                    const a = numIn(CF.ls.get('pct.a', '80')), b = numIn(CF.ls.get('pct.b', '100'));
                    const calc = () => { const x = num(a.value), y = num(b.value); CF.ls.set('pct.a', a.value); CF.ls.set('pct.b', b.value); if (!isFinite(x) || !isFinite(y) || !x) { fill(out); return; }
                        fill(out, kvBig([[T('Veränderung'), (y >= x ? '+' : '') + fmt((y - x) / x * 100, 2) + ' %', true], [T('Unterschied'), fmt(y - x)], [T('%s ist von %s', fmt(x), fmt(y)), fmt(x / y * 100, 2) + ' %']])); };
                    [a, b].forEach(x => x.addEventListener('input', calc)); setTimeout(calc, 0);
                    return [field(T('Alter Wert'), a), field(T('Neuer Wert'), b)];
                },
                part: () => {
                    const p = numIn(CF.ls.get('pct.p', '15')), g = numIn(CF.ls.get('pct.g', '80'));
                    const calc = () => { const x = num(p.value), y = num(g.value); CF.ls.set('pct.p', p.value); CF.ls.set('pct.g', g.value); if (!isFinite(x) || !isFinite(y)) { fill(out); return; }
                        fill(out, kvBig([[T('%s % von %s', fmt(x), fmt(y)), fmt(x * y / 100), true], [T('%s ist wie viel % von %s?', fmt(x), fmt(y)), y ? fmt(x / y * 100, 4) + ' %' : '–'], [T('%s sind %s %, das Ganze ist', fmt(y), fmt(x)), x ? fmt(y / x * 100) : '–']])); };
                    [p, g].forEach(x => x.addEventListener('input', calc)); setTimeout(calc, 0);
                    return [field(T('Zahl A (Prozent)'), p), field(T('Zahl B (Grundwert)'), g)];
                },
                rule: () => {
                    const a = numIn(CF.ls.get('pct.ra', '3')), b = numIn(CF.ls.get('pct.rb', '4,50')), c = numIn(CF.ls.get('pct.rc', '7'));
                    const calc = () => { const x = num(a.value), y = num(b.value), z = num(c.value); CF.ls.set('pct.ra', a.value); CF.ls.set('pct.rb', b.value); CF.ls.set('pct.rc', c.value); if (![x, y, z].every(isFinite) || !x) { fill(out); return; }
                        fill(out, kvBig([[T('Ergebnis'), fmt(y / x * z), true]]), el('p', { class: 'small muted', text: T('%s entspricht %s, also entspricht %s genau %s.', fmt(x), fmt(y), fmt(z), fmt(y / x * z)) })); };
                    [a, b, c].forEach(x => x.addEventListener('input', calc)); setTimeout(calc, 0);
                    return [el('div', { class: 'grid2' }, field(T('Wenn'), a), field(T('entspricht'), b)), field(T('dann entspricht'), c)];
                },
            };
            fill(box, forms[mode]());
        }
        fill(root, two([CF.seg([['vat', T('MwSt')], ['discount', T('Rabatt')], ['change', T('Veränderung')], ['part', T('Prozent von')], ['rule', T('Dreisatz')]], mode, v => { mode = v; render(); }), box], [CF.card(T('Ergebnis'), 'sparkles', out)]));
        render();
    },
};

/* ---------- Einheiten ---------- */
const U = {
    len: [T('Länge'), [['mm', 1e-3], ['cm', .01], ['dm', .1], ['m', 1], ['km', 1000], ['in', .0254, T('Zoll')], ['ft', .3048, T('Fuß')], ['yd', .9144, 'Yard'], ['mi', 1609.344, T('Meile')], ['nmi', 1852, T('Seemeile')], ['ly', 9.4607e15, T('Lichtjahr')]]],
    mass: [T('Gewicht'), [['mg', 1e-6], ['g', 1e-3], ['kg', 1], ['t', 1000], ['oz', .028349523125, T('Unze')], ['lb', .45359237, T('Pfund (US)')], ['st', 6.35029318, 'Stone'], ['ct', 2e-4, T('Karat')], ['Pfd', .5, T('Pfund (500 g)')]]],
    temp: [T('Temperatur'), [['°C'], ['°F'], ['K']]],
    vol: [T('Volumen'), [['ml', 1e-3], ['cl', .01], ['dl', .1], ['l', 1], ['m³', 1000], ['TL', .005, T('Teelöffel')], ['EL', .015, T('Esslöffel')], ['cup', .2365882365, T('Tasse (US)')], ['fl oz', .0295735295625], ['pt', .473176473, 'Pint (US)'], ['qt', .946352946, 'Quart (US)'], ['gal', 3.785411784, T('Gallone (US)')], ['gal UK', 4.54609, T('Gallone (UK)')], ['bbl', 158.987294928, T('Barrel')]]],
    area: [T('Fläche'), [['cm²', 1e-4], ['m²', 1], ['a', 100, 'Ar'], ['ha', 1e4], ['km²', 1e6], ['ft²', .09290304], ['acre', 4046.8564224], ['mi²', 2589988.110336], [T('Fußballfeld'), 7140]]],
    speed: [T('Geschwindigkeit'), [['m/s', 1], ['km/h', 1 / 3.6], ['mph', .44704], ['kn', 1852 / 3600, T('Knoten')], ['ft/s', .3048], ['Mach', 343]]],
    time: [T('Zeit'), [['ms', .001], ['s', 1], ['min', 60], ['h', 3600], [T('Tag'), 86400], [T('Woche'), 604800], [T('Monat'), 2629800], [T('Jahr'), 31557600]]],
    data: [T('Datenmengen'), [['bit', .125], ['B', 1], ['kB', 1e3], ['MB', 1e6], ['GB', 1e9], ['TB', 1e12], ['PB', 1e15], ['KiB', 1024], ['MiB', 1048576], ['GiB', 1073741824], ['TiB', 1099511627776], ['Mbit', 125000], ['Gbit', 125000000]]],
    rate: [T('Datenrate'), [['kbit/s', 125], ['Mbit/s', 125000], ['Gbit/s', 125000000], ['kB/s', 1000], ['MB/s', 1e6], ['GB/s', 1e9]]],
    fuel: [T('Verbrauch'), [['l/100km'], ['km/l'], ['mpg US'], ['mpg UK']]],
    press: [T('Druck'), [['Pa', 1], ['hPa', 100], ['kPa', 1000], ['bar', 1e5], ['mbar', 100], ['psi', 6894.757293168], ['atm', 101325], ['mmHg', 133.322387415]]],
    energy: [T('Energie'), [['J', 1], ['kJ', 1000], ['cal', 4.184], ['kcal', 4184], ['Wh', 3600], ['kWh', 3.6e6], ['BTU', 1055.05585262], ['eV', 1.602176634e-19]]],
    power: [T('Leistung'), [['W', 1], ['kW', 1000], ['MW', 1e6], ['PS', 735.49875], ['hp', 745.69987158227]]],
    angle: [T('Winkel'), [['°', Math.PI / 180, T('Grad')], ['rad', 1], ['gon', Math.PI / 200], ['′', Math.PI / 10800, T('Bogenminute')], [T('Umdrehung'), 2 * Math.PI]]],
};
function convUnit(g, v, from, to) {
    if (g === 'temp') {
        const c = from === '°C' ? v : from === '°F' ? (v - 32) * 5 / 9 : v - 273.15;
        return to === '°C' ? c : to === '°F' ? c * 9 / 5 + 32 : c + 273.15;
    }
    if (g === 'fuel') {
        const toL = { 'l/100km': x => x, 'km/l': x => 100 / x, 'mpg US': x => 235.214583 / x, 'mpg UK': x => 282.480936 / x };
        const fromL = { 'l/100km': x => x, 'km/l': x => 100 / x, 'mpg US': x => 235.214583 / x, 'mpg UK': x => 282.480936 / x };
        return fromL[to](toL[from](v));
    }
    const f = U[g][1].find(u => u[0] === from)[1], t = U[g][1].find(u => u[0] === to)[1];
    return v * f / t;
}
CF.views.einheiten = {
    mount(root, params) {
        let g = CF.ls.get('unit.g', 'len');
        const val = numIn(params.n || CF.ls.get('unit.v', '1'), '', { class: 'in big' });
        const fromBox = el('div'), toBox = el('div');
        let from, to;
        const out = el('div', { class: 'big-num' });
        const table = el('div');
        function sel(cur, cb) { return select(U[g][1].map(u => [u[0], u[2] ? u[0] + ' · ' + u[2] : u[0]]), cur, cb, { class: 'in big' }); }
        function setup() {
            CF.ls.set('unit.g', g);
            const list = U[g][1].map(u => u[0]);
            const saved = CF.ls.get('unit.' + g, null);
            from = saved && list.indexOf(saved[0]) >= 0 ? saved[0] : list[g === 'temp' ? 0 : Math.min(3, list.length - 2)];
            to = saved && list.indexOf(saved[1]) >= 0 ? saved[1] : list[g === 'temp' ? 1 : Math.min(4, list.length - 1)];
            if (params.from && list.indexOf(params.from) >= 0) from = params.from;
            if (params.to && list.indexOf(params.to) >= 0) to = params.to;
            fill(fromBox, sel(from, v => { from = v; calc(); }));
            fill(toBox, sel(to, v => { to = v; calc(); }));
            calc();
        }
        function calc() {
            CF.ls.set('unit.' + g, [from, to]); CF.ls.set('unit.v', val.value);
            const v = num(val.value);
            if (!isFinite(v)) { out.textContent = '–'; fill(table); return; }
            const r = convUnit(g, v, from, to);
            out.textContent = fmt(v) + ' ' + from + ' = ' + fmt(r, Math.abs(r) >= 100 ? 4 : 8) + ' ' + to;
            fill(table, el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' }, U[g][1].map(u => el('tr', { class: u[0] === to ? 'is-cur' : '' },
                el('td', { text: u[2] || u[0] }), el('td', { class: 'num mono', text: fmt(convUnit(g, v, from, u[0]), 8) }), el('td', { class: 'mono', text: u[0] }))))));
        }
        val.addEventListener('input', calc);
        const swap = ibtn('refresh', T('Tauschen'), () => { [from, to] = [to, from]; fromBox.firstChild.value = from; toBox.firstChild.value = to; calc(); });
        fill(root, two([field(T('Art'), select(Object.entries(U).map(([k, v]) => [k, v[0]]), g, v => { g = v; setup(); }, { class: 'in' })),
            field(T('Wert'), val), el('div', { class: 'unit-row' }, field(T('von'), fromBox), swap, field(T('in'), toBox)), CF.card(T('Ergebnis'), 'sparkles', out)],
            [CF.card(T('Alle Einheiten'), 'list', table)]));
        setup();
    },
};

/* ---------- Währungen ---------- */
function chartSvg(points) {
    if (!points.length) return '';
    const W = 640, H = 220, pad = 34;
    const vals = points.map(p => p[1]);
    const lo = Math.min(...vals), hi = Math.max(...vals), span = hi - lo || hi * .01 || 1;
    const x = i => pad + (W - pad - 8) * i / Math.max(1, points.length - 1);
    const y = v => 10 + (H - 30) * (1 - (v - lo) / span);
    const line = points.map((p, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p[1]).toFixed(1)).join('');
    const area = line + 'L' + x(points.length - 1).toFixed(1) + ' ' + (H - 20) + 'L' + pad + ' ' + (H - 20) + 'Z';
    const lab = v => v.toLocaleString(CF.locale(), { maximumSignificantDigits: 5 });
    return '<svg class="chart" viewBox="0 0 ' + W + ' ' + H + '" role="img"><path class="g" d="M' + pad + ' 10H' + W + 'M' + pad + ' ' + (H - 20) + 'H' + W + '"/>'
        + '<path class="a" d="' + area + '"/><path class="l" d="' + line + '"/>'
        + '<text x="2" y="16">' + lab(hi) + '</text><text x="2" y="' + (H - 22) + '">' + lab(lo) + '</text>'
        + '<text x="' + pad + '" y="' + (H - 4) + '">' + points[0][0] + '</text><text x="' + (W - 8) + '" y="' + (H - 4) + '" text-anchor="end">' + points[points.length - 1][0] + '</text></svg>';
}
const CUR_NAMES = { EUR: 'Euro', USD: 'US-Dollar', GBP: T('Britisches Pfund'), CHF: T('Schweizer Franken'), JPY: 'Yen', CNY: 'Renminbi', PLN: 'Złoty', CZK: T('Tschechische Krone'), SEK: T('Schwedische Krone'), NOK: T('Norwegische Krone'), DKK: T('Dänische Krone'), HUF: 'Forint', TRY: T('Türkische Lira'), AUD: T('Australischer Dollar'), CAD: T('Kanadischer Dollar') };
CF.views.waehrung = {
    mount(root, params) {
        const data = CF.boot.data || {};
        const rates = data.rates || { EUR: 1 };
        const codes = Object.keys(rates).sort((a, b) => a === 'EUR' ? -1 : b === 'EUR' ? 1 : a.localeCompare(b));
        let from = (params.from || CF.ls.get('fx.from', 'EUR')).toUpperCase();
        let targets = CF.ls.get('fx.to', ['USD', 'GBP', 'CHF']);
        if (params.to) targets = [params.to.toUpperCase()].concat(targets.filter(x => x !== params.to.toUpperCase())).slice(0, 8);
        const amt = numIn(params.n || CF.ls.get('fx.amt', '100'), '', { class: 'in big' });
        const fromSel = select(codes.map(c => [c, c + (CUR_NAMES[c] ? ' · ' + CUR_NAMES[c] : '')]), rates[from] ? from : 'EUR', v => { from = v; calc(); }, { class: 'in big' });
        const out = el('div', { class: 'stack' });
        const chartBox = el('div', { class: 'stack' });
        let chartCur = targets[0] || 'USD', range = 2;
        function calc() {
            CF.ls.set('fx.from', from); CF.ls.set('fx.to', targets); CF.ls.set('fx.amt', amt.value);
            const v = num(amt.value);
            fill(out, targets.map(t => {
                const r = isFinite(v) && rates[t] && rates[from] ? v / rates[from] * rates[t] : NaN;
                return el('div', { class: 'fx-row' }, el('div', { class: 'grow' }, el('div', { class: 'fx-big', text: money(r) + ' ' + t }), el('div', { class: 'tiny faint', text: '1 ' + from + ' = ' + fmt(rates[t] / rates[from], 6) + ' ' + t + (CUR_NAMES[t] ? ' · ' + CUR_NAMES[t] : '') })),
                    ibtn('chart', T('Kursverlauf'), () => { chartCur = t === 'EUR' ? from : t; loadChart(); }, 'sm'),
                    ibtn('copy', T('Kopieren'), () => CF.copy(money(r)), 'sm'),
                    ibtn('x', T('Entfernen'), () => { targets = targets.filter(x => x !== t); calc(); }, 'sm'));
            }), el('div', { class: 'row' }, select([['', T('+ Währung hinzufügen')]].concat(codes.filter(c => targets.indexOf(c) < 0).map(c => [c, c + (CUR_NAMES[c] ? ' · ' + CUR_NAMES[c] : '')])), '', v => { if (v) { targets.push(v); calc(); } }, { class: 'in', style: { width: 'auto' } }),
                ibtn('refresh', T('Tauschen'), () => { if (targets[0]) { const t = targets[0]; targets[0] = from; from = t; fromSel.value = from; calc(); } })));
        }
        async function loadChart() {
            const cur = chartCur === 'EUR' ? (from === 'EUR' ? 'USD' : from) : chartCur;
            fill(chartBox, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Kursverlauf wird geladen …')));
            if (!CF.loggedIn()) { fill(chartBox, CF.msg('info', T('Der Kursverlauf geht nach dem Anmelden.'))); return; }
            try {
                const r = await CF.api('fxhist', { cur, days: range });
                const pts = r.points;
                const first = pts[0], last = pts[pts.length - 1];
                const ch = first && last ? (last[1] - first[1]) / first[1] * 100 : 0;
                const box = el('div');
                box.innerHTML = chartSvg(pts);
                fill(chartBox, el('div', { class: 'row between' }, el('b', { text: T('1 Euro in %s', cur) }), CF.seg([['1', '30 T'], ['2', '90 T'], ['3', T('1 J')], ['4', T('5 J')]], String(range), v => { range = +v; loadChart(); })),
                    box, el('div', { class: 'row small' }, CF.badge((ch >= 0 ? '+' : '') + fmt(ch, 2) + ' %', ch >= 0 ? 'ok' : 'danger'),
                        el('span', { class: 'muted', text: last ? T('zuletzt %s am %s', fmt(last[1], 4), last[0]) : '' })));
            } catch (e) { fill(chartBox, CF.msg('warn', e.message)); }
        }
        amt.addEventListener('input', calc);
        fill(root, two([field(T('Betrag'), amt), field(T('Währung'), fromSel), CF.card(T('Umgerechnet'), 'coins', out)],
            [CF.card(T('Kursverlauf'), 'chart', chartBox), el('p', { class: 'tiny faint', text: T('Referenzkurse der Europäischen Zentralbank vom %s. Die EZB veröffentlicht sie werktags gegen 16 Uhr.', data.date || '?') })]));
        calc();
        loadChart();
    },
};

/* ---------- Zinsen, Sparplan, Kredit ---------- */
CF.views.finanzen = {
    mount(root) {
        let mode = CF.ls.get('fin.mode', 'save');
        const box = el('div', { class: 'stack' }), out = el('div', { class: 'stack' });
        const get = (k, d) => CF.ls.get('fin.' + k, d);
        function f(k, label, d, hint) { const i = numIn(get(k, d)); i.addEventListener('input', () => { CF.ls.set('fin.' + k, i.value); calc(); }); return [field(label, i, hint), () => num(i.value)]; }
        let inputs = {};
        function render() {
            CF.ls.set('fin.mode', mode);
            const defs = {
                save: [['start', T('Startkapital'), '1000'], ['rate', T('Monatliche Sparrate'), '100'], ['p', T('Zinssatz pro Jahr in %'), '5'], ['years', T('Laufzeit in Jahren'), '20'], ['dyn', T('Jährliche Erhöhung der Rate in %'), '0', T('Dynamik')]],
                loan: [['amount', T('Kreditsumme'), '20000'], ['p', T('Sollzins pro Jahr in %'), '6,5'], ['years', T('Laufzeit in Jahren'), '5']],
                double: [['p', T('Zinssatz pro Jahr in %'), '7']],
            }[mode];
            inputs = {};
            fill(box, defs.map(([k, l, d, h]) => { const [fd, g] = f(mode + k, l, d, h); inputs[k] = g; return fd; }));
            calc();
        }
        function calc() {
            const v = k => inputs[k] ? inputs[k]() : NaN;
            if (mode === 'save') {
                const s = v('start') || 0, r = v('rate') || 0, p = (v('p') || 0) / 100, n = Math.round(v('years') || 0), dyn = (v('dyn') || 0) / 100;
                if (n <= 0 || n > 100) { fill(out); return; }
                let bal = s, paid = s, rate = r;
                const rows = [];
                for (let y = 1; y <= n; y++) {
                    for (let m = 0; m < 12; m++) { bal += rate; paid += rate; bal *= Math.pow(1 + p, 1 / 12); }
                    rows.push([y, paid, bal]);
                    rate *= 1 + dyn;
                }
                fill(out, el('div', { class: 'kv' }, [[T('Endkapital'), money(bal), true], [T('Eingezahlt'), money(paid)], [T('Zinsen'), money(bal - paid)]].map(([k, val, st]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v' + (st ? ' strong' : ''), text: val }), el('div', { class: 'a' })]).flat()),
                    finBars(rows), el('div', { class: 'tbl-wrap', style: { maxHeight: '20rem', overflow: 'auto' } }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: T('Jahr') }), el('th', { class: 'num', text: T('Eingezahlt') }), el('th', { class: 'num', text: T('Kapital') })),
                        rows.map(r => el('tr', {}, el('td', { text: r[0] }), el('td', { class: 'num', text: money(r[1]) }), el('td', { class: 'num', text: money(r[2]) }))))),
                    el('p', { class: 'tiny faint', text: T('Monatliche Einzahlung, monatliche Verzinsung (konformer Zins), ohne Steuern und Kosten.') }));
            } else if (mode === 'loan') {
                const a = v('amount'), p = (v('p') || 0) / 100 / 12, n = Math.round((v('years') || 0) * 12);
                if (!(a > 0) || n <= 0 || n > 600) { fill(out); return; }
                const rate = p ? a * p / (1 - Math.pow(1 + p, -n)) : a / n;
                let rest = a;
                const rows = [];
                let yInt = 0, yTil = 0;
                for (let m = 1; m <= n; m++) { const z = rest * p, t = rate - z; rest -= t; yInt += z; yTil += t; if (m % 12 === 0 || m === n) { rows.push([Math.ceil(m / 12), yInt, yTil, Math.max(0, rest)]); yInt = 0; yTil = 0; } }
                fill(out, el('div', { class: 'kv' }, [[T('Monatliche Rate'), money(rate), true], [T('Zinsen gesamt'), money(rate * n - a)], [T('Zurückgezahlt gesamt'), money(rate * n)]].map(([k, val, st]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v' + (st ? ' strong' : ''), text: val }), el('div', { class: 'a' })]).flat()),
                    el('div', { class: 'tbl-wrap', style: { maxHeight: '20rem', overflow: 'auto' } }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: T('Jahr') }), el('th', { class: 'num', text: T('Zinsen') }), el('th', { class: 'num', text: T('Tilgung') }), el('th', { class: 'num', text: T('Restschuld') })),
                        rows.map(r => el('tr', {}, el('td', { text: r[0] }), el('td', { class: 'num', text: money(r[1]) }), el('td', { class: 'num', text: money(r[2]) }), el('td', { class: 'num', text: money(r[3]) }))))),
                    el('p', { class: 'tiny faint', text: T('Annuitätendarlehen mit gleichbleibender Monatsrate. Ohne Gebühren, ohne Sondertilgung.') }));
            } else {
                const p = v('p');
                if (!(p > 0)) { fill(out); return; }
                fill(out, el('div', { class: 'big-num', text: T('%s Jahre', fmt(Math.log(2) / Math.log(1 + p / 100), 1)) }), el('p', { class: 'small muted', text: T('So lange dauert es, bis sich Geld bei %s % Zinsen pro Jahr verdoppelt. Faustregel: 72 geteilt durch den Zinssatz = %s.', fmt(p), fmt(72 / p, 1)) }));
            }
        }
        fill(root, two([CF.seg([['save', T('Sparplan')], ['loan', T('Kredit')], ['double', T('Verdoppeln')]], mode, v => { mode = v; render(); }), box], [CF.card(T('Ergebnis'), 'chart', out)]));
        render();
    },
};
function finBars(rows) {
    const max = Math.max(...rows.map(r => r[2]), 1);
    return el('div', { class: 'fin-bars' }, rows.map(r => el('div', { class: 'fin-bar', title: r[0] + ': ' + money(r[2]) }, el('span', { class: 'z', style: { height: ((r[2] - r[1]) / max * 100) + '%' } }), el('span', { class: 'e', style: { height: (r[1] / max * 100) + '%' } }))));
}

/* ---------- Sprit und Rechnung teilen ---------- */
CF.views.kosten = {
    mount(root) {
        let mode = CF.ls.get('cost.mode', 'fuel');
        const box = el('div', { class: 'stack' }), out = el('div', { class: 'stack' });
        const fld = (k, label, d, hint) => { const i = numIn(CF.ls.get('cost.' + k, d)); i.addEventListener('input', () => { CF.ls.set('cost.' + k, i.value); calc(); }); box.append(field(label, i, hint)); return () => num(i.value); };
        let g = {};
        function render() {
            CF.ls.set('cost.mode', mode);
            fill(box);
            if (mode === 'fuel') g = { km: fld('km', T('Strecke in km'), '350'), use: fld('use', T('Verbrauch (l/100 km oder kWh/100 km)'), '6,5'), price: fld('price', T('Preis pro Liter bzw. kWh'), '1,79'), people: fld('people', T('Personen'), '2'), back: null };
            else g = { bill: fld('bill', T('Rechnungsbetrag'), '84,50'), tip: fld('tip', T('Trinkgeld in %'), '10'), people: fld('ppl', T('Personen'), '3') };
            calc();
        }
        function calc() {
            if (mode === 'fuel') {
                const km = g.km(), use = g.use(), pr = g.price(), p = Math.max(1, g.people() || 1);
                if (![km, use, pr].every(isFinite)) { fill(out); return; }
                const l = km * use / 100, cost = l * pr;
                fill(out, el('div', { class: 'kv' }, [[T('Verbrauch'), fmt(l, 2) + ' l / kWh'], [T('Kosten'), money(cost), true], [T('Pro Person'), money(cost / p), true], [T('Hin und zurück'), money(cost * 2)], [T('Pro km'), money(cost / km)]]
                    .map(([k, v, st]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v' + (st ? ' strong' : ''), text: v }), el('div', { class: 'a' })]).flat()));
            } else {
                const b = g.bill(), t = g.tip() || 0, p = Math.max(1, g.people() || 1);
                if (!isFinite(b)) { fill(out); return; }
                const total = b * (1 + t / 100);
                const per = total / p;
                const round = Math.ceil(per * 2) / 2;
                fill(out, el('div', { class: 'kv' }, [[T('Trinkgeld'), money(total - b)], [T('Gesamt'), money(total), true], [T('Pro Person'), money(per), true], [T('Aufgerundet pro Person'), money(round) + ' (' + T('zusammen %s', money(round * p)) + ')']]
                    .map(([k, v, st]) => [el('div', { class: 'k', text: k }), el('div', { class: 'v' + (st ? ' strong' : ''), text: v }), el('div', { class: 'a' })]).flat()),
                    el('div', { class: 'chips' }, [0, 5, 10, 15, 20].map(x => el('button', { type: 'button', class: 'chip', text: x + ' %', onclick: () => { CF.ls.set('cost.tip', String(x)); render(); } }))));
            }
        }
        fill(root, two([CF.seg([['fuel', T('Spritkosten')], ['bill', T('Rechnung teilen')]], mode, v => { mode = v; render(); }), box], [CF.card(T('Ergebnis'), 'coins', out)]));
        render();
    },
};

/* ---------- Römische Zahlen ---------- */
const ROM = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
function toRoman(n) { let s = ''; ROM.forEach(([v, r]) => { while (n >= v) { s += r; n -= v; } }); return s; }
function fromRoman(s) {
    s = s.toUpperCase().replace(/\s/g, '');
    if (!/^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/.test(s) || !s) return NaN;
    let n = 0, i = 0;
    ROM.forEach(([v, r]) => { while (s.slice(i, i + r.length) === r) { n += v; i += r.length; } });
    return n;
}
CF.views.roemisch = {
    mount(root) {
        const inp = input({ class: 'in big mono', placeholder: T('2026 oder MMXXVI'), value: CF.ls.get('rom', String(new Date().getFullYear())), autocomplete: 'off', spellcheck: 'false' });
        const out = el('div', { class: 'stack' });
        function run() {
            CF.ls.set('rom', inp.value);
            const v = inp.value.trim();
            if (!v) { fill(out); return; }
            if (/^\d+$/.test(v)) {
                const n = +v;
                fill(out, n >= 1 && n <= 3999 ? el('div', { class: 'big-num mono', text: toRoman(n) }) : CF.msg('warn', T('Römische Zahlen gehen von 1 bis 3999.')),
                    n >= 1 && n <= 3999 ? el('div', { class: 'small muted', text: ROM.filter(([x]) => x <= n).length ? T('So zusammengesetzt: %s', (() => { let m = n, p = []; ROM.forEach(([x, r]) => { while (m >= x) { p.push(r + ' (' + x + ')'); m -= x; } }); return p.join(' + '); })()) : '' }) : null);
            } else {
                const n = fromRoman(v);
                fill(out, isNaN(n) ? CF.msg('warn', T('Keine gültige römische Zahl.')) : el('div', { class: 'big-num', text: fmt(n) }));
            }
        }
        inp.addEventListener('input', run);
        fill(root, el('div', { class: 'stack lg' }, inp, out, el('div', { class: 'chips' }, ROM.slice().reverse().filter(r => r[1].length === 1).map(([v, r]) => el('span', { class: 'badge', text: r + ' = ' + v })))));
        run();
    },
};

/* ---------- Morse ---------- */
const MORSE = { a: '.-', b: '-...', c: '-.-.', d: '-..', e: '.', f: '..-.', g: '--.', h: '....', i: '..', j: '.---', k: '-.-', l: '.-..', m: '--', n: '-.', o: '---', p: '.--.', q: '--.-', r: '.-.', s: '...', t: '-', u: '..-', v: '...-', w: '.--', x: '-..-', y: '-.--', z: '--..',
    0: '-----', 1: '.----', 2: '..---', 3: '...--', 4: '....-', 5: '.....', 6: '-....', 7: '--...', 8: '---..', 9: '----.', 'ä': '.-.-', 'ö': '---.', 'ü': '..--', 'ß': '...--..',
    '.': '.-.-.-', ',': '--..--', '?': '..--..', '!': '-.-.--', '/': '-..-.', '(': '-.--.', ')': '-.--.-', '&': '.-...', ':': '---...', ';': '-.-.-.', '=': '-...-', '+': '.-.-.', '-': '-....-', '"': '.-..-.', '@': '.--.-.', "'": '.----.' };
const RMORSE = Object.fromEntries(Object.entries(MORSE).map(([k, v]) => [v, k]));
CF.views.morse = {
    mount(root) {
        const txt = el('textarea', { class: 'in', rows: 4, placeholder: 'SOS', value: CF.ls.get('morse', 'SOS') });
        const mor = el('textarea', { class: 'in mono', rows: 4 });
        let wpm = 15, playing = null;
        const enc = () => { CF.ls.set('morse', txt.value); mor.value = txt.value.toLowerCase().split(/\s+/).filter(Boolean).map(w => Array.from(w).map(c => MORSE[c] || '').filter(Boolean).join(' ')).join(' / '); };
        const dec = () => { txt.value = mor.value.trim().split(/\s*\/\s*|\s{3,}/).map(w => w.split(/\s+/).map(c => RMORSE[c.replace(/[·•]/g, '.').replace(/[–—_]/g, '-')] || '').join('')).join(' ').toUpperCase(); };
        txt.addEventListener('input', enc);
        mor.addEventListener('input', dec);
        const lamp = el('div', { class: 'morse-lamp' });
        async function play() {
            if (playing) { playing.stop = true; return; }
            const me = playing = { stop: false };
            const unit = 1200 / wpm;
            let ctx = null;
            try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { /* ohne Ton */ }
            const beep = ms => new Promise(res => {
                lamp.classList.add('on');
                let o = null;
                if (ctx) { o = ctx.createOscillator(); const g = ctx.createGain(); g.gain.value = .15; o.frequency.value = 650; o.connect(g); g.connect(ctx.destination); o.start(); }
                setTimeout(() => { if (o) o.stop(); lamp.classList.remove('on'); res(); }, ms);
            });
            playBtn.lastChild.textContent = T('Stopp');
            for (const ch of mor.value) {
                if (me.stop) break;
                if (ch === '.') await beep(unit); else if (ch === '-') await beep(unit * 3); else if (ch === ' ') await CF.sleep(unit * 2); else if (ch === '/') await CF.sleep(unit * 2);
                await CF.sleep(unit);
            }
            if (ctx) ctx.close();
            playing = null;
            playBtn.lastChild.textContent = T('Abspielen');
        }
        const playBtn = btn(T('Abspielen'), 'btn-primary', play, 'play');
        fill(root, two([field(T('Text'), txt), field(T('Morsecode'), mor), el('div', { class: 'row' }, playBtn, lamp, el('span', { class: 'small muted', text: T('Tempo') }), CF.seg([['10', T('langsam')], ['15', T('normal')], ['22', T('schnell')]], '15', v => { wpm = +v; })),
            el('div', { class: 'row' }, btn(T('Kopieren'), 'btn-second btn-sm', () => CF.copy(mor.value), 'copy'))],
            [CF.card(T('Alphabet'), 'list', el('div', { class: 'morse-tab mono' }, Object.entries(MORSE).slice(0, 36).map(([k, v]) => el('div', {}, el('b', { text: k.toUpperCase() }), el('span', { text: v })))))]));
        enc();
        return () => { if (playing) playing.stop = true; };
    },
};
})();
