/* Suchmaschine (aus CodeForge) – Scanner: Kamera, Bilder, Screenshots, Serien- und Inventur-Modus, Ergebnis-Karte.
   Neu hier: Gescanntes direkt suchen (Barcode, ISBN, Text), auf Wunsch sofort nach dem Scan. */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T } = CF;

const FORMAT_NAMES = {
    QRCode: 'QR Code', MicroQRCode: 'Micro QR', RMQRCode: 'rMQR', DataMatrix: 'Data Matrix', Aztec: 'Aztec', PDF417: 'PDF417', MaxiCode: 'MaxiCode',
    EAN13: 'EAN-13', EAN8: 'EAN-8', UPCA: 'UPC-A', UPCE: 'UPC-E', Code128: 'Code 128', Code39: 'Code 39', Code93: 'Code 93', Codabar: 'Codabar',
    ITF: 'ITF', ITF14: 'ITF-14', DataBar: 'GS1 DataBar', DataBarExpanded: 'DataBar Expanded', DataBarLimited: 'DataBar Limited', DataBarExp: 'DataBar Expanded',
    DataBarLtd: 'DataBar Limited', DXFilmEdge: 'DX Film Edge', PZN: 'PZN', ISBN: 'ISBN', 'GS1-128': 'GS1-128', MicroPDF417: 'MicroPDF417', CompactPDF417: 'Compact PDF417',
};
CF.formatName = f => FORMAT_NAMES[f] || f || '';

const TYPE_ICON = { url: 'link', wifi: 'wifi', vcard: 'user', event: 'calendar', geo: 'pin', tel: 'phone', sms: 'sms', email: 'mail', girocode: 'bank', swissqr: 'bank',
    otp: 'key', gs1: 'box', bcbp: 'plane', crypto: 'coins', number: 'hash', text: 'text', ticket: 'ticket', binary: 'grid', pair: 'pair' };
CF.typeIcon = t => TYPE_ICON[t] || 'qr';

/* ---------- Gescanntes suchen ---------- */

/** Suchbegriff zu einem gescannten Inhalt ('' = nicht sinnvoll, z. B. WLAN-Passwörter, Bankdaten, Links) */
CF.searchQuery = (text, p) => {
    p = p || CF.Parse.content(text, {}, CF.lang);
    const t = String(text || '').trim();
    const d = t.replace(/[\s-]/g, '');
    switch (p.type) {
        case 'number': {
            const best = (p.data.hits || []).find(h => h.valid) || (p.data.hits || [])[0] || {};
            if (/^(iban|card|imei)$/.test(best.id || '')) return '';
            if (/^97[89]\d{10}$/.test(d) || /^isbn/.test(best.id || '')) return 'ISBN ' + d;
            if (/^0\d{13}$/.test(d)) return d.slice(1);
            return d;
        }
        case 'gs1': return p.data.gtin ? String(p.data.gtin).replace(/^0(?=\d{13}$)/, '') : '';
        case 'text': return t.length <= 300 ? t.replace(/\s+/g, ' ') : '';
        case 'vcard': case 'geo': case 'event': return p.summary || '';
        default: return '';
    }
};
CF.searchFor = (q) => { location.href = (CF.boot.search || './?q=') + encodeURIComponent(q); };

/* ---------- Ergebnis-Karte: zeigt, was in einem Code steckt, mit passenden Aktionen ---------- */

function saveText(name, text, type) { CF.download(name, new Blob([text], { type })); }

function safetyBlock(s, onResolve) {
    const box = el('div', { class: 'stack' });
    const host = s.unicodeHost || s.host;
    if (host) {
        const reg = s.registrable;
        const sub = host.length > reg.length ? host.slice(0, host.length - reg.length) : '';
        box.append(el('div', { class: 'domain' }, sub ? el('span', { class: 'sub', text: sub }) : null, el('span', { text: s.unicodeHost && s.unicodeHost !== s.host ? s.unicodeHost.slice(sub.length) : reg })));
    }
    const label = { ok: T('Sieht unauffällig aus'), info: T('Hinweise beachten'), warn: T('Vorsicht'), danger: T('Gefährlich: nicht öffnen') }[s.level];
    const list = el('ul', { style: { margin: '.3rem 0 0', paddingLeft: '1.1rem' } }, s.findings.filter(f => f.level !== 'ok').map(f => el('li', { text: T(f.text, ...(f.args || [])) })));
    box.append(CF.msg(s.level === 'ok' ? 'ok' : s.level, el('div', {}, el('b', { text: label }), list.children.length ? list : null), s.level === 'ok' ? 'shield' : 'alert'));
    if (onResolve) box.append(onResolve);
    return box;
}

function resolveBox(url) {
    const out = el('div', { class: 'stack' });
    const b = btn(T('Ziel prüfen, ohne zu öffnen'), 'btn-soft btn-sm', async () => {
        b.disabled = true;
        fill(out, b, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Server folgt den Weiterleitungen …')));
        try {
            const r = await CF.api('resolve', { url });
            const hops = el('div', { class: 'hops' }, r.hops.map((h, i) => el('div', {},
                CF.badge(h.status ? String(h.status) : (h.note === 'blocked' ? T('gesperrt') : '–'), h.status >= 200 && h.status < 300 ? 'ok' : h.status >= 300 && h.status < 400 ? 'info' : 'warn'),
                el('code', { class: 'mono', text: (i + 1) + '. ' + h.url }))));
            const final = r.final;
            const s = CF.Parse.analyzeUrl(final, CF.boot.base);
            fill(out, el('div', { class: 'small muted', text: r.hops.length > 1 ? T('%s Stationen bis zum Ziel:', r.hops.length) : T('Keine Weiterleitung, das ist schon das Ziel.') }), hops,
                el('div', { class: 'small', style: { fontWeight: 700 } }, T('Endziel')), safetyBlock(s),
                el('div', { class: 'row' }, btn(T('Endziel öffnen'), s.level === 'danger' ? 'btn-danger btn-sm' : 'btn-second btn-sm', () => openChecked(final, s), 'external'), btn(T('Kopieren'), 'btn-ghost btn-sm', () => CF.copy(final), 'copy')));
            if (r.hops.some(h => h.note === 'blocked')) out.append(CF.msg('warn', T('Ein Ziel liegt in einem privaten Netz und wurde nicht abgerufen.')));
        } catch (e) {
            b.disabled = false;
            fill(out, b, CF.msg('warn', e.message));
        }
    }, 'search');
    out.append(b);
    return out;
}

async function openChecked(url, s) {
    if (s && s.level === 'danger') {
        if (!await CF.confirm(T('Dieser Link sieht gefährlich aus. Wirklich öffnen?'), T('Trotzdem öffnen'), true)) return;
    } else if (s && s.level === 'warn') {
        if (!await CF.confirm(T('Bei diesem Link gibt es Warnzeichen. Trotzdem öffnen?'), T('Öffnen'))) return;
    }
    CF.openUrl(url);
}

function kvGrid(fields) {
    const g = el('div', { class: 'kv' });
    fields.forEach(([k, v, o]) => {
        o = o || {};
        const val = el('div', { class: 'v' + (o.mono ? ' mono' : '') + (o.pre ? ' pre' : '') + (o.strong ? ' strong' : '') });
        if (o.href) val.append(el('a', { href: o.href, text: v }));
        else if (o.link) val.append(el('a', { href: v, text: v, target: '_blank', rel: 'noopener noreferrer' }));
        else val.textContent = v;
        if (o.secret) { val.classList.add('secret'); val.title = T('Zum Anzeigen tippen'); val.addEventListener('click', () => val.classList.toggle('show')); }
        g.append(el('div', { class: 'k', text: T(k) }), val, el('div', { class: 'a' }, o.copy ? ibtn('copy', T('Kopieren'), () => CF.copy(o.raw || v), 'sm') : null));
    });
    return g;
}

function totpBox(d) {
    const code = el('div', { class: 'totp', text: '······' });
    const bar = el('div', { class: 'meter' }, el('span', { style: { width: '100%' } }));
    let alive = true, last = -1;
    const tick = async () => {
        if (!alive || !code.isConnected) { if (code.isConnected === false && last >= 0) alive = false; if (!alive) return; }
        const p = d.period || 30;
        const left = p - (Math.floor(Date.now() / 1000) % p);
        bar.firstChild.style.width = (left / p * 100) + '%';
        const slot = Math.floor(Date.now() / 1000 / p);
        if (slot !== last) {
            last = slot;
            const c = await CF.Parse.totp(d.secret, d.digits, p, d.algorithm);
            code.textContent = c ? c.replace(/(\d{3})(?=\d)/g, '$1 ') : T('nicht verfügbar');
        }
        setTimeout(tick, 1000);
    };
    tick();
    return el('div', { class: 'stack', style: { gap: '.4rem' } }, el('div', { class: 'small muted', text: T('Aktueller Code') }), code, bar);
}

function ticketBox(bytes) {
    const box = el('div', { class: 'stack' }, el('div', { class: 'row small muted' }, el('span', { class: 'spinner' }), T('Ticket wird entpackt …')));
    CF.Ticket.decodeUic(bytes).then(t => {
        const rows = [['Ausgeber', (t.company ? t.company + ' · ' : '') + 'RICS ' + t.rics], ['Format', 'UIC 918.3 v' + t.version]];
        if (t.head) {
            rows.push(['Ticket-Nummer', t.head.ticket, { copy: true, mono: true }]);
            if (t.head.issued) rows.push(['Ausgestellt', CF.fmtDateTime(t.head.issued.getTime())]);
        }
        rows.push(['Datensätze', t.records.map(r => r.tag).join(', ')]);
        const lay = CF.Ticket.layoutText(t.layout);
        fill(box, kvGrid(rows),
            lay ? el('div', { class: 'stack', style: { gap: '.35rem' } }, el('div', { class: 'small muted', text: T('Ticket-Ausdruck (wie auf dem Papierticket)') }),
                el('pre', { class: 'mono', style: { margin: 0, padding: '.8rem', borderRadius: '.7rem', background: '#fff', color: '#0f172a', fontSize: '.72rem', lineHeight: 1.35, overflowX: 'auto' }, text: lay })) : null,
            t.records.some(r => r.tag === 'U_FLEX') ? el('div', { class: 'small muted', text: T('Enthält zusätzlich FCB-Daten (U_FLEX), die hier nicht entschlüsselt werden.') }) : null);
    }).catch(e => fill(box, CF.msg('warn', e.message === 'nodecomp' ? T('Dieser Browser kann die Ticketdaten nicht entpacken.') : T('Die Ticketdaten ließen sich nicht lesen.'))));
    return box;
}

/**
 * Ergebnis-Karte. meta: { format, contentType, sid, version, ecLevel, symbol } von der Engine
 * opts: { historyId, compact, onCreate }
 */
CF.resultCard = (text, meta, opts) => {
    meta = meta || {};
    opts = opts || {};
    const p = CF.Parse.content(text, meta, CF.lang);
    const level = p.safety ? p.safety.level : (p.warnings.some(w => w.level === 'danger') ? 'danger' : '');
    const ico = el('div', { class: 'result-ico' + (level === 'danger' ? ' danger' : level === 'warn' ? ' warn' : ''), icon: CF.typeIcon(p.type) });
    const sub = [CF.formatName(meta.format), meta.version && /QR/.test(meta.format || '') ? T('Version %s', meta.version) : '', meta.ecLevel ? T('Fehlerkorrektur %s', meta.ecLevel) : ''].filter(Boolean).join(' · ');
    const head = el('div', { class: 'result-head' }, ico, el('div', { class: 'grow' }, el('div', { class: 'result-title', text: T(p.title) }), sub ? el('div', { class: 'result-sub', text: sub }) : null));
    if (opts.historyId) {
        const h = CF.Store.get(opts.historyId);
        const star = ibtn('star', T('Favorit'), () => { const cur = CF.Store.get(opts.historyId); if (!cur) return; CF.Store.update(opts.historyId, { fav: !cur.data.fav }); star.classList.toggle('on'); }, 'star' + (h && h.data.fav ? ' on' : ''));
        head.append(star);
    }
    const box = el('div', { class: 'result' }, head);

    if (p.type === 'url' || (p.type === 'gs1' && p.safety)) {
        box.append(safetyBlock(p.safety, p.safety.scheme === 'http' || p.safety.scheme === 'https' ? (CF.session.settings && CF.session.settings.resolve === false ? null : resolveBox(p.data.url)) : null));
    }
    if (p.fields.length && p.type !== 'url') box.append(kvGrid(p.fields));
    if (p.type === 'url') box.append(kvGrid(p.fields.slice(0, 1)));
    p.warnings.forEach(w => box.append(CF.msg(w.level, T(w.text))));
    if (p.type === 'otp' && p.data.kind === 'totp') box.append(totpBox(p.data));
    if (p.type === 'ticket') box.append(ticketBox(p.data.bytes));
    if (p.type === 'number') {
        const hits = p.data.hits.slice(1);
        if (hits.length) box.append(el('div', { class: 'small muted', text: T('Könnte auch sein: %s', hits.map(h => T(h.name)).join(', ')) }));
    }

    const acts = el('div', { class: 'row' });
    const d = p.data;
    switch (p.type) {
        case 'url':
            if (p.safety.scheme === 'http' || p.safety.scheme === 'https') {
                acts.append(btn(T('Öffnen'), p.safety.level === 'danger' ? 'btn-danger' : 'btn-primary', () => openChecked(d.url, p.safety), 'external'));
                if (d.clean) acts.append(btn(T('Ohne Tracking öffnen'), 'btn-second', () => openChecked(d.clean, p.safety), 'shield'));
            }
            break;
        case 'gs1': if (d.link) acts.append(btn(T('Öffnen'), 'btn-primary', () => openChecked(d.url, p.safety), 'external')); break;
        case 'wifi': if (d.P) acts.append(btn(T('Passwort kopieren'), 'btn-primary', () => CF.copy(d.P), 'key')); break;
        case 'vcard':
            acts.append(btn(T('Kontakt speichern'), 'btn-primary', () => saveText((d.name || 'kontakt').replace(/[^\w\-]+/g, '_') + '.vcf', d.vcf.replace(/\r?\n/g, '\r\n'), 'text/vcard'), 'download'));
            if (d.tel) acts.append(btn(T('Anrufen'), 'btn-second', () => { location.href = 'tel:' + d.tel.replace(/\s/g, ''); }, 'phone'));
            break;
        case 'event': acts.append(btn(T('In den Kalender'), 'btn-primary', () => saveText('termin.ics', d.ics, 'text/calendar'), 'calendar')); break;
        case 'geo':
            acts.append(btn(T('Karte öffnen'), 'btn-primary', () => CF.openUrl('https://www.openstreetmap.org/?mlat=' + d.lat + '&mlon=' + d.lon + '#map=17/' + d.lat + '/' + d.lon), 'pin'));
            acts.append(btn(T('In Karten-App'), 'btn-second', () => { location.href = 'geo:' + d.lat + ',' + d.lon; }, 'external'));
            break;
        case 'tel': acts.append(btn(T('Anrufen'), 'btn-primary', () => { location.href = 'tel:' + d.num.replace(/\s/g, ''); }, 'phone')); break;
        case 'sms': acts.append(btn(T('SMS schreiben'), 'btn-primary', () => { location.href = 'sms:' + d.num + (d.body ? (/iPhone|iPad/.test(navigator.userAgent) ? '&' : '?') + 'body=' + encodeURIComponent(d.body) : ''); }, 'sms')); break;
        case 'email': acts.append(btn(T('E-Mail schreiben'), 'btn-primary', () => { location.href = d.href; }, 'mail')); break;
        case 'girocode': acts.append(btn(T('IBAN kopieren'), 'btn-primary', () => CF.copy(d.iban), 'copy')); break;
        case 'number': acts.append(btn(T('Im Nummern-Detektiv'), 'btn-second', () => CF.go('convert', { q: text }), 'hash')); break;
    }
    const sq = CF.searchQuery(text, p);
    if (sq) acts.prepend(btn(T('Suchen'), acts.querySelector('.btn-primary') ? 'btn-second' : 'btn-primary', () => CF.searchFor(sq), 'search', { title: sq }));
    acts.append(btn(T('Kopieren'), 'btn-second', () => CF.copy(text), 'copy'));
    if (navigator.share) acts.append(ibtn('share', T('Teilen'), () => CF.share({ text })));
    if (!opts.compact) {
        acts.append(ibtn('wand', T('Als Code neu erstellen'), () => CF.go('create', { text, format: meta.format })));
    }
    box.append(acts);
    if (meta.seq) box.append(CF.msg('info', T('Teil %s von %s eines zusammengesetzten Codes.', meta.seq.index + 1, meta.seq.size)));
    return box;
};

/* ---------- Kamera ---------- */

class Camera {
    constructor() { this.stream = null; this.track = null; this.caps = {}; this.torchOn = false; }
    static supported() { return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext !== false; }
    static async devices() {
        try { return (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput'); } catch (e) { return []; }
    }
    async start(video, deviceId) {
        this.stop();
        const v = { width: { ideal: 1920 }, height: { ideal: 1080 } };
        if (deviceId) v.deviceId = { exact: deviceId }; else v.facingMode = { ideal: 'environment' };
        try { this.stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: v }); }
        catch (e) {
            if (e && (e.name === 'OverconstrainedError' || e.name === 'NotReadableError')) this.stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
            else throw e;
        }
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');
        video.muted = true;
        video.srcObject = this.stream;
        await video.play().catch(() => {});
        this.track = this.stream.getVideoTracks()[0];
        this.caps = this.track && this.track.getCapabilities ? this.track.getCapabilities() : {};
        this.deviceId = this.track && this.track.getSettings ? this.track.getSettings().deviceId : '';
        try { if (this.caps.focusMode && this.caps.focusMode.indexOf('continuous') >= 0) await this.track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (e) { /* egal */ }
        this.torchOn = false;
    }
    stop() {
        if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        this.stream = null; this.track = null;
    }
    get hasTorch() { return !!this.caps.torch; }
    get zoomRange() { return this.caps.zoom ? this.caps.zoom : null; }
    async torch(on) {
        if (!this.track) return false;
        try { await this.track.applyConstraints({ advanced: [{ torch: on }] }); this.torchOn = on; return true; } catch (e) { return false; }
    }
    async zoom(v) { if (this.track) { try { await this.track.applyConstraints({ advanced: [{ zoom: v }] }); } catch (e) { /* egal */ } } }
}
CF.Camera = Camera;

/** Liest Bilder von der Kamera, bis stop() gerufen wird. onResults(results, w, h) */
function scanLoop(video, onResults, opts) {
    opts = opts || {};
    const work = document.createElement('canvas');
    const ctx = work.getContext('2d', { willReadFrequently: true });
    let running = true, busy = false, frame = 0, timer = null, paused = false;
    const tick = async () => {
        if (!running) return;
        if (!busy && !paused && video.readyState >= 2 && video.videoWidth) {
            busy = true;
            frame++;
            const vw = video.videoWidth, vh = video.videoHeight;
            const k = Math.min(1, (opts.maxSide || 1280) / Math.max(vw, vh));
            work.width = Math.round(vw * k);
            work.height = Math.round(vh * k);
            ctx.drawImage(video, 0, 0, work.width, work.height);
            try {
                const img = ctx.getImageData(0, 0, work.width, work.height);
                const res = await CF.Engine.read(img, { tryHarder: frame % 2 === 0, max: 8, symbol: !!opts.symbol, formats: opts.formats });
                if (running && !paused) onResults(res, work.width, work.height);
            } catch (e) { /* nächster Versuch */ }
            busy = false;
        }
        timer = setTimeout(tick, 50);
    };
    tick();
    return {
        stop() { running = false; clearTimeout(timer); },
        pause(v) { paused = v; },
        get paused() { return paused; },
        grab() { return work; },
    };
}
CF.scanLoop = scanLoop;

function drawHits(canvas, results, w, h, color) {
    canvas.width = w; canvas.height = h;
    const g = canvas.getContext('2d');
    g.clearRect(0, 0, w, h);
    results.forEach(r => {
        const p = r.position;
        if (!p) return;
        const pts = [p.topLeft, p.topRight, p.bottomRight, p.bottomLeft];
        g.beginPath();
        pts.forEach((pt, i) => (i ? g.lineTo(pt.x, pt.y) : g.moveTo(pt.x, pt.y)));
        g.closePath();
        g.lineWidth = Math.max(3, w / 220);
        g.strokeStyle = color || '#34d399';
        g.fillStyle = 'rgba(52, 211, 153, .18)';
        g.fill();
        g.stroke();
    });
}

/** Dialog mit Kamera, liefert den ersten Treffer (für Kundenkarten und Kopplung) */
CF.scanOnce = (title, formats) => new Promise(resolve => {
    const video = el('video', { playsinline: true, muted: true, autoplay: true });
    const ov = el('canvas', { class: 'ov' });
    const viewer = el('div', { class: 'viewer' }, video, ov, el('div', { class: 'frame' }, el('span')), el('div', { class: 'laser' }));
    const cam = new Camera();
    let loop = null, done = false;
    const finish = (r) => { if (done) return; done = true; if (loop) loop.stop(); cam.stop(); m.close(); resolve(r); };
    const m = CF.modal({
        title, body: el('div', { class: 'stack' }, viewer, el('div', { class: 'row' }, btn(T('Bild wählen'), 'btn-second btn-sm', async () => {
            const f = await CF.pickFile('image/*');
            if (!f) return;
            const r = await CF.Engine.readBlob(f);
            if (r.results.length) { CF.feedback(); finish(r.results[0]); } else CF.toast(T('Kein Code im Bild gefunden.'), 'warn');
        }, 'image'))),
        onClose: () => { if (!done) { done = true; if (loop) loop.stop(); cam.stop(); resolve(null); } },
    });
    if (!Camera.supported()) { viewer.append(el('div', { class: 'idle' }, el('p', { text: T('Kamera nicht verfügbar. Bitte ein Bild wählen.') }))); return; }
    CF.Engine.ready().catch(() => {});
    cam.start(video).then(() => {
        loop = scanLoop(video, (res, w, h) => {
            drawHits(ov, res, w, h);
            if (res.length) { CF.feedback(); finish(res[0]); }
        }, { formats });
    }).catch(() => { viewer.append(el('div', { class: 'idle' }, el('p', { text: T('Kein Zugriff auf die Kamera. Bitte ein Bild wählen.') }))); });
});

/* ---------- Ansicht: Scannen ---------- */

CF.views = CF.views || {};
CF.views.scan = {
    title: 'Scannen', icon: 'scan',
    mount(root, params) {
        const cam = new Camera();
        // „Gleich suchen“: aus dem Suchfeld (go=search) immer, sonst nach Wunsch
        const fromSearch = params && params.go === 'search';
        let auto = fromSearch || CF.ls.get('autoSearch', false);
        let loop = null, mode = fromSearch ? 'single' : ((params && params.mode) || CF.ls.get('scanMode', 'single'));
        const session = []; // Serie und Inventur: {text, format, qty, ts, uid, sent}
        const lastSeen = new Map();
        let lastHitTime = 0;

        const video = el('video', { playsinline: true, muted: true, autoplay: true });
        const ov = el('canvas', { class: 'ov' });
        const still = el('img', { class: 'still', alt: '', hidden: true });
        const status = el('span', { class: 'pill', hidden: true });
        const camSel = el('select', { class: 'in', style: { maxWidth: '12rem', padding: '.45rem 2rem .45rem .7rem', fontSize: '.8rem', background: 'rgba(10,14,30,.62)', color: '#e2e8f0', borderColor: 'rgba(255,255,255,.14)' }, hidden: true });
        const torchBtn = ibtn('torch', T('Taschenlampe'), async () => { const on = !cam.torchOn; if (await cam.torch(on)) torchBtn.classList.toggle('on', on); }, '');
        torchBtn.hidden = true;
        const flipBtn = ibtn('flip', T('Kamera wechseln'), () => switchCam(), '');
        flipBtn.hidden = true;
        const zoom = el('input', { type: 'range', min: 1, max: 1, step: 0.1, value: 1, hidden: true, oninput: e => cam.zoom(+e.target.value) });
        const stopBtn = ibtn('stop', T('Kamera aus'), () => stopCam(), '');
        const bar = el('div', { class: 'bar', hidden: true }, stopBtn, flipBtn, torchBtn, zoom);
        const top = el('div', { class: 'topbar' }, status, el('span', { class: 'grow' }), camSel);
        const idle = el('div', { class: 'idle' });
        const frameEl = el('div', { class: 'frame', hidden: true }, el('span'));
        const laser = el('div', { class: 'laser', hidden: true });
        const viewer = el('div', { class: 'viewer' }, video, still, ov, frameEl, laser, idle, top, bar);

        const resultBox = el('div', { class: 'stack' });
        const listBox = el('div', { class: 'scanlist' });
        const listHead = el('div', { class: 'row between' });
        const rightCard = el('div', { class: 'card' });

        const modeSeg = CF.seg([['single', T('Einzeln'), 'scan'], ['serie', T('Serie'), 'list'], ['count', T('Inventur'), 'hash']], mode, v => { mode = v; CF.ls.set('scanMode', v); renderRight(); });
        const autoSw = CF.toggle(T('Gleich suchen'), auto, v => { auto = v; if (!fromSearch) CF.ls.set('autoSearch', v); }, T('Barcodes, ISBN und Text sofort in der Suche öffnen'));

        function setIdle(text, withButtons) {
            fill(idle, el('div', { class: 'big', icon: 'camera' }),
                el('div', { style: { fontWeight: 750, fontSize: '1.1rem' }, text: T('Bereit zum Scannen') }),
                el('p', { text }),
                withButtons !== false ? el('div', { class: 'row', style: { justifyContent: 'center' } },
                    Camera.supported() ? btn(T('Kamera starten'), 'btn-primary btn-lg', () => startCam(), 'camera') : null,
                    btn(T('Bild wählen'), 'btn-second btn-lg', () => pickImage(), 'image')) : null);
            idle.hidden = false;
        }

        async function startCam(deviceId) {
            still.hidden = true;
            setStatus(T('Kamera startet …'), true);
            try {
                CF.Engine.ready().catch(() => {});
                await cam.start(video, deviceId);
            } catch (e) {
                const name = e && e.name;
                setIdle(name === 'NotAllowedError' ? T('Zugriff auf die Kamera wurde verweigert. Erlaube ihn in den Browser-Einstellungen oder wähle ein Bild.')
                    : name === 'NotFoundError' ? T('Keine Kamera gefunden. Du kannst trotzdem Bilder und Screenshots scannen.')
                    : T('Die Kamera ließ sich nicht starten.') + ' (' + (name || e) + ')');
                setStatus('', false);
                return;
            }
            idle.hidden = true;
            bar.hidden = false; frameEl.hidden = false; laser.hidden = false;
            torchBtn.hidden = !cam.hasTorch;
            const z = cam.zoomRange;
            if (z && z.max > z.min) { zoom.min = z.min; zoom.max = Math.min(z.max, 8); zoom.step = z.step || 0.1; zoom.value = z.min; zoom.hidden = false; } else zoom.hidden = true;
            const devs = await Camera.devices();
            flipBtn.hidden = devs.length < 2;
            camSel.hidden = devs.length < 3;
            fill(camSel, devs.map((d, i) => el('option', { value: d.deviceId, text: d.label || T('Kamera %s', i + 1) })));
            camSel.value = cam.deviceId || '';
            camSel.onchange = () => startCam(camSel.value);
            CF.ls.set('camOn', true);
            if (loop) loop.stop();
            loop = scanLoop(video, onFrame, { symbol: mode === 'single' });
            setStatus(CF.Engine.state === 'ready' ? '' : T('Scan-Engine lädt …'), CF.Engine.state !== 'ready');
            CF.Engine.ready().then(() => setStatus('', false), () => setStatus(T('Scan-Engine nicht verfügbar'), true));
        }
        async function switchCam() {
            const devs = await Camera.devices();
            if (devs.length < 2) return;
            const i = devs.findIndex(d => d.deviceId === cam.deviceId);
            startCam(devs[(i + 1) % devs.length].deviceId);
        }
        function stopCam() {
            if (loop) loop.stop();
            loop = null;
            cam.stop();
            bar.hidden = true; frameEl.hidden = true; laser.hidden = true;
            ov.getContext('2d').clearRect(0, 0, ov.width, ov.height);
            CF.ls.set('camOn', false);
            setIdle(T('Halte einen QR- oder Barcode vor die Kamera. Oder wähle ein Bild, zieh es hierher oder füge einen Screenshot mit Strg+V ein.'));
        }
        function setStatus(text, show) {
            fill(status, show && text ? el('span', { class: 'spinner', style: { width: '.8rem', height: '.8rem', borderWidth: '2px' } }) : null, text || '');
            status.hidden = !show || !text;
        }

        let clearT = null;
        function onFrame(res, w, h) {
            if (res.length) {
                drawHits(ov, res, w, h);
                clearTimeout(clearT);
                clearT = setTimeout(() => { ov.getContext('2d').clearRect(0, 0, ov.width, ov.height); }, 700);
                handle(res, 'scan');
            }
        }

        function handle(res, src) {
            const now = Date.now();
            if (mode === 'single') {
                const r = res[0];
                if (loop) loop.pause(true);
                flash();
                CF.feedback();
                showSingle(res, src);
                return;
            }
            for (const r of res) {
                const key = r.format + '|' + r.text;
                const seen = lastSeen.get(key) || 0;
                lastSeen.set(key, now);
                if (now - seen < (mode === 'count' ? 1400 : 2500)) continue;
                if (mode === 'serie' && session.some(s => s.text === r.text && s.format === r.format) && now - seen < 15000) continue;
                if (now - lastHitTime < 350) continue;
                lastHitTime = now;
                addToSession(r, src);
            }
        }

        function addToSession(r, src) {
            let entry = mode === 'count' ? session.find(s => s.text === r.text && s.format === r.format) : null;
            if (entry) { entry.qty++; entry.ts = Date.now(); CF.beep(2093, 60); }
            else {
                entry = { text: r.text, format: r.format, qty: 1, ts: Date.now(), uid: CF.uid(), sent: null };
                session.unshift(entry);
                CF.feedback();
            }
            flash();
            CF.Store.addHistory(r.text, r.format, src, r);
            renderList();
        }

        function flash() { viewer.classList.remove('flash'); void viewer.offsetWidth; viewer.classList.add('flash'); }

        function showSingle(res, src) {
            const first = res[0];
            const hid = CF.Store.addHistory(first.text, first.format, src, first);
            if (auto && res.length === 1) {
                const q = CF.searchQuery(first.text);
                if (q) {
                    stopCam();
                    CF.toast(T('Suche nach „%s“ …', q.length > 40 ? q.slice(0, 40) + '…' : q), 'info');
                    CF.searchFor(q);
                    return;
                }
            }
            fill(resultBox,
                res.length > 1 ? CF.msg('info', T('%s Codes gefunden.', res.length)) : null,
                res.map((r, i) => el('div', { class: i ? 'card flat tight' : '' }, CF.resultCard(r.text, r, { historyId: i === 0 ? hid : null }))),
                cam.stream ? btn(T('Weiter scannen'), 'btn-primary full', () => resume(), 'scan') : null);
            renderRight();
            if (window.innerWidth < 1100) setTimeout(() => rightCard.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60);
        }
        function resume() {
            fill(resultBox);
            renderRight();
            if (loop) { loop.pause(false); return; }
            if (!cam.stream) startCam();
        }
        viewer.addEventListener('click', e => { if (loop && loop.paused && !e.target.closest('.bar') && !e.target.closest('select')) resume(); });

        function renderList() {
            const total = session.reduce((a, s) => a + s.qty, 0);
            fill(listHead, el('div', {}, el('b', { text: mode === 'count' ? T('%s Artikel, %s Stück', session.length, total) : T('%s Scans', session.length) })),
                el('div', { class: 'row' },
                    btn('CSV', 'btn-second btn-sm', () => exportCsv(), 'download', { disabled: !session.length }),
                    ibtn('copy', T('Alle kopieren'), () => CF.copy(session.slice().reverse().map(s => mode === 'count' ? s.text + '\t' + s.qty : s.text).join('\n')), 'sm'),
                    ibtn('trash', T('Liste leeren'), async () => { if (session.length && await CF.confirm(T('Liste leeren?'), T('Leeren'), true)) { session.length = 0; lastSeen.clear(); renderList(); } }, 'sm')));
            fill(listBox, session.length ? session.map(s => el('div', { class: 'it' },
                mode === 'count' ? el('span', { class: 'q', text: '×' + s.qty }) : el('span', { class: 'result-ico', style: { width: '2rem', height: '2rem', borderRadius: '.6rem' }, icon: CF.typeIcon(CF.Parse.content(s.text, s, CF.lang).type) }),
                el('div', { class: 'grow' }, el('div', { class: 'break', style: { fontWeight: 650 }, text: s.text.length > 160 ? s.text.slice(0, 160) + '…' : s.text }),
                    el('div', { class: 'tiny faint', text: CF.formatName(s.format) + ' · ' + CF.fmtTime(s.ts) })),
                s.sent ? el('span', { class: 'badge ' + (s.sent === 'ok' ? 'ok' : 'warn'), title: s.sent === 'ok' ? T('Am Empfänger angekommen') : T('Wird gesendet'), text: s.sent === 'ok' ? '✓' : '…' }) : null,
                mode === 'count' ? el('div', { class: 'row nw' }, ibtn('plus', '+1', () => { s.qty++; renderList(); }, 'sm')) : null,
                CF.searchQuery(s.text) ? ibtn('search', T('Suchen'), () => CF.searchFor(CF.searchQuery(s.text)), 'sm') : null,
                ibtn('copy', T('Kopieren'), () => CF.copy(s.text), 'sm')))
                : CF.empty('list', mode === 'count' ? T('Scanne Artikel. Gleiche Codes werden gezählt.') : T('Scanne nacheinander mehrere Codes. Jeder landet hier in der Liste.')));
        }
        function exportCsv() {
            const rows = [['nr', 'zeit', 'format', 'menge', 'inhalt']].concat(session.slice().reverse().map((s, i) => [i + 1, new Date(s.ts).toISOString(), CF.formatName(s.format), s.qty, s.text]));
            CF.download('scans-' + CF.fileStamp() + '.csv', new Blob([CF.csv(rows)], { type: 'text/csv;charset=utf-8' }));
        }

        function renderRight() {
            if (mode === 'single') {
                fill(rightCard, resultBox.children.length ? resultBox : el('div', {},
                    el('h2', { html: CF.icon('sparkles') + '<span>' + CF.T('Ergebnis') + '</span>' }),
                    el('p', { class: 'muted small', text: T('Hier erscheint, was im Code steckt: Links werden auf Betrugsmaschen geprüft, WLAN, Kontakte, Termine, Überweisungen, Bordkarten und Produktdaten werden verständlich aufbereitet.') }),
                    el('div', { class: 'chips' }, ['QR', 'Micro QR', 'rMQR', 'Data Matrix', 'Aztec', 'PDF417', 'EAN/UPC', 'Code 128', 'Code 39/93', 'ITF', 'Codabar', 'DataBar', 'MaxiCode'].map(n => el('span', { class: 'badge', text: n })))));
            } else {
                renderList();
                fill(rightCard, el('div', { class: 'stack' }, listHead, listBox));
            }
            if (loop) {
                loop.stop();
                loop = scanLoop(video, onFrame, { symbol: mode === 'single' });
            }
        }

        async function scanBlob(blob, src) {
            if (!blob) return;
            setStatus(T('Bild wird gelesen …'), true);
            let r;
            try { r = await CF.Engine.readBlob(blob); } catch (e) { setStatus('', false); CF.toast(T('Das Bild ließ sich nicht lesen.'), 'bad'); return; }
            setStatus('', false);
            if (loop) loop.pause(true);
            const url = URL.createObjectURL(blob);
            still.src = url;
            still.onload = () => URL.revokeObjectURL(url);
            still.hidden = false;
            idle.hidden = true;
            frameEl.hidden = true; laser.hidden = true;
            drawHits(ov, r.results, r.canvas.width, r.canvas.height);
            ov.style.objectFit = 'contain';
            if (!r.results.length) {
                CF.toast(T('Kein Code im Bild gefunden.'), 'warn');
                fill(resultBox, CF.msg('warn', T('In diesem Bild wurde kein Code gefunden. Tipp: Code möglichst groß, scharf und gerade aufnehmen.')),
                    btn(T('Anderes Bild'), 'btn-second', () => pickImage(), 'image'));
                const prev = mode; mode = 'single'; renderRight(); mode = prev;
                return;
            }
            if (mode === 'single') { CF.feedback(); showSingle(r.results, src); }
            else r.results.forEach(x => addToSession(x, src));
        }
        async function pickImage() {
            const files = await CF.pickFile('image/*', true);
            if (!files) return;
            for (const f of files) await scanBlob(f, 'image');
        }
        CF.scanSharedBlob = blob => scanBlob(blob, 'image');

        const onPaste = e => {
            const f = [...((e.clipboardData && e.clipboardData.files) || [])].find(x => /^image\//.test(x.type));
            if (f) { e.preventDefault(); scanBlob(f, 'image'); }
        };
        document.addEventListener('paste', onPaste);
        const touch = matchMedia('(pointer: coarse)').matches;
        const drop = el('div', { class: 'drop', onclick: () => pickImage() }, el('span', { html: CF.icon('upload'), style: { display: 'inline-block', verticalAlign: 'middle', marginRight: '.4rem' } }),
            touch ? T('Foto oder Screenshot aus der Galerie scannen') : T('Bild hierher ziehen, klicken oder Screenshot einfügen (Strg+V)'));
        [viewer, drop].forEach(z => {
            z.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
            z.addEventListener('dragleave', () => drop.classList.remove('over'));
            z.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); const f = [...e.dataTransfer.files].find(x => /^image\//.test(x.type)); if (f) scanBlob(f, 'image'); });
        });

        const notSecure = window.isSecureContext === false ? CF.msg('warn', T('Die Kamera funktioniert nur über HTTPS. Bilder und Screenshots lassen sich trotzdem scannen.')) : null;

        fill(root,
            el('div', { class: 'row between cf-bar' }, modeSeg, autoSw),
            fromSearch ? CF.msg('info', T('Scanne einen Barcode, eine ISBN oder einen Text: die Suche startet sofort.'), 'search') : null,
            notSecure,
            el('div', { class: 'layout' },
                el('div', { class: 'stack' }, viewer, drop),
                el('div', { class: 'sticky' }, rightCard)));
        setIdle(T('Halte einen QR- oder Barcode vor die Kamera. Oder wähle ein Bild, zieh es hierher oder füge einen Screenshot mit Strg+V ein.'));
        renderRight();
        CF.Engine.ready().catch(() => {});
        if ((fromSearch || CF.ls.get('camOn', false)) && Camera.supported()) startCam();
        if (params && params.blob) scanBlob(params.blob, 'image');

        return () => {
            document.removeEventListener('paste', onPaste);
            if (loop) loop.stop();
            cam.stop();
        };
    },
};
})();
