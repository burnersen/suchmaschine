/* Suchmaschine – Kurzlinks und Text-Schnipsel im Browser.
   Kurzlinks: Wunschname, Ablauf, Aufruf-Grenze, Passwort, Ziel ändern, an/aus, Statistik, QR, Ziele prüfen, CSV, Lesezeichen-Knopf.
   Schnipsel: werden hier verschlüsselt (AES-256-GCM), der Schlüssel steht nur im Link hinter dem #. */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

const fmtDate = ts => ts ? new Date(ts * 1000).toLocaleDateString(CF.locale(), { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
const fmtDT = ts => ts ? new Date(ts * 1000).toLocaleString(CF.locale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
const isoDay = ts => { const d = new Date(ts * 1000); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };

/** QR-Code als SVG (für Kurzlinks und Schnipsel) */
function qrSvg(text) {
    if (!CF.QR || !CF.Render) return '';
    try { return CF.Render.qr(CF.QR.encode(text, { ecl: 'M', boost: true }), { margin: 4 }).svg; } catch (e) { return ''; }
}
function qrDialog(text, title) {
    const svg = qrSvg(text);
    const box = el('div', { class: 'stack', style: { alignItems: 'center' } });
    const pv = el('div', { class: 'preview', style: { minHeight: '0', width: '100%' } });
    pv.innerHTML = svg;
    box.append(pv, el('code', { class: 'mono break', text: text }));
    const m = CF.modal({ title: title || T('QR-Code'), body: box, actions: [
        btn('PNG', 'btn-primary', async () => CF.download('qr-' + CF.fileStamp() + '.png', await CF.Render.svgToPng(svg, 1024, 1024)), 'download'),
        btn('SVG', 'btn-second', () => CF.download('qr-' + CF.fileStamp() + '.svg', new Blob([svg], { type: 'image/svg+xml' })), 'download'),
        btn(T('Im QR-Studio gestalten'), 'btn-ghost', () => { m.close(); CF.go('create', { text }); }, 'palette')] });
}

/** Säulen für die Statistik */
function bars(days) {
    const keys = Object.keys(days);
    const max = Math.max(1, ...keys.map(k => days[k]));
    return el('div', { class: 'stack' },
        el('div', { class: 'bars' }, keys.map(k => el('span', { title: k + ': ' + days[k], style: { height: Math.max(1, days[k] / max * 100) + '%', opacity: days[k] ? '.9' : '.25' } }))),
        el('div', { class: 'row between tiny faint' }, el('span', { text: keys[0] || '' }), el('span', { text: keys[keys.length - 1] || '' })));
}

/* ---------- Kurzlinks ---------- */

CF.views.kurzlinks = {
    mount(root, params) {
        if (!CF.loggedIn()) { fill(root, CF.msg('info', T('Kurzlinks kann man nach dem Anmelden anlegen.')), el('a', { class: 'btn btn-primary', href: CF.boot.login, text: T('Anmelden') })); return; }
        let links = [], query = '', sort = 'new', fresh = '';
        const listBox = el('div', { class: 'hlist' });
        const head = el('div', { class: 'row between' });

        const url = input({ type: 'url', placeholder: 'https://example.org/ein/sehr/langer/pfad', class: 'in big', autocomplete: 'off', spellcheck: 'false', value: params.url || params.text || '' });
        const code = input({ placeholder: T('frei lassen = zufällig'), maxlength: 40, autocomplete: 'off', spellcheck: 'false' });
        const exp = input({ type: 'date' });
        const max = input({ type: 'number', min: 0, max: 1000000, placeholder: T('unbegrenzt') });
        const pass = input({ type: 'text', placeholder: T('ohne'), autocomplete: 'off', spellcheck: 'false' });
        const note = input({ placeholder: T('nur für dich sichtbar'), maxlength: 200 });
        let strip = CF.ls.get('shortStrip', true);
        const resBox = el('div');
        const make = btn(T('Kurzlink erstellen'), 'btn-primary btn-lg', () => create(), 'link');
        url.addEventListener('keydown', e => { if (e.key === 'Enter') create(); });

        async function create() {
            if (!url.value.trim()) { url.focus(); return; }
            make.disabled = true;
            try {
                const r = await CF.api('short_create', { url: url.value, code: code.value, expires: exp.value, max_hits: max.value || 0, pass: pass.value, note: note.value, strip: strip ? 1 : 0 });
                fresh = r.link.code;
                url.value = ''; code.value = ''; exp.value = ''; max.value = ''; pass.value = ''; note.value = '';
                showResult(r.link);
                await load();
            } catch (e) { CF.toast(e.message, 'bad', 4500); }
            make.disabled = false;
        }
        function showResult(l) {
            const pv = el('div', { class: 'preview', style: { minHeight: '0', padding: '.6rem', maxWidth: '9rem' } });
            pv.innerHTML = qrSvg(l.short);
            fill(resBox, el('div', { class: 'card flat', style: { marginTop: '.8rem' } }, el('div', { class: 'row nw', style: { alignItems: 'flex-start' } },
                pv, el('div', { class: 'stack grow' },
                    el('div', { class: 'domain', text: l.short }),
                    el('div', { class: 'small muted break', text: l.title ? l.title + ' · ' + l.url : l.url }),
                    el('div', { class: 'row' }, btn(T('Kopieren'), 'btn-primary btn-sm', () => CF.copy(l.short), 'copy'),
                        navigator.share ? btn(T('Teilen'), 'btn-second btn-sm', () => CF.share({ url: l.short }), 'share') : null,
                        btn(T('QR-Code'), 'btn-ghost btn-sm', () => qrDialog(l.short), 'qr'))))));
            CF.copy(l.short, true);
            CF.toast(T('Kurzlink kopiert'));
        }

        const badges = l => [
            l.state === 'off' ? CF.badge(T('aus'), 'warn') : null,
            l.state === 'expired' ? CF.badge(T('abgelaufen'), 'warn') : null,
            l.state === 'used' ? CF.badge(T('aufgebraucht'), 'warn') : null,
            l.pass ? CF.badge('🔒 ' + T('Passwort')) : null,
            l.expires && l.state !== 'expired' ? CF.badge(T('bis %s', fmtDate(l.expires))) : null,
            l.max ? CF.badge(T('%s von %s Aufrufen', l.hits, l.max)) : null,
            l.dead ? CF.badge(T('Ziel tot (%s)', l.dead > 1 ? l.dead : '?'), 'danger') : null,
        ];
        function render() {
            const q = query.toLowerCase();
            let list = links.filter(l => !q || (l.code + ' ' + l.url + ' ' + l.title + ' ' + l.note).toLowerCase().indexOf(q) >= 0);
            if (sort === 'hits') list = list.slice().sort((a, b) => b.hits - a.hits);
            if (sort === 'last') list = list.slice().sort((a, b) => b.last - a.last);
            fill(head, el('b', { text: T('%s Kurzlinks', links.length) }), el('div', { class: 'row' },
                (() => { const i = input({ type: 'search', placeholder: T('Suchen …'), value: query, style: { width: '12rem' } }); i.addEventListener('input', () => { query = i.value; render(); setTimeout(() => { const n = head.querySelector('input'); n.focus(); n.setSelectionRange(n.value.length, n.value.length); }, 0); }); return i; })(),
                select([['new', T('neueste zuerst')], ['hits', T('meiste Aufrufe')], ['last', T('zuletzt benutzt')]], sort, v => { sort = v; render(); }, { style: { width: 'auto' } }),
                btn(T('Ziele prüfen'), 'btn-second btn-sm', () => check(), 'test'),
                ibtn('download', 'CSV', () => exportCsv(), 'sm')));
            fill(listBox, list.length ? list.map(l => el('div', { class: 'hrow short-row' + (l.code === fresh ? ' is-new' : '') + (l.state !== 'ok' ? ' is-off' : '') },
                el('div', { class: 'grow' },
                    el('div', { class: 'row nw' }, el('a', { class: 't mono', href: l.short, text: l.short.replace(/^https?:\/\//, ''), title: T('Klicken zum Kopieren'), onclick: e => { e.preventDefault(); CF.copy(l.short); } })),
                    el('div', { class: 'm break', text: (l.title ? l.title + ' · ' : '') + l.url }),
                    el('div', { class: 'row', style: { gap: '.3rem', marginTop: '.25rem' } }, CF.badge(T('%s Aufrufe', l.hits), 'acc'), badges(l), l.note ? el('span', { class: 'tiny faint', text: l.note }) : null)),
                el('div', { class: 'row nw' },
                    ibtn('chart', T('Statistik'), () => stats(l), 'sm'),
                    ibtn('edit', T('Bearbeiten'), () => edit(l), 'sm'),
                    ibtn('qr', T('QR-Code'), () => qrDialog(l.short), 'sm'),
                    ibtn('eye', T('Vorschau ohne Aufruf'), () => CF.openUrl(l.short + '+'), 'sm'),
                    ibtn('trash', T('Löschen'), async () => { if (await CF.confirm(T('Kurzlink %s löschen?', l.code), T('Löschen'), true)) { await CF.api('short_delete', { code: l.code }); load(); } }, 'sm'))))
                : CF.empty('link', query ? T('Nichts gefunden.') : T('Noch keine Kurzlinks.')));
        }
        async function load() {
            try { const r = await CF.api('short_list', {}); links = r.links || []; render(); } catch (e) { fill(listBox, CF.msg('danger', e.message)); }
        }
        async function check() {
            CF.toast(T('Ziele werden geprüft …'), 'info');
            try { const r = await CF.api('short_check', {}); links = r.links; render(); CF.toast(T('%s geprüft, %s tot', r.checked, r.dead), r.dead ? 'warn' : 'ok', 3500); } catch (e) { CF.toast(e.message, 'bad'); }
        }
        function exportCsv() {
            const rows = [['code', 'kurzlink', 'ziel', 'titel', 'aufrufe', 'erstellt', 'ablauf', 'limit', 'zustand', 'notiz']].concat(links.map(l => [l.code, l.short, l.url, l.title, l.hits, fmtDT(l.created), fmtDT(l.expires), l.max || '', l.state, l.note]));
            CF.download('kurzlinks-' + CF.fileStamp() + '.csv', new Blob([CF.csv(rows)], { type: 'text/csv;charset=utf-8' }));
        }
        async function stats(l) {
            const body = el('div', { class: 'stack' });
            const show = async (d) => {
                fill(body, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Lädt …')));
                try {
                    const r = await CF.api('short_stats', { code: l.code, days: d });
                    const sum = Object.values(r.days).reduce((a, b) => a + b, 0);
                    fill(body, seg, el('div', { class: 'row' }, el('div', { class: 'big-num', text: CF.fmtNum(sum) }), el('div', { class: 'small muted', text: T('Aufrufe in diesem Zeitraum, %s insgesamt', CF.fmtNum(l.hits)) })),
                        bars(r.days), el('div', { class: 'tiny faint', text: T('Gezählt wird nur ein Zähler pro Tag. Keine IP-Adressen, keine Cookies, kein Referrer.') }));
                } catch (e) { fill(body, CF.msg('danger', e.message)); }
            };
            const seg = CF.seg([['30', T('30 Tage')], ['90', T('90 Tage')], ['365', T('1 Jahr')]], '30', v => show(+v));
            CF.modal({ title: T('Statistik: %s', l.code), body, wide: true });
            show(30);
        }
        function edit(l) {
            const u = input({ type: 'url', value: l.url });
            const c = input({ value: l.code, maxlength: 40 });
            const e = input({ type: 'date', value: l.expires ? isoDay(l.expires) : '' });
            const m = input({ type: 'number', min: 0, value: l.max || '' });
            const p = input({ placeholder: l.pass ? T('unverändert lassen') : T('ohne'), autocomplete: 'off' });
            const n = input({ value: l.note, maxlength: 200 });
            let active = l.active, rmPass = false;
            const dlg = CF.modal({ title: T('Kurzlink bearbeiten'), wide: true, body: el('div', { class: 'stack' },
                field(T('Ziel-Adresse'), u, T('ändern geht jederzeit, der Kurzlink bleibt gleich')),
                el('div', { class: 'grid2' }, field(T('Name'), c), field(T('Notiz'), n)),
                el('div', { class: 'grid3' }, field(T('Gültig bis'), e), field(T('Höchstens Aufrufe'), m), field(T('Passwort'), p)),
                l.pass ? CF.toggle(T('Passwort entfernen'), false, v => { rmPass = v; }) : null,
                CF.toggle(T('Aktiv'), active, v => { active = v; }, T('Ausgeschaltete Kurzlinks leiten nicht weiter'))),
                actions: [btn(T('Abbrechen'), 'btn-ghost', () => dlg.close()), btn(T('Speichern'), 'btn-primary', async () => {
                    try {
                        await CF.api('short_update', { old: l.code, url: u.value, code: c.value, expires: e.value, max_hits: m.value || 0, pass: rmPass ? '-' : p.value, note: n.value, active: active ? 1 : 0 });
                        dlg.close(); CF.toast(T('Gespeichert')); load();
                    } catch (err) { CF.toast(err.message, 'bad', 4500); }
                }, 'check')] });
        }

        const bookmarklet = "javascript:location.href='" + (CF.boot.origin || location.origin + location.pathname) + "?a=tools&tool=kurzlinks&url='+encodeURIComponent(location.href)";
        const bm = el('a', { class: 'btn btn-second btn-sm', href: bookmarklet, onclick: e => { e.preventDefault(); CF.toast(T('In die Lesezeichenleiste ziehen'), 'info'); } });
        bm.insertAdjacentHTML('beforeend', CF.icon('link'));
        bm.append(el('span', { text: T('Kürzen') }));

        fill(root,
            el('div', { class: 'layout' },
                el('div', { class: 'stack lg' },
                    CF.card(T('Neuer Kurzlink'), 'link', el('div', { class: 'stack' },
                        field(T('Lange Adresse'), url),
                        el('div', { class: 'grid2' }, field(T('Wunschname'), code, T('optional')), field(T('Notiz'), note, T('optional'))),
                        el('details', { class: 'adv' }, el('summary', {}, T('Ablauf, Aufruf-Grenze, Passwort')), el('div', { class: 'stack' },
                            el('div', { class: 'grid3' }, field(T('Gültig bis'), exp), field(T('Höchstens Aufrufe'), max, T('1 = nur einmal')), field(T('Passwort'), pass)),
                            el('div', { class: 'tiny faint', text: T('Mit Passwort oder Aufruf-Grenze kommt vor dem Ziel eine kurze Zwischenseite, damit die Linkvorschau von Messengern keinen Aufruf verbraucht.') }))),
                        CF.toggle(T('Tracking-Parameter entfernen'), strip, v => { strip = v; CF.ls.set('shortStrip', v); }, 'utm_…, fbclid, gclid …'),
                        el('div', { class: 'row' }, make), resBox)),
                    CF.card(T('Deine Kurzlinks'), 'list', el('div', { class: 'stack' }, head, listBox))),
                el('div', { class: 'sticky stack lg' },
                    CF.card(T('Gut zu wissen'), 'info', el('div', { class: 'stack small' },
                        el('p', { text: T('Kurzlinks funktionieren für jeden, auch ohne Anmeldung. Nur du siehst deine Liste.') }),
                        el('p', { text: T('Ein + am Ende eines Kurzlinks zeigt das Ziel, ohne weiterzuleiten und ohne zu zählen.') }),
                        el('p', { text: T('Statistik ohne Tracking: nur ein Zähler pro Tag, keine IP-Adressen, keine Cookies.') }),
                        el('p', { text: T('Im Suchfeld geht auch „kurz https://…“.') }))),
                    CF.card(T('Schneller kürzen'), 'sparkles', el('div', { class: 'stack small' },
                        el('p', { text: T('Zieh diesen Knopf in deine Lesezeichenleiste. Ein Klick darauf kürzt die Seite, die du gerade ansiehst.') }), bm,
                        el('p', { class: 'muted', text: T('Am Handy (Android): installiere die Suchmaschine als App, dann steht sie im Teilen-Menü.') }))))));
        load().then(() => { if (params.edit) { const l = links.find(x => x.code === params.edit); if (l) edit(l); } });
        if (params.url || params.text) setTimeout(() => CF.focus(url), 50);
    },
};

/* ---------- Text-Schnipsel ---------- */

const b64u = a => { let s = ''; a.forEach(x => { s += String.fromCharCode(x); }); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); };
const hex = a => Array.from(a, x => x.toString(16).padStart(2, '0')).join('');

/** Verschlüsseln: liefert { data (JSON-Text für den Server), key (für den Link), auth (Prüfwert bei Passwort) } */
async function seal(text, pass) {
    const key = crypto.getRandomValues(new Uint8Array(32));
    let raw = key, salt = null, auth = '';
    if (pass) {
        salt = crypto.getRandomValues(new Uint8Array(16));
        const base = await crypto.subtle.importKey('raw', new TextEncoder().encode(pass), 'PBKDF2', false, ['deriveBits']);
        const bits = new Uint8Array(await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations: 310000 }, base, 512));
        const both = new Uint8Array(64);
        both.set(key, 0); both.set(bits.slice(0, 32), 32);
        raw = new Uint8Array(await crypto.subtle.digest('SHA-256', both));
        auth = hex(bits.slice(32));
    }
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const k = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt']);
    const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, k, new TextEncoder().encode(JSON.stringify({ t: text }))));
    const data = { v: 1, iv: b64u(iv), ct: b64u(ct) };
    if (salt) data.salt = b64u(salt);
    return { data: JSON.stringify(data), key: b64u(key), auth };
}

CF.views.schnipsel = {
    mount(root, params) {
        if (!CF.loggedIn()) { fill(root, CF.msg('info', T('Text-Schnipsel kann man nach dem Anmelden anlegen. Abholen geht für jeden mit dem Link.')), el('a', { class: 'btn btn-primary', href: CF.boot.login, text: T('Anmelden') })); return; }
        if (!window.crypto || !crypto.subtle) { fill(root, CF.msg('danger', T('Verschlüsseln geht nur über HTTPS.'))); return; }
        const links = CF.ls.get('notes', {});
        const ta = el('textarea', { class: 'in', rows: 9, placeholder: T('Text, Passwort, Zugangsdaten, Notiz …'), maxlength: 80000 });
        if (params.text) ta.value = params.text;
        let hours = CF.ls.get('noteHours', 24), views = CF.ls.get('noteViews', 1);
        const pass = input({ type: 'text', placeholder: T('optional'), autocomplete: 'off', spellcheck: 'false' });
        const label = input({ placeholder: T('optional, nur für dich'), maxlength: 80 });
        const count = el('span', { class: 'tiny faint' });
        ta.addEventListener('input', () => { count.textContent = T('%s Zeichen', ta.value.length); });
        const out = el('div');
        const listBox = el('div', { class: 'hlist' });
        const make = btn(T('Verschlüsseln und Link erzeugen'), 'btn-primary btn-lg', async () => {
            if (!ta.value.trim()) { ta.focus(); return; }
            make.disabled = true;
            try {
                const s = await seal(ta.value, pass.value);
                const r = await CF.api('note_create', { data: s.data, hours, views, pass: pass.value ? 1 : 0, auth: s.auth, label: label.value });
                const link = r.url + '#' + s.key;
                links[r.id] = link;
                CF.ls.set('notes', links);
                ta.value = ''; pass.value = ''; label.value = ''; count.textContent = '';
                const pv = el('div', { class: 'preview', style: { minHeight: '0', padding: '.6rem', maxWidth: '9rem' } });
                pv.innerHTML = qrSvg(link);
                fill(out, el('div', { class: 'card flat', style: { marginTop: '.8rem' } }, el('div', { class: 'row nw', style: { alignItems: 'flex-start' } }, pv,
                    el('div', { class: 'stack grow' }, CF.msg('ok', T('Fertig. Schick diesen Link an die Person, die den Text lesen soll:')),
                        el('div', { class: 'code-line' }, el('span', { text: link })),
                        el('div', { class: 'row' }, btn(T('Link kopieren'), 'btn-primary btn-sm', () => CF.copy(link), 'copy'),
                            navigator.share ? btn(T('Teilen'), 'btn-second btn-sm', () => CF.share({ url: link }), 'share') : null,
                            btn(T('QR-Code'), 'btn-ghost btn-sm', () => qrDialog(link, T('Text-Schnipsel')), 'qr')),
                        pass.value === '' ? null : null))));
                CF.copy(link, true);
                CF.toast(T('Link kopiert'));
                load();
            } catch (e) { CF.toast(e.message, 'bad', 4500); }
            make.disabled = false;
        }, 'lock');
        async function load() {
            try {
                const r = await CF.api('note_list', {});
                fill(listBox, r.notes.length ? r.notes.map(n => el('div', { class: 'hrow' },
                    el('div', { class: 'ico', icon: n.pass ? 'lock' : 'text' }),
                    el('div', { class: 'grow' }, el('div', { class: 't', text: n.label || T('Schnipsel vom %s', fmtDT(n.created)) }),
                        el('div', { class: 'm', text: T('gültig bis %s', fmtDT(n.expires)) + ' · ' + (n.left === 0 ? T('beliebig oft bis dahin') : n.left === 1 ? T('wird beim Lesen gelöscht') : T('noch %s Abrufe', n.left)) })),
                    links[n.id] ? ibtn('copy', T('Link kopieren'), () => CF.copy(links[n.id]), 'sm') : el('span', { class: 'tiny faint', title: T('Den Link kennt nur der Browser, in dem du ihn erzeugt hast.'), text: T('Link nur dort') }),
                    ibtn('trash', T('Löschen'), async () => { if (await CF.confirm(T('Schnipsel löschen? Der Link funktioniert dann nicht mehr.'), T('Löschen'), true)) { await CF.api('note_delete', { id: n.id }); delete links[n.id]; CF.ls.set('notes', links); load(); } }, 'sm')))
                    : CF.empty('text', T('Keine offenen Schnipsel.')));
                // Links zu längst gelöschten Schnipseln vergessen
                Object.keys(links).forEach(id => { if (!r.notes.some(n => n.id === id)) delete links[id]; });
                CF.ls.set('notes', links);
            } catch (e) { fill(listBox, CF.msg('danger', e.message)); }
        }
        fill(root, el('div', { class: 'layout' },
            el('div', { class: 'stack lg' },
                CF.card(T('Neuer Schnipsel'), 'lock', el('div', { class: 'stack' },
                    field(T('Text'), ta), count,
                    el('div', { class: 'grid2' },
                        field(T('Gültig'), select([['1', T('1 Stunde')], ['24', T('1 Tag')], ['72', T('3 Tage')], ['168', T('7 Tage')], ['720', T('30 Tage')]], String(hours), v => { hours = +v; CF.ls.set('noteHours', hours); })),
                        field(T('Abrufe'), select([['1', T('einmal, danach weg')], ['3', T('3 Mal')], ['10', T('10 Mal')], ['0', T('beliebig bis zum Ablauf')]], String(views), v => { views = +v; CF.ls.set('noteViews', views); }))),
                    el('div', { class: 'grid2' }, field(T('Passwort'), pass, T('extra Schutz, getrennt schicken')), field(T('Bezeichnung'), label)),
                    el('div', { class: 'row' }, make), out)),
                CF.card(T('Deine offenen Schnipsel'), 'list', listBox)),
            el('div', { class: 'sticky stack lg' },
                CF.card(T('So funktioniert es'), 'shield', el('div', { class: 'stack small' },
                    el('p', { text: T('Dein Browser verschlüsselt den Text mit AES-256-GCM. Der Schlüssel steht nur im Link hinter dem #, den Teil schickt kein Browser an einen Server.') }),
                    el('p', { text: T('Auf dem Server liegt nur unlesbarer Datensalat. Nach dem letzten erlaubten Abruf oder nach Ablauf wird er gelöscht.') }),
                    el('p', { text: T('Die Abholseite zeigt den Text erst nach einem Klick. So verbrauchen Linkvorschauen in Messengern keinen Abruf.') }),
                    el('p', { text: T('Mit Passwort: schick das Passwort auf einem anderen Weg als den Link (z. B. per Anruf).') }))))));
        load();
        setTimeout(() => CF.focus(ta), 30);
    },
};
})();
