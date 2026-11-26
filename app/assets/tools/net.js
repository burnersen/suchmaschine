/* Suchmaschine – Netz-Werkzeuge: Was Seiten sehen, Ist die Seite down?, Header und Weiterleitungen, SSL, DNS, WHOIS,
   Link-Checker, Link-Vorschau, Lesezeichen aufräumen. Abrufe nach draußen macht der eigene Server (sicher, nur öffentliche Adressen). */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

const statusKind = s => s >= 200 && s < 300 ? 'ok' : s >= 300 && s < 400 ? 'info' : s ? 'danger' : 'warn';
const dt = ts => ts ? new Date(ts * 1000).toLocaleString(CF.locale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–';
const spin = text => el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), text || T('Wird abgefragt …'));
function kv(rows) {
    const g = el('div', { class: 'kv' });
    rows.filter(Boolean).forEach(([k, v, copy]) => {
        g.append(el('div', { class: 'k', text: k }), el('div', { class: 'v' + (typeof v === 'string' && v.length > 60 ? ' break' : '') }, v == null || v === '' ? '–' : v),
            el('div', { class: 'a' }, copy ? ibtn('copy', T('Kopieren'), () => CF.copy(String(copy === true ? v : copy)), 'sm') : null));
    });
    return g;
}
/** Eingabezeile mit Knopf; merkt sich die letzte Eingabe; ?q=… in der Adresse startet sofort */
function askBar(key, placeholder, label, run, params) {
    const inp = input({ class: 'in big', placeholder, value: (params && (params.q || params.url)) || CF.ls.get('net.' + key, ''), autocomplete: 'off', spellcheck: 'false', inputmode: 'url' });
    const go = () => { const v = inp.value.trim(); if (!v) { inp.focus(); return; } CF.ls.set('net.' + key, v); run(v); };
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') go(); });
    const bar = el('div', { class: 'row nw' }, el('div', { class: 'grow' }, inp), btn(label, 'btn-primary btn-lg', go, 'search'));
    bar.go = go;
    bar.inp = inp;
    if (params && (params.q || params.url)) setTimeout(go, 0);
    else setTimeout(() => CF.focus(inp), 30);
    return bar;
}
function shell(root, bar, out, side) {
    fill(root, el('div', { class: 'stack lg' }, bar, out, side || null));
}

/* ---------- Meine IP und was Seiten sehen ---------- */
CF.views.ip = {
    mount(root) {
        const d = CF.boot.data || {};
        const nav = navigator;
        const scr = window.screen || {};
        const tz = (Intl.DateTimeFormat().resolvedOptions() || {}).timeZone || '';
        const rows = [
            [T('Bildschirm'), (scr.width || '?') + ' × ' + (scr.height || '?') + ' px, ' + T('Fenster %s', innerWidth + ' × ' + innerHeight) + ', ' + T('Pixeldichte %s', devicePixelRatio)],
            [T('Farbtiefe'), (scr.colorDepth || '?') + ' Bit'],
            [T('Sprachen'), (nav.languages || [nav.language]).join(', ')],
            [T('Zeitzone'), tz + ' (UTC' + (-(new Date().getTimezoneOffset()) >= 0 ? '+' : '') + (-(new Date().getTimezoneOffset()) / 60) + ')'],
            [T('Betriebssystem'), (nav.userAgentData && nav.userAgentData.platform) || nav.platform || '?'],
            [T('Prozessorkerne'), nav.hardwareConcurrency || '?'],
            [T('Arbeitsspeicher'), nav.deviceMemory ? T('mindestens %s GB', nav.deviceMemory) : T('wird nicht verraten')],
            [T('Touch'), nav.maxTouchPoints ? T('ja, %s Punkte', nav.maxTouchPoints) : T('nein')],
            [T('Cookies'), nav.cookieEnabled ? T('erlaubt') : T('gesperrt')],
            ['Do Not Track', nav.doNotTrack === '1' ? T('an') : T('aus')],
            ['Global Privacy Control', nav.globalPrivacyControl ? T('an') : T('aus')],
            [T('Dunkles Design bevorzugt'), matchMedia('(prefers-color-scheme: dark)').matches ? T('ja') : T('nein')],
            [T('Weniger Bewegung bevorzugt'), matchMedia('(prefers-reduced-motion: reduce)').matches ? T('ja') : T('nein')],
            [T('Grafik (WebGL)'), (() => { try { const c = document.createElement('canvas').getContext('webgl'); const x = c && c.getExtension('WEBGL_debug_renderer_info'); return x ? c.getParameter(x.UNMASKED_RENDERER_WEBGL) : T('verborgen'); } catch (e) { return T('nicht verfügbar'); } })()],
        ];
        const rtcBox = el('div', { class: 'stack' }, btn(T('WebRTC prüfen'), 'btn-second btn-sm', () => rtc(), 'test'));
        async function rtc() {
            fill(rtcBox, spin(T('Prüft …')));
            const ips = new Set();
            try {
                const pc = new RTCPeerConnection({ iceServers: [] });
                pc.createDataChannel('x');
                pc.onicecandidate = e => { if (e.candidate && e.candidate.candidate) { const m = e.candidate.candidate.split(' ')[4]; if (m) ips.add(m); } };
                await pc.setLocalDescription(await pc.createOffer());
                await CF.sleep(1500);
                pc.close();
            } catch (e) { fill(rtcBox, CF.msg('ok', T('WebRTC ist in diesem Browser aus. Darüber kann nichts durchsickern.'))); return; }
            const list = [...ips];
            const leak = list.filter(x => !/\.local$/.test(x));
            fill(rtcBox, leak.length ? CF.msg('warn', T('Dein Browser verrät über WebRTC lokale Adressen: %s', leak.join(', ')))
                : CF.msg('ok', list.length ? T('Lokale Adressen sind verschleiert (%s).', list.join(', ')) : T('WebRTC verrät keine Adressen.')));
        }
        // Die andere Version (meist IPv4, wenn das Handy per IPv6 kommt) sieht der Server nicht: der Browser fragt eine Adresse, die nur darüber geht
        const other = d.v6 ? 'IPv4' : 'IPv6';
        const src = d.other || {};
        const otherBox = el('div', { class: 'stack' });
        const askOther = url => fetch(url, { cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(j => {
                const ip = String(j.ip || '');
                if (!/^[0-9a-fA-F:.]{3,45}$/.test(ip)) throw new Error();
                fill(otherBox, el('div', { class: 'row' }, el('div', { class: 'big-num mono', text: ip }), ibtn('copy', T('Kopieren'), () => CF.copy(ip))));
            });
        const noOther = () => fill(otherBox, CF.msg('info', T('Keine %s gefunden. Vermutlich hat dein Anschluss gerade keine.', other)));
        const askBtn = () => fill(otherBox, el('div', { class: 'row' }, btn(T('%s auch anzeigen', other), 'btn-second btn-sm', () => { fill(otherBox, spin(T('Prüft …'))); askOther(src.ext).catch(noOther); }, 'search')),
            el('p', { class: 'tiny faint', text: T('Fragt dafür einmal ipify.org. Der Dienst sieht dabei deine Adresse.') }));
        if (src.own) { fill(otherBox, spin(T('Prüft …'))); askOther(src.own).catch(askBtn); } else if (src.ext) askBtn(); else noOther();
        fill(root, el('div', { class: 'layout' },
            el('div', { class: 'stack lg' },
                CF.card(T('So sieht dich das Internet'), 'monitor', el('div', { class: 'stack' },
                    el('div', { class: 'row' }, el('div', { class: 'big-num mono', text: d.ip || '?' }), ibtn('copy', T('Kopieren'), () => CF.copy(d.ip || ''))),
                    kv([[T('Version'), d.v6 ? 'IPv6' : 'IPv4'], [T('Rechnername (Reverse DNS)'), d.host || '–'], [T('Verbindung'), (d.https ? 'HTTPS' : 'HTTP') + (d.proto ? ' · ' + d.proto : '')]]),
                    el('p', { class: 'small muted', text: T('Diese Adresse sieht jede Webseite, die du aufrufst. Ein VPN oder Tor ersetzt sie durch eine andere.') }))),
                CF.card(T('Deine %s-Adresse', other), 'wifi', otherBox),
                CF.card(T('Was dein Browser mitschickt'), 'list', el('div', { class: 'stack' },
                    el('p', { class: 'small muted', text: T('Diese Kopfzeilen gehen bei jedem Seitenaufruf mit, ganz ohne JavaScript.') }),
                    kv((d.headers || []).map(h => [h[0], h[1]]))))),
            el('div', { class: 'sticky stack lg' },
                CF.card(T('Was Seiten per JavaScript erfahren'), 'eye', el('div', { class: 'stack' }, kv(rows.map(r => [r[0], String(r[1])])),
                    el('p', { class: 'tiny faint', text: T('Zusammen ergeben solche Merkmale einen recht eindeutigen Fingerabdruck. Nichts davon wird hier gespeichert.') }))),
                CF.card('WebRTC', 'shield', rtcBox))));
    },
};

/* ---------- Ist die Seite down? ---------- */
CF.views.down = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        const bar = askBar('down', 'example.org', T('Prüfen'), async v => {
            fill(out, spin(T('Der Server fragt die Seite ab …')));
            try {
                const r = await CF.api('down', { url: v });
                const verdict = { up: ['ok', T('Erreichbar!'), T('Die Seite antwortet. Wenn sie bei dir nicht lädt, liegt es eher an deiner Verbindung, deinem DNS oder einem Werbeblocker.')],
                    blocked: ['warn', T('Antwortet, aber sperrt Abrufe'), T('Die Seite ist online, lässt aber automatische Abrufe nicht zu (Status %s). Im Browser klappt es meist trotzdem.', r.status)],
                    missing: ['warn', T('Seite nicht gefunden'), T('Der Server läuft, aber diese Adresse gibt es dort nicht (Status %s).', r.status)],
                    error: ['danger', T('Serverfehler'), T('Die Seite antwortet mit einem Fehler (Status %s). Das Problem liegt beim Betreiber.', r.status)],
                    down: ['danger', T('Nicht erreichbar'), r.error || T('Keine Antwort.')] }[r.verdict];
                fill(out, el('div', { class: 'status-big ' + (verdict[0] === 'ok' ? 'ok' : 'bad') }, CF.icon(verdict[0] === 'ok' ? 'check' : 'alert') ? el('span', { html: CF.icon(verdict[0] === 'ok' ? 'check' : 'alert') }) : null, el('span', { text: verdict[1] })),
                    CF.msg(verdict[0] === 'ok' ? 'info' : verdict[0], verdict[2]),
                    kv([[T('Antwortzeit'), r.ms + ' ms'], ['HTTP', (r.status || '–') + (r.http ? ' · HTTP/' + r.http : '')], [T('Endadresse'), r.final, true],
                        [T('Weiterleitungen'), String(Math.max(0, r.hops - 1))], [T('IP-Adressen'), (r.ips || []).join(', ') || r.dns], [T('Geprüft'), dt(r.checked)]]),
                    el('div', { class: 'row' }, btn(T('Nochmal prüfen'), 'btn-second btn-sm', () => bar.go(), 'refresh'),
                        btn(T('Header ansehen'), 'btn-ghost btn-sm', () => CF.go('header', { url: v }), 'list'),
                        btn(T('SSL prüfen'), 'btn-ghost btn-sm', () => CF.go('ssl', { q: v }), 'lock')));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, bar, out);
    },
};

/* ---------- Header und Weiterleitungen ---------- */
CF.views.header = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        let method = 'GET';
        const bar = askBar('header', 'https://example.org/', T('Abrufen'), async v => {
            fill(out, spin());
            try {
                const r = await CF.api('headers', { url: v, method });
                const sec = el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: T('Sicherheits-Kopfzeile') }), el('th', { text: T('Einstufung') }), el('th', { text: T('Wert') })),
                    r.security.map(([n, lv, val]) => el('tr', {}, el('td', { class: 'mono', text: n }), el('td', {}, el('span', { class: 'pill-' + lv, text: lv === 'ok' ? T('gut') : lv === 'mid' ? T('mittel') : T('fehlt') })), el('td', { class: 'small break', text: val })))));
                const score = r.security.filter(x => x[1] === 'ok').length;
                fill(out,
                    CF.card(T('Weiterleitungskette'), 'right', el('div', { class: 'hops' }, r.hops.map((h, i) => el('div', {},
                        CF.badge(h.status ? String(h.status) : '–', statusKind(h.status)), el('code', { class: 'mono', text: (i + 1) + '. ' + h.url }),
                        el('span', { class: 'tiny faint', text: (h.ms ? h.ms + ' ms' : '') + (h.ip ? ' · ' + h.ip : '') + (h.http ? ' · HTTP/' + h.http : '') }),
                        h.error ? el('span', { class: 'small', style: { color: 'var(--bad)' }, text: h.error }) : null)))),
                    CF.card(T('Sicherheit: %s von %s', score, r.security.length), 'shield', sec),
                    r.hops.map((h, i) => h.headers && h.headers.length ? el('details', { class: 'adv', open: i === r.hops.length - 1 ? true : null }, el('summary', {}, T('Kopfzeilen von Station %s (%s)', i + 1, h.status || '–')),
                        el('div', { class: 'stack' }, el('div', { class: 'out-box' }, h.headers.map(x => x[0] + ': ' + x[1]).join('\n')),
                            el('div', { class: 'row' }, btn(T('Kopieren'), 'btn-ghost btn-sm', () => CF.copy(h.headers.map(x => x[0] + ': ' + x[1]).join('\n')), 'copy')))) : null));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, el('div', { class: 'stack' }, bar, CF.seg([['GET', 'GET'], ['HEAD', 'HEAD']], method, v => { method = v; })), out);
    },
};

/* ---------- SSL-Zertifikat ---------- */
CF.views.ssl = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        const bar = askBar('ssl', 'example.org', T('Prüfen'), async v => {
            fill(out, spin());
            try {
                const r = await CF.api('ssl', { host: v });
                const ok = r.trusted && r.match && r.daysLeft >= 0;
                const warnDays = r.daysLeft < 14;
                fill(out,
                    el('div', { class: 'status-big ' + (ok && !warnDays ? 'ok' : 'bad') }, el('span', { html: CF.icon(ok ? 'lock' : 'alert') }),
                        el('span', { text: !r.trusted ? T('Zertifikat wird nicht vertraut') : !r.match ? T('Zertifikat passt nicht zum Namen') : r.daysLeft < 0 ? T('Zertifikat abgelaufen') : T('Gültig, noch %s Tage', r.daysLeft) })),
                    warnDays && r.daysLeft >= 0 ? CF.msg('warn', T('Läuft bald ab. Automatische Verlängerung prüfen!')) : null,
                    kv([[T('Ausgestellt für'), r.subject], [T('Aussteller'), r.issuer], [T('Gültig ab'), dt(r.from)], [T('Gültig bis'), dt(r.to)],
                        [T('Namen im Zertifikat'), r.sanCount + ': ' + r.san.slice(0, 30).join(', ') + (r.sanCount > 30 ? ' …' : '')],
                        [T('Schlüssel'), r.key], [T('Signatur'), r.sig], [T('Protokoll'), r.protocol + (r.cipher ? ' · ' + r.cipher + ' (' + r.cipherBits + ' Bit)' : '')],
                        [T('Seriennummer'), r.serial, true], ['SHA-256', r.sha256, true], ['IP', r.ip + ':' + r.port], [T('Verbindungsaufbau'), r.ms + ' ms']]),
                    r.chain && r.chain.length > 1 ? CF.card(T('Zertifikatskette'), 'link', el('div', { class: 'hops' }, r.chain.map((c, i) => el('div', {}, CF.badge(String(i)), el('span', { text: c.subject + ' ← ' + c.issuer }), el('span', { class: 'tiny faint', text: T('bis %s', dt(c.to)) }))))) : null);
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, bar, out);
    },
};

/* ---------- DNS ---------- */
CF.views.dns = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        const bar = askBar('dns', 'example.org', T('Abfragen'), async v => {
            fill(out, spin());
            try {
                const r = await CF.api('dns', { name: v });
                const types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'CAA', 'SOA', 'PTR'];
                const groups = types.map(t => [t, r.records.filter(x => x.type === t)]).filter(g => g[1].length);
                fill(out,
                    r.mail ? CF.card(T('E-Mail-Sicherheit'), 'mail', el('div', { class: 'stack' },
                        CF.msg(r.mail.mx ? 'ok' : 'info', r.mail.mx ? T('%s Mailserver (MX)', r.mail.mx) : T('Kein Mailserver eingetragen.')),
                        CF.msg(r.mail.spf ? 'ok' : 'warn', r.mail.spf ? 'SPF: ' + r.mail.spf : T('Kein SPF-Eintrag: Fremde können leichter E-Mails im Namen dieser Domain fälschen.')),
                        CF.msg(r.mail.dmarc ? (r.mail.policy === 'none' ? 'warn' : 'ok') : 'warn', r.mail.dmarc ? 'DMARC: ' + r.mail.dmarc + (r.mail.policy === 'none' ? ' · ' + T('nur beobachten (p=none)') : '') : T('Kein DMARC-Eintrag.')))) : null,
                    groups.length ? el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: T('Typ') }), el('th', { text: T('Wert') }), el('th', { class: 'num', text: 'TTL' })),
                        groups.map(([t, list]) => list.map(x => el('tr', {}, el('td', { class: 'mono', text: t }), el('td', { class: 'mono break', text: x.value }), el('td', { class: 'num', text: x.ttl ? x.ttl + ' s' : '' }))))))
                        : CF.msg('warn', T('Keine Einträge gefunden.')),
                    el('div', { class: 'row' }, btn(T('WHOIS'), 'btn-ghost btn-sm', () => CF.go('whois', { q: v }), 'user'), btn(T('SSL prüfen'), 'btn-ghost btn-sm', () => CF.go('ssl', { q: v }), 'lock')));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, bar, out, el('p', { class: 'tiny faint', text: T('Gefragt wird der DNS-Server, den dieser Server benutzt. Auch eine IP-Adresse geht (Reverse DNS).') }));
    },
};

/* ---------- WHOIS ---------- */
CF.views.whois = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        const bar = askBar('whois', 'example.org · 1.1.1.1 · AS13335', T('Abfragen'), async v => {
            fill(out, spin());
            try {
                const r = await CF.api('whois', { q: v });
                if (r.free) { fill(out, el('div', { class: 'status-big ok' }, el('span', { html: CF.icon('check') }), el('span', { text: T('Diese Domain scheint frei zu sein!') }))); return; }
                if (r.raw) { fill(out, CF.msg('info', T('Für diese Endung gibt es kein RDAP, hier die Antwort des WHOIS-Servers:')), el('div', { class: 'out-box', text: r.raw })); return; }
                const ev = r.events || {};
                const ent = r.entities || {};
                const exp = ev.expiration ? Math.round((new Date(ev.expiration) - new Date()) / 86400000) : null;
                fill(out,
                    kv([[T('Name'), r.name], [T('Kennung'), r.handle], [T('Registrar'), ent.registrar], [T('Inhaber'), ent.registrant || T('nicht öffentlich')],
                        [T('Registriert'), ev.registration], [T('Zuletzt geändert'), ev['last changed']], [T('Läuft ab'), ev.expiration ? ev.expiration + (exp !== null ? ' (' + (exp >= 0 ? T('in %s Tagen', exp) : T('abgelaufen')) + ')' : '') : ''],
                        [T('Status'), (r.status || []).join(', ')], [T('Nameserver'), (r.nameservers || []).join(', ')], r.dnssec !== null && r.dnssec !== undefined ? ['DNSSEC', r.dnssec ? T('an') : T('aus')] : null,
                        r.range ? [T('Adressbereich'), r.range + (r.cidr && r.cidr.length ? ' (' + r.cidr.join(', ') + ')' : '')] : null, r.asn ? ['AS', r.asn] : null,
                        r.country ? [T('Land'), r.country] : null, r.type ? [T('Art'), r.type] : null,
                        ent.abuse ? [T('Missbrauch melden'), ent.abuse] : null]),
                    el('p', { class: 'tiny faint', text: T('Quelle: %s. Inhaberdaten sind wegen Datenschutz meist nicht öffentlich.', r.source || 'RDAP') }));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, bar, out);
    },
};

/* ---------- Link-Checker ---------- */
function checkTable(results) {
    return el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' },
        el('tr', {}, el('th', { text: T('Adresse') }), el('th', { text: T('Ergebnis') }), el('th', { class: 'num', text: 'ms' })),
        results.map(r => {
            const dead = r.status === 404 || r.status === 410 || r.error === 'nx' || r.error === 'connect';
            const moved = r.final !== r.url;
            return el('tr', {},
                el('td', { class: 'small break' }, el('div', { text: r.url }), moved ? el('div', { class: 'tiny faint', text: '→ ' + r.final }) : null),
                el('td', {}, el('span', { class: dead ? 'pill-bad' : r.status >= 200 && r.status < 400 ? (moved ? 'pill-mid' : 'pill-ok') : 'pill-mid',
                    text: dead ? T('tot') : r.status >= 200 && r.status < 400 ? (moved ? T('umgeleitet') : 'OK') : (r.status ? String(r.status) : T('Fehler')) }),
                    r.errText ? el('div', { class: 'tiny faint', text: r.errText }) : (r.status ? el('div', { class: 'tiny faint', text: 'HTTP ' + r.status }) : null),
                    dead ? el('a', { class: 'tiny', href: 'https://web.archive.org/web/*/' + r.url, target: '_blank', rel: 'noopener noreferrer', text: T('im Internet Archive') }) : null),
                el('td', { class: 'num small', text: r.ms || '' }));
        })));
}
CF.views.linkcheck = {
    mount(root, params) {
        const ta = el('textarea', { class: 'in code', rows: 8, placeholder: 'https://example.org/\nhttps://example.com/alte-seite\n…' });
        if (params.text) ta.value = params.text;
        const out = el('div', { class: 'stack' });
        const go = btn(T('Links prüfen'), 'btn-primary btn-lg', async () => {
            const urls = (ta.value.match(/https?:\/\/[^\s<>"')\]]+/g) || ta.value.split(/\s+/).filter(x => /\.[a-z]{2,}/i.test(x))).slice(0, 200);
            if (!urls.length) { ta.focus(); return; }
            go.disabled = true;
            const all = [];
            for (let i = 0; i < urls.length; i += 50) {
                fill(out, spin(T('Prüfe %s von %s …', Math.min(i + 50, urls.length), urls.length)));
                try { const r = await CF.api('check', { urls: urls.slice(i, i + 50) }); all.push(...r.results); } catch (e) { fill(out, CF.msg('danger', e.message)); go.disabled = false; return; }
            }
            const dead = all.filter(r => r.status === 404 || r.status === 410 || r.error === 'nx' || r.error === 'connect');
            fill(out, el('div', { class: 'row' }, CF.badge(T('%s geprüft', all.length)), CF.badge(T('%s tot', dead.length), dead.length ? 'danger' : 'ok'),
                CF.badge(T('%s umgeleitet', all.filter(r => r.final !== r.url).length), 'info'),
                btn('CSV', 'btn-ghost btn-sm', () => CF.download('linkcheck-' + CF.fileStamp() + '.csv', new Blob([CF.csv([['url', 'status', 'ziel', 'fehler', 'ms']].concat(all.map(r => [r.url, r.status, r.final, r.errText, r.ms])))], { type: 'text/csv;charset=utf-8' })), 'download')),
                checkTable(all));
            go.disabled = false;
        }, 'test');
        fill(root, el('div', { class: 'stack lg' }, field(T('Adressen, eine pro Zeile (oder einfach Text mit Links hineinkopieren)'), ta), el('div', { class: 'row' }, go,
            el('span', { class: 'tiny faint', text: T('bis zu 200 Links, je 50 parallel') })), out,
            el('p', { class: 'tiny faint', text: T('Als tot gelten 404, 410, nicht vorhandene Domains und Verbindungsfehler. Manche Seiten sperren automatische Abrufe (403, 429), die sind deshalb nicht automatisch kaputt.') })));
    },
};
CF.checkTable = checkTable;

/* ---------- Link-Vorschau ---------- */
CF.views.vorschau = {
    mount(root, params) {
        const out = el('div', { class: 'stack' });
        const bar = askBar('preview', 'https://example.org/artikel', T('Vorschau'), async v => {
            fill(out, spin(T('Seite wird gelesen …')));
            try {
                const r = await CF.api('preview', { url: v });
                const title = r.ogTitle || r.twTitle || r.title;
                const desc = r.ogDesc || r.twDesc || r.desc;
                const host = (() => { try { return new URL(r.final).host; } catch (e) { return ''; } })();
                const img = r.image ? el('img', { src: r.image, alt: '' }) : null;
                const card = (cls, label) => el('div', { class: 'stack' }, el('div', { class: 'small muted', text: label }),
                    el('div', { class: 'lp lp--' + cls }, img ? img.cloneNode() : null,
                        el('div', { class: 'lp__body' }, el('div', { class: 'lp__site' }, r.icon ? el('img', { src: r.icon, alt: '', class: 'lp__ico' }) : null, el('span', { text: r.site || host })),
                            el('div', { class: 'lp__title', text: title || T('(kein Titel)') }), desc ? el('div', { class: 'lp__desc', text: desc }) : null)));
                const checks = [
                    [!!(r.ogTitle), T('og:title gesetzt')], [!!(r.ogDesc || r.desc), T('Beschreibung vorhanden')], [!!r.ogImage, T('og:image gesetzt')],
                    [!!r.image, r.ogImage && !r.image ? T('og:image lässt sich nicht laden') : T('Bild lädt') + (r.imageInfo ? ' (' + r.imageInfo + ')' : '')],
                    [!!r.twCard, T('twitter:card gesetzt')], [!!r.canonical, T('Canonical-Adresse gesetzt')], [!/noindex/i.test(r.robots || ''), r.robots ? 'robots: ' + r.robots : T('keine robots-Sperre')],
                ];
                fill(out, el('div', { class: 'layout' },
                    el('div', { class: 'stack lg' }, card('chat', T('Messenger (WhatsApp, Signal, Telegram)')), card('social', T('Soziale Netze (Mastodon, LinkedIn, X)')), card('search', T('Suchergebnis'))),
                    el('div', { class: 'stack lg' },
                        CF.card(T('Prüfung'), 'test', el('div', { class: 'stack' }, checks.map(([ok, t]) => CF.msg(ok ? 'ok' : 'warn', t)))),
                        CF.card(T('Gefundene Angaben'), 'list', kv([[T('Titel'), r.title], ['og:title', r.ogTitle], ['og:description', r.ogDesc], ['description', r.desc], ['og:image', r.ogImage, r.ogImage ? true : false],
                            ['og:type', r.type], ['twitter:card', r.twCard], ['canonical', r.canonical], [T('Sprache'), r.lang], ['theme-color', r.theme], [T('Endadresse'), r.final]])))));
            } catch (e) { fill(out, CF.msg('danger', e.message)); }
        }, params);
        shell(root, bar, out);
    },
};

/* ---------- Lesezeichen aufräumen ---------- */
CF.views.lesezeichen = {
    mount(root) {
        let items = [];   // { title, url, folder, add, node }
        let doc = null;
        const out = el('div', { class: 'stack' });
        const drop = el('div', { class: 'drop', onclick: () => pick() }, el('span', { html: CF.icon('upload'), style: { display: 'inline-block', verticalAlign: 'middle', marginRight: '.4rem' } }),
            T('Lesezeichen-Datei (HTML) hierher ziehen oder klicken'));
        drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
        drop.addEventListener('dragleave', () => drop.classList.remove('over'));
        drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); if (e.dataTransfer.files[0]) read(e.dataTransfer.files[0]); });
        async function pick() { const f = await CF.pickFile('.html,.htm,text/html'); if (f) read(f); }
        const norm = u => { try { const x = new URL(u); x.hash = ''; let s = x.href.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''); return s.toLowerCase(); } catch (e) { return u; } };
        async function read(f) {
            const html = await f.text();
            doc = new DOMParser().parseFromString(html, 'text/html');
            items = [];
            doc.querySelectorAll('a[href]').forEach(a => {
                const folders = [];
                let p = a.closest('dl');
                while (p) { const h = p.previousElementSibling && p.previousElementSibling.tagName === 'H3' ? p.previousElementSibling : (p.parentElement && p.parentElement.querySelector(':scope > h3')); if (h) folders.unshift(h.textContent.trim()); p = p.parentElement && p.parentElement.closest('dl'); }
                items.push({ title: a.textContent.trim(), url: a.getAttribute('href'), folder: folders.join(' / '), node: a, status: null, remove: false });
            });
            analyse();
        }
        function analyse() {
            const groups = {};
            items.forEach(i => { if (/^https?:/i.test(i.url)) (groups[norm(i.url)] = groups[norm(i.url)] || []).push(i); });
            const dups = Object.values(groups).filter(g => g.length > 1);
            dups.forEach(g => g.slice(1).forEach(i => { i.remove = true; i.why = 'dup'; }));
            render(dups);
        }
        function render(dups) {
            const web = items.filter(i => /^https?:/i.test(i.url));
            const dead = items.filter(i => i.why === 'dead');
            const rm = items.filter(i => i.remove);
            const checkBtn = btn(T('Tote Links suchen'), 'btn-second', async () => {
                checkBtn.disabled = true;
                const todo = web.filter(i => !i.remove);
                for (let k = 0; k < todo.length; k += 50) {
                    checkBtn.lastChild.textContent = T('Prüfe %s von %s …', Math.min(k + 50, todo.length), todo.length);
                    try {
                        const r = await CF.api('check', { urls: todo.slice(k, k + 50).map(i => i.url) });
                        r.results.forEach((x, j) => { const it = todo[k + j]; if (!it) return; it.status = x; if (x.status === 404 || x.status === 410 || x.error === 'nx') { it.remove = true; it.why = 'dead'; } });
                    } catch (e) { CF.toast(e.message, 'bad'); break; }
                }
                render(dups);
            }, 'test');
            fill(out,
                el('div', { class: 'row' }, CF.badge(T('%s Lesezeichen', items.length)), CF.badge(T('%s doppelt', items.filter(i => i.why === 'dup').length), 'warn'), CF.badge(T('%s tot', dead.length), dead.length ? 'danger' : ''),
                    CF.badge(T('%s werden entfernt', rm.length), 'acc')),
                el('div', { class: 'row' }, CF.loggedIn() ? checkBtn : el('span', { class: 'small muted', text: T('Tote Links prüfen geht nach dem Anmelden.') }),
                    btn(T('Bereinigte Datei herunterladen'), 'btn-primary', () => download(), 'download', { disabled: !doc })),
                rm.length ? el('div', { class: 'tbl-wrap' }, el('table', { class: 'tbl' }, el('tr', {}, el('th', { text: T('Entfernen') }), el('th', { text: T('Lesezeichen') }), el('th', { text: T('Grund') })),
                    rm.slice(0, 500).map(i => el('tr', {}, el('td', {}, el('input', { type: 'checkbox', checked: true, onchange: e => { i.remove = e.target.checked; } })),
                        el('td', { class: 'small break' }, el('b', { text: i.title || i.url }), el('div', { class: 'tiny faint', text: (i.folder ? i.folder + ' · ' : '') + i.url })),
                        el('td', { class: 'small', text: i.why === 'dup' ? T('doppelt') : T('tot (%s)', i.status && (i.status.status || i.status.errText)) }))))) : CF.msg('ok', T('Nichts zum Aufräumen gefunden.')));
        }
        function download() {
            items.forEach(i => { if (i.remove && i.node) { const dt = i.node.closest('dt'); (dt || i.node).remove(); } });
            const html = '<!DOCTYPE NETSCAPE-Bookmark-file-1>\n' + doc.documentElement.outerHTML.replace(/^<html>|<\/html>$/g, '');
            CF.download('lesezeichen-aufgeraeumt-' + CF.fileStamp() + '.html', new Blob([html], { type: 'text/html;charset=utf-8' }));
            CF.toast(T('Fertig. Die Datei im Browser über „Lesezeichen importieren“ einlesen.'));
        }
        fill(root, el('div', { class: 'stack lg' },
            CF.msg('info', T('So geht es: Im Browser die Lesezeichen als HTML-Datei exportieren (Firefox: Lesezeichen verwalten → Importieren und Sichern → Exportieren; Chrome/Edge: Lesezeichen-Manager → ⋮ → Exportieren). Hier einlesen, prüfen, bereinigte Datei herunterladen und wieder importieren.'), 'info'),
            drop, out,
            el('p', { class: 'tiny faint', text: T('Die Datei wird nur in deinem Browser gelesen. Zum Prüfen auf tote Links schickt der Browser nur die Adressen an deinen eigenen Server, gespeichert wird nichts.') })));
    },
};
})();
