/* Suchmaschine – Zeit-Werkzeuge im Browser: mehrere Timer, Pomodoro, Intervall-Training, Wecker, Stoppuhr mit Runden,
   Countdown (mit Titel, Vollbild und Link) und Weltzeituhr mit Termin-Planer. Timer überleben das Neuladen der Seite. */
(function () {
'use strict';
const CF = window.CF;
const { el, fill, btn, ibtn, T, field, input, select } = CF;

const SOUNDS = [['glocke', T('Glocke')], ['gong', T('Gong')], ['dingdong', T('Ding-Dong')], ['sanft', T('Sanft')], ['wecker', T('Wecker')], ['vogel', T('Vogel')]];
const soundUrl = k => (CF.boot.base || './') + '?a=asset&f=sounds/' + k + '.mp3&v=' + encodeURIComponent(CF.boot.v || '');
const pad2 = n => String(n).padStart(2, '0');
function hms(sec, tenths) {
    const neg = sec < 0;
    sec = Math.abs(sec);
    const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = Math.floor(sec % 60);
    const t = (h ? h + ':' + pad2(m) : pad2(m)) + ':' + pad2(s) + (tenths ? ',' + Math.floor(sec * 10 % 10) : '');
    return (neg ? '−' : '') + t;
}
/** „5“, „1:30“, „90s“, „1,5h“, „1h20m“ → Sekunden */
function parseDur(s) {
    s = String(s).trim().toLowerCase().replace(',', '.');
    if (/^\d+(\.\d+)?$/.test(s)) return Math.round(parseFloat(s) * 60);
    let m = s.match(/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/);
    if (m) return m[3] !== undefined ? +m[1] * 3600 + +m[2] * 60 + +m[3] : +m[1] * 60 + +m[2];
    let t = 0, ok = false;
    s.replace(/(\d+(?:\.\d+)?)\s*(h|std|stunden?|m|min|minuten?|s|sek|sekunden?)/g, (x, n, u) => { ok = true; t += parseFloat(n) * (/^h|^std/.test(u) ? 3600 : /^m/.test(u) ? 60 : 1); });
    return ok ? Math.round(t) : NaN;
}

/* ---------- Ton, Vibration, Benachrichtigung, Bildschirm an ---------- */
let audio = null, ringing = 0;
function ring(title) {
    const snd = CF.ls.get('timer.sound', 'glocke');
    if (snd) {
        try {
            if (!audio) audio = new Audio();
            audio.src = soundUrl(snd);
            audio.loop = true;
            audio.play().catch(() => {});
            clearTimeout(ringing);
            ringing = setTimeout(stopRing, 60000);
        } catch (e) { /* kein Ton */ }
    }
    if (navigator.vibrate) navigator.vibrate([400, 200, 400, 200, 400]);
    if (window.Notification && Notification.permission === 'granted' && document.hidden) {
        try { new Notification(title, { body: T('Zeit ist um!'), tag: 'sm-timer' }); } catch (e) { /* egal */ }
    }
    CF.toast(title + ' · ' + T('Zeit ist um!'), 'info', 6000);
}
function stopRing() { clearTimeout(ringing); if (audio) { audio.pause(); audio.currentTime = 0; } }
function shortBeep(f) { CF.beep(f || 1320, 180); if (navigator.vibrate) navigator.vibrate(120); }
let wake = null;
async function keepAwake(on) {
    try {
        if (on && !wake && navigator.wakeLock) { wake = await navigator.wakeLock.request('screen'); wake.addEventListener('release', () => { wake = null; }); }
        if (!on && wake) { await wake.release(); wake = null; }
    } catch (e) { /* nicht erlaubt */ }
}

/* ---------- Timer (mehrere), Pomodoro, Intervall, Wecker ---------- */
CF.views.timer = {
    mount(root, params) {
        let timers = CF.ls.get('timers', []);
        const save = () => CF.ls.set('timers', timers);
        const list = el('div', { class: 'stack' });
        const title0 = document.title;
        const now = () => Date.now();
        const leftOf = t => t.paused ? t.left : (t.end - now()) / 1000;
        function add(t) { t.id = CF.uid(); timers.unshift(t); save(); render(); keepAwake(CF.ls.get('timer.awake', false)); }
        function addTimer(sec, name) { if (!(sec > 0)) { CF.toast(T('Bitte eine Dauer wie 5, 1:30 oder 90s eingeben.'), 'warn'); return; } add({ kind: 'timer', name: name || T('Timer %s', hms(sec)), dur: sec, end: now() + sec * 1000, paused: false, left: sec }); }
        function addSeq(name, steps, rounds) { add({ kind: 'seq', name, steps, idx: 0, rounds, dur: steps[0].sec, end: now() + steps[0].sec * 1000, paused: false, left: steps[0].sec }); }
        function addAlarm(hm, name) {
            const [h, m] = hm.split(':').map(Number);
            const d = new Date(); d.setHours(h, m, 0, 0);
            if (d <= new Date()) d.setDate(d.getDate() + 1);
            add({ kind: 'alarm', name: name || T('Wecker %s', hm), dur: (d - now()) / 1000, end: d.getTime(), paused: false, left: 0 });
        }
        if (params.min) addTimer(Math.round(parseFloat(String(params.min).replace(',', '.')) * 60));

        const custom = input({ class: 'in big mono', placeholder: T('5 · 1:30 · 90s · 1h20m'), autocomplete: 'off' });
        const name = input({ placeholder: T('Name (optional), z. B. Nudeln'), maxlength: 40 });
        custom.addEventListener('keydown', e => { if (e.key === 'Enter') { addTimer(parseDur(custom.value), name.value); custom.value = ''; name.value = ''; } });
        const quick = el('div', { class: 'chips' }, [1, 3, 5, 10, 15, 20, 30, 45, 60].map(m => el('button', { type: 'button', class: 'chip', text: m + ' min', onclick: () => addTimer(m * 60, name.value) })));

        // Pomodoro und Intervall
        const pomo = () => { const s = []; for (let i = 1; i <= 4; i++) { s.push({ label: T('Fokus %s/4', i), sec: CF.ls.get('pomo.work', 25) * 60 }); s.push({ label: i < 4 ? T('kurze Pause') : T('lange Pause'), sec: (i < 4 ? CF.ls.get('pomo.short', 5) : CF.ls.get('pomo.long', 15)) * 60 }); } addSeq('Pomodoro', s, 1); };
        const work = input({ type: 'number', min: 5, max: 3600, value: CF.ls.get('hiit.work', 40), style: { width: '5.5rem' } });
        const rest = input({ type: 'number', min: 0, max: 3600, value: CF.ls.get('hiit.rest', 20), style: { width: '5.5rem' } });
        const rounds = input({ type: 'number', min: 1, max: 99, value: CF.ls.get('hiit.rounds', 8), style: { width: '5rem' } });
        const hiit = () => {
            CF.ls.set('hiit.work', +work.value); CF.ls.set('hiit.rest', +rest.value); CF.ls.set('hiit.rounds', +rounds.value);
            const s = [{ label: T('Bereit machen'), sec: 10 }];
            for (let i = 1; i <= +rounds.value; i++) { s.push({ label: T('Belastung %s/%s', i, rounds.value), sec: +work.value }); if (+rest.value && i < +rounds.value) s.push({ label: T('Pause'), sec: +rest.value }); }
            addSeq(T('Intervall'), s, 1);
        };
        const alarmIn = input({ type: 'time', value: CF.ls.get('alarm.t', '07:00') });

        function render() {
            fill(list, timers.length ? timers.map(t => {
                const row = el('div', { class: 'tm' + (t.done ? ' is-done' : '') + (t.paused ? ' is-paused' : ''), 'data-id': t.id });
                const step = t.kind === 'seq' ? t.steps[t.idx] : null;
                fill(row, el('div', { class: 'tm__head' }, el('b', { text: t.name }), step ? CF.badge(step.label, 'acc') : null, t.kind === 'alarm' ? CF.badge(new Date(t.end).toLocaleTimeString(CF.locale(), { hour: '2-digit', minute: '2-digit' })) : null),
                    el('div', { class: 'tm__time mono', 'data-time': '' }), el('div', { class: 'meter' }, el('span', { 'data-bar': '' })),
                    el('div', { class: 'row' },
                        t.done ? btn(T('Ton aus'), 'btn-danger btn-sm', () => { stopRing(); t.done = false; t.ack = true; save(); render(); }, 'volume') : null,
                        t.kind !== 'alarm' && !t.done ? btn(t.paused ? T('Weiter') : T('Pause'), 'btn-second btn-sm', () => { if (t.paused) { t.end = now() + t.left * 1000; t.paused = false; } else { t.left = leftOf(t); t.paused = true; } save(); render(); }, t.paused ? 'play' : 'stop') : null,
                        t.kind === 'timer' ? btn('+1 min', 'btn-ghost btn-sm', () => { if (t.paused) t.left += 60; else t.end += 60000; t.dur += 60; t.done = false; t.ack = false; stopRing(); save(); render(); }, 'plus') : null,
                        t.kind !== 'alarm' ? ibtn('refresh', T('Neu starten'), () => { if (t.kind === 'seq') t.idx = 0; const d = t.kind === 'seq' ? t.steps[0].sec : t.dur; t.end = now() + d * 1000; t.left = d; t.paused = false; t.done = false; t.ack = false; stopRing(); save(); render(); }, 'sm') : null,
                        ibtn('trash', T('Entfernen'), () => { timers = timers.filter(x => x !== t); stopRing(); save(); render(); }, 'sm')));
                return row;
            }) : CF.empty('history', T('Kein Timer läuft. Wähle oben eine Zeit.')));
            tick();
        }
        function tick() {
            let soonest = null;
            timers.forEach(t => {
                let left = leftOf(t);
                if (!t.paused && left <= 0 && !t.done && !t.ack) {
                    if (t.kind === 'seq' && t.idx < t.steps.length - 1) {
                        t.idx++;
                        t.end = now() + t.steps[t.idx].sec * 1000;
                        t.dur = t.steps[t.idx].sec;
                        shortBeep(/Pause/.test(t.steps[t.idx].label) ? 880 : 1320);
                        save(); render(); return;
                    }
                    t.done = true;
                    save();
                    ring(t.name);
                    render();
                    return;
                }
                if (t.kind === 'seq' && !t.paused && left > 0 && left <= 3.05 && Math.abs(left - Math.round(left)) < .13 && t.lastBeep !== Math.round(left)) { t.lastBeep = Math.round(left); CF.beep(880, 80); }
                const row = list.querySelector('[data-id="' + t.id + '"]');
                if (!row) return;
                const dur = t.kind === 'seq' ? t.steps[t.idx].sec : t.dur;
                row.querySelector('[data-time]').textContent = t.done || t.ack ? '00:00' : hms(Math.max(0, Math.ceil(left)));
                row.querySelector('[data-bar]').style.width = Math.max(0, Math.min(100, 100 - left / dur * 100)) + '%';
                if (!t.paused && !t.done && !t.ack && (soonest === null || left < soonest[0])) soonest = [left, t.name];
            });
            document.title = soonest ? hms(Math.max(0, Math.ceil(soonest[0]))) + ' · ' + soonest[1] : title0;
        }
        const iv = setInterval(tick, 200);

        const soundSel = select(SOUNDS.concat([['', T('kein Ton')]]), CF.ls.get('timer.sound', 'glocke'), v => CF.ls.set('timer.sound', v), { style: { width: 'auto' } });
        const notifyBtn = window.Notification && Notification.permission === 'default' ? btn(T('Benachrichtigungen erlauben'), 'btn-ghost btn-sm', () => Notification.requestPermission().then(() => notifyBtn.remove()), 'info') : null;
        fill(root, el('div', { class: 'layout' },
            el('div', { class: 'stack lg' },
                CF.card(T('Neuer Timer'), 'play', el('div', { class: 'stack' }, quick, el('div', { class: 'row nw' }, el('div', { class: 'grow' }, custom), btn(T('Start'), 'btn-primary btn-lg', () => { addTimer(parseDur(custom.value), name.value); custom.value = ''; name.value = ''; }, 'play')), name)),
                CF.card(T('Laufende Timer'), 'history', list)),
            el('div', { class: 'stack lg sticky' },
                CF.card('Pomodoro', 'sparkles', el('div', { class: 'stack' }, el('p', { class: 'small muted', text: T('4 × 25 Minuten konzentriert arbeiten, dazwischen 5 Minuten Pause, am Ende 15.') }), btn(T('Pomodoro starten'), 'btn-second', pomo, 'play'))),
                CF.card(T('Intervall-Training'), 'refresh', el('div', { class: 'stack' }, el('div', { class: 'row' }, field(T('Belastung (s)'), work), field(T('Pause (s)'), rest), field(T('Runden'), rounds)), btn(T('Intervall starten'), 'btn-second', hiit, 'play'))),
                CF.card(T('Wecker'), 'calendar', el('div', { class: 'row' }, alarmIn, btn(T('Wecker stellen'), 'btn-second', () => { CF.ls.set('alarm.t', alarmIn.value); addAlarm(alarmIn.value); }, 'check'))),
                CF.card(T('Ton und Anzeige'), 'volume', el('div', { class: 'stack' }, el('div', { class: 'row' }, soundSel, btn(T('Ton testen'), 'btn-ghost btn-sm', () => { ring(T('Test')); setTimeout(stopRing, 2500); }, 'play')),
                    CF.toggle(T('Bildschirm anlassen'), CF.ls.get('timer.awake', false), v => { CF.ls.set('timer.awake', v); keepAwake(v); }), notifyBtn,
                    el('p', { class: 'tiny faint', text: T('Timer laufen weiter, auch wenn du die Seite neu lädst. Der Ton klingt nur, solange der Tab offen ist.') }))))));
        render();
        if (CF.ls.get('timer.awake', false) && timers.length) keepAwake(true);
        return () => { clearInterval(iv); document.title = title0; keepAwake(false); };
    },
};

/* ---------- Stoppuhr (eigene Ansicht innerhalb des Timers? Nein: eigener Bereich auf derselben Seite) ---------- */
CF.views.stoppuhr = {
    mount(root) {
        let st = CF.ls.get('sw', { start: 0, acc: 0, run: false, laps: [] });
        const save = () => CF.ls.set('sw', st);
        const show = el('div', { class: 'sw-time mono' });
        const laps = el('div', { class: 'stack' });
        const elapsed = () => (st.acc + (st.run ? Date.now() - st.start : 0)) / 1000;
        const go = btn('', 'btn-primary btn-lg', () => { if (st.run) { st.acc += Date.now() - st.start; st.run = false; } else { st.start = Date.now(); st.run = true; } save(); paint(); }, 'play');
        function paint() {
            go.lastChild.textContent = st.run ? T('Pause') : (st.acc ? T('Weiter') : T('Start'));
            const best = st.laps.length > 1 ? Math.min(...st.laps.map(l => l.d)) : null, worst = st.laps.length > 1 ? Math.max(...st.laps.map(l => l.d)) : null;
            fill(laps, st.laps.slice().reverse().map((l, i) => el('div', { class: 'row between mono small sw-lap' + (l.d === best ? ' best' : l.d === worst ? ' worst' : '') },
                el('span', { text: T('Runde %s', st.laps.length - i) }), el('span', { text: hms(l.d, true) }), el('span', { class: 'faint', text: hms(l.t, true) }))));
        }
        const iv = setInterval(() => { show.textContent = hms(elapsed(), true); }, 50);
        fill(root, el('div', { class: 'stack lg', style: { maxWidth: '40rem' } }, show,
            el('div', { class: 'row' }, go, btn(T('Runde'), 'btn-second btn-lg', () => { if (!st.run) return; const t = elapsed(); const last = st.laps.length ? st.laps[st.laps.length - 1].t : 0; st.laps.push({ t, d: t - last }); save(); paint(); }, 'list'),
                btn(T('Zurücksetzen'), 'btn-ghost', () => { st = { start: 0, acc: 0, run: false, laps: [] }; save(); paint(); }, 'refresh'),
                btn(T('Runden kopieren'), 'btn-ghost', () => CF.copy(st.laps.map((l, i) => (i + 1) + '\t' + hms(l.d, true) + '\t' + hms(l.t, true)).join('\n')), 'copy')),
            laps));
        paint();
        return () => clearInterval(iv);
    },
};
// Die Timer-Seite zeigt Timer und Stoppuhr als Reiter
const timerMount = CF.views.timer.mount;
CF.views.timer.mount = function (root, params) {
    const a = el('div'), b = el('div');
    let tab = params.watch === '1' ? 'sw' : CF.ls.get('timer.tab', 'timer');
    let clean1 = null, clean2 = null;
    const seg = CF.seg([['timer', T('Timer'), 'history'], ['sw', T('Stoppuhr'), 'play']], tab, v => { tab = v; CF.ls.set('timer.tab', v); show(); });
    fill(root, el('div', { class: 'row cf-bar' }, seg), a, b);
    function show() {
        a.hidden = tab !== 'timer'; b.hidden = tab !== 'sw';
        if (tab === 'timer' && !clean1) clean1 = timerMount(a, params) || true;
        if (tab === 'sw' && !clean2) clean2 = CF.views.stoppuhr.mount(b, params) || true;
    }
    show();
    return () => { [clean1, clean2].forEach(c => { if (typeof c === 'function') c(); }); };
};

/* ---------- Countdown ---------- */
function easter(y) { const a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30, i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451); return new Date(y, Math.floor((h + l - 7 * m + 114) / 31) - 1, (h + l - 7 * m + 114) % 31 + 1); }
const NAMED = {
    weihnachten: [T('Heiligabend'), y => new Date(y, 11, 24)], silvester: [T('Silvester'), y => new Date(y, 11, 31, 23, 59, 59)], neujahr: [T('Neujahr'), y => new Date(y + 1, 0, 1)],
    ostern: [T('Ostern'), y => easter(y)], nikolaus: [T('Nikolaus'), y => new Date(y, 11, 6)], valentinstag: [T('Valentinstag'), y => new Date(y, 1, 14)], halloween: ['Halloween', y => new Date(y, 9, 31)],
    sommer: [T('Sommeranfang'), y => new Date(y, 5, 21)], winter: [T('Winteranfang'), y => new Date(y, 11, 21)],
};
CF.views.countdown = {
    mount(root, params) {
        let saved = CF.ls.get('countdowns', []);
        const title = input({ placeholder: T('z. B. Urlaub, Hochzeit, Prüfung'), maxlength: 60, value: params.title || '' });
        const date = input({ type: 'date', value: params.d || '' });
        const time = input({ type: 'time', value: params.t || '' });
        const big = el('div', { class: 'cd' });
        const listBox = el('div', { class: 'hlist' });
        let target = null, label = '';
        function setTarget(d, l) { target = d; label = l; paint(); }
        function fromInputs() {
            if (!date.value) return;
            const d = new Date(date.value + 'T' + (time.value || '00:00'));
            setTarget(d, title.value || d.toLocaleDateString(CF.locale(), { dateStyle: 'long' }));
        }
        function paint() {
            if (!target) { fill(big, el('p', { class: 'muted', text: T('Wähle ein Datum oder einen Anlass.') })); return; }
            let s = Math.max(0, (target - Date.now()) / 1000);
            const d = Math.floor(s / 86400); s -= d * 86400;
            const h = Math.floor(s / 3600); s -= h * 3600;
            const m = Math.floor(s / 60); s = Math.floor(s - m * 60);
            fill(big, el('div', { class: 'cd__label', text: label }),
                target <= Date.now() ? el('div', { class: 'cd__done', text: T('Geschafft! 🎉') }) :
                el('div', { class: 'cd__grid' }, [[d, T('Tage')], [h, T('Stunden')], [m, T('Minuten')], [s, T('Sekunden')]].map(([v, n]) => el('div', { class: 'cd__cell' }, el('b', { class: 'mono', text: n === T('Tage') ? CF.fmtNum(v) : pad2(v) }), el('span', { text: n })))),
                el('div', { class: 'small muted', text: target.toLocaleString(CF.locale(), { dateStyle: 'full', timeStyle: 'short' }) + ' · ' + T('%s Wochen', CF.fmtNum(Math.floor((target - Date.now()) / 604800000))) }));
        }
        function link() {
            const u = new URL(CF.toolUrl('countdown'), location.href);
            if (target) { u.searchParams.set('d', target.getFullYear() + '-' + pad2(target.getMonth() + 1) + '-' + pad2(target.getDate())); if (target.getHours() || target.getMinutes()) u.searchParams.set('t', pad2(target.getHours()) + ':' + pad2(target.getMinutes())); }
            if (label) u.searchParams.set('title', label);
            return u.href;
        }
        function renderList() {
            fill(listBox, saved.length ? saved.map((c, i) => el('div', { class: 'hrow', onclick: () => { setTarget(new Date(c.ts), c.label); } }, el('div', { class: 'grow' }, el('div', { class: 't', text: c.label }), el('div', { class: 'm', text: new Date(c.ts).toLocaleString(CF.locale(), { dateStyle: 'medium', timeStyle: 'short' }) + ' · ' + T('noch %s Tage', Math.max(0, Math.ceil((c.ts - Date.now()) / 86400000))) })),
                ibtn('trash', T('Entfernen'), e => { e.stopPropagation(); saved.splice(i, 1); CF.ls.set('countdowns', saved); renderList(); }, 'sm'))) : el('p', { class: 'small muted', text: T('Gemerkte Countdowns erscheinen hier.') }));
        }
        [title, date, time].forEach(x => x.addEventListener('input', fromInputs));
        const iv = setInterval(paint, 500);
        const named = el('div', { class: 'chips' }, Object.entries(NAMED).map(([k, [n, fn]]) => el('button', { type: 'button', class: 'chip', text: n, onclick: () => {
            const y = new Date().getFullYear();
            let d = fn(y);
            if (d < new Date()) d = fn(y + 1);
            title.value = n; date.value = d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); time.value = pad2(d.getHours()) + ':' + pad2(d.getMinutes());
            setTarget(d, n);
        } })));
        fill(root, el('div', { class: 'layout' },
            el('div', { class: 'stack lg' }, big,
                el('div', { class: 'row' }, btn(T('Vollbild'), 'btn-second btn-sm', () => { const fs = big.requestFullscreen || big.webkitRequestFullscreen; if (fs) fs.call(big); }, 'zoom'),
                    btn(T('Link kopieren'), 'btn-second btn-sm', () => CF.copy(link()), 'link'),
                    btn(T('Merken'), 'btn-ghost btn-sm', () => { if (!target) return; saved = [{ ts: target.getTime(), label }].concat(saved.filter(c => c.label !== label)).slice(0, 20); CF.ls.set('countdowns', saved); renderList(); }, 'star'))),
            el('div', { class: 'stack lg sticky' },
                CF.card(T('Countdown bis'), 'calendar', el('div', { class: 'stack' }, field(T('Titel'), title), el('div', { class: 'grid2' }, field(T('Datum'), date), field(T('Uhrzeit'), time)), named)),
                CF.card(T('Gemerkt'), 'star', listBox))));
        if (params.named && NAMED[params.named]) named.querySelectorAll('button')[Object.keys(NAMED).indexOf(params.named)].click();
        else if (date.value) fromInputs();
        else if (saved[0]) setTarget(new Date(saved[0].ts), saved[0].label);
        else paint();
        renderList();
        return () => clearInterval(iv);
    },
};

/* ---------- Weltzeituhr ---------- */
const CITIES = [
    ['Berlin', 'Europe/Berlin'], ['London', 'Europe/London'], ['Lissabon', 'Europe/Lisbon'], ['Madrid', 'Europe/Madrid'], ['Paris', 'Europe/Paris'], ['Rom', 'Europe/Rome'], ['Zürich', 'Europe/Zurich'], ['Wien', 'Europe/Vienna'],
    ['Athen', 'Europe/Athens'], ['Istanbul', 'Europe/Istanbul'], ['Moskau', 'Europe/Moscow'], ['Kiew', 'Europe/Kyiv'], ['Helsinki', 'Europe/Helsinki'], ['Reykjavík', 'Atlantic/Reykjavik'],
    ['New York', 'America/New_York'], ['Chicago', 'America/Chicago'], ['Denver', 'America/Denver'], ['Los Angeles', 'America/Los_Angeles'], ['Toronto', 'America/Toronto'], ['Vancouver', 'America/Vancouver'],
    ['Mexiko-Stadt', 'America/Mexico_City'], ['São Paulo', 'America/Sao_Paulo'], ['Buenos Aires', 'America/Argentina/Buenos_Aires'], ['Honolulu', 'Pacific/Honolulu'], ['Anchorage', 'America/Anchorage'],
    ['Dubai', 'Asia/Dubai'], ['Mumbai', 'Asia/Kolkata'], ['Bangkok', 'Asia/Bangkok'], ['Singapur', 'Asia/Singapore'], ['Hongkong', 'Asia/Hong_Kong'], ['Peking', 'Asia/Shanghai'], ['Tokio', 'Asia/Tokyo'], ['Seoul', 'Asia/Seoul'],
    ['Sydney', 'Australia/Sydney'], ['Perth', 'Australia/Perth'], ['Auckland', 'Pacific/Auckland'], ['Kairo', 'Africa/Cairo'], ['Johannesburg', 'Africa/Johannesburg'], ['Lagos', 'Africa/Lagos'], ['Nairobi', 'Africa/Nairobi'], ['UTC', 'UTC'],
];
function tzParts(date, tz) {
    const f = new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date);
    const g = k => +(f.find(p => p.type === k) || {}).value;
    return { h: g('hour') % 24, m: g('minute'), y: g('year'), mo: g('month'), d: g('day') };
}
function tzOffset(date, tz) { const p = tzParts(date, tz); return (Date.UTC(p.y, p.mo - 1, p.d, p.h, p.m) - Math.floor(date.getTime() / 60000) * 60000) / 60000; }
CF.views.weltzeit = {
    mount(root) {
        const here = (Intl.DateTimeFormat().resolvedOptions() || {}).timeZone || 'Europe/Berlin';
        let mine = CF.ls.get('wz', ['America/New_York', 'Europe/London', 'Asia/Tokyo', 'Australia/Sydney']);
        let shift = 0;   // Termin-Planer: Minuten ab jetzt
        const list = el('div', { class: 'stack' });
        const slider = el('input', { type: 'range', min: -720, max: 1440, step: 30, value: 0 });
        const sliderLabel = el('b', { class: 'mono' });
        const cityName = tz => (CITIES.find(c => c[1] === tz) || [tz.split('/').pop().replace(/_/g, ' ')])[0];
        function paint() {
            const base = new Date(Date.now() + shift * 60000);
            if (shift) base.setMinutes(Math.round(base.getMinutes() / 30) * 30, 0, 0);
            const offHere = tzOffset(base, here);
            sliderLabel.textContent = shift ? base.toLocaleString(CF.locale(), { weekday: 'short', hour: '2-digit', minute: '2-digit' }) + ' ' + T('bei dir') : T('jetzt');
            fill(list, [here].concat(mine.filter(z => z !== here)).map((tz, i) => {
                const p = tzParts(base, tz);
                const diff = (tzOffset(base, tz) - offHere) / 60;
                const work = p.h >= 8 && p.h < 18, night = p.h < 7 || p.h >= 22;
                return el('div', { class: 'wz' + (work ? ' is-work' : night ? ' is-night' : '') },
                    el('div', { class: 'grow' }, el('b', { text: (i === 0 ? '📍 ' : '') + cityName(tz) }), el('div', { class: 'tiny faint', text: tz + ' · ' + (diff === 0 ? T('gleiche Zeit') : (diff > 0 ? '+' : '') + String(diff).replace('.', ',') + ' h') })),
                    el('div', { class: 'wz__t mono', text: pad2(p.h) + ':' + pad2(p.m) }),
                    el('div', { class: 'tiny faint', style: { minWidth: '5.5rem', textAlign: 'right' }, text: new Date(Date.UTC(p.y, p.mo - 1, p.d)).toLocaleDateString(CF.locale(), { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' }) }),
                    i ? ibtn('x', T('Entfernen'), () => { mine = mine.filter(z => z !== tz); CF.ls.set('wz', mine); paint(); }, 'sm') : el('span', { style: { width: '2rem' } }));
            }));
        }
        slider.addEventListener('input', () => { shift = +slider.value; paint(); });
        const add = select([['', T('+ Stadt hinzufügen')]].concat(CITIES.map(c => [c[1], c[0]])), '', v => { if (v && mine.indexOf(v) < 0) { mine.push(v); CF.ls.set('wz', mine); paint(); } add.value = ''; }, { class: 'in', style: { width: 'auto' } });
        const iv = setInterval(() => { if (!shift) paint(); }, 15000);
        fill(root, el('div', { class: 'stack lg', style: { maxWidth: '48rem' } },
            el('div', { class: 'row' }, add),
            list,
            CF.card(T('Termin-Planer'), 'calendar', el('div', { class: 'stack' }, el('div', { class: 'row between' }, el('span', { class: 'small muted', text: T('Schieb die Zeit, um einen Termin für alle zu finden. Grün = übliche Arbeitszeit (8 bis 18 Uhr).') }), sliderLabel), slider,
                btn(T('Zurück auf jetzt'), 'btn-ghost btn-sm', () => { shift = 0; slider.value = 0; paint(); }, 'refresh')))));
        paint();
        return () => clearInterval(iv);
    },
};
})();
