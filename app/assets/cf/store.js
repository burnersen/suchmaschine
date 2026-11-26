/* Suchmaschine (aus CodeForge) – Verlauf von Scanner und QR-Studio, nur im Browser (localStorage), nie auf dem Server */
(function () {
'use strict';
const CF = window.CF;
const KEY = 'items';
const MAX_HISTORY = 500;

let items = CF.ls.get(KEY, {});
let rev = CF.ls.get('rev', 0);
const listeners = new Set();
let syncing = false, syncAgain = false;

function save() {
    if (!CF.ls.set(KEY, items)) {
        // Speicher voll: älteste Verlaufseinträge ohne Stern entfernen
        const old = Object.entries(items).filter(([, v]) => v.kind === 'h' && !(v.data && v.data.fav)).sort((a, b) => a[1].ts - b[1].ts).slice(0, 200);
        old.forEach(([k]) => delete items[k]);
        CF.ls.set(KEY, items);
    }
    listeners.forEach(fn => { try { fn(); } catch (e) { /* egal */ } });
}

function trim() {
    const hist = Object.entries(items).filter(([, v]) => v.kind === 'h' && !v.deleted);
    if (hist.length <= MAX_HISTORY) return;
    hist.filter(([, v]) => !(v.data && v.data.fav)).sort((a, b) => a[1].ts - b[1].ts).slice(0, hist.length - MAX_HISTORY)
        .forEach(([k]) => { items[k] = { kind: 'h', data: {}, ts: Date.now(), deleted: true, dirty: true }; });
}

const Store = {
    all(kind) {
        return Object.entries(items).filter(([, v]) => v.kind === kind && !v.deleted).map(([uid, v]) => Object.assign({ uid }, v)).sort((a, b) => b.ts - a.ts);
    },
    get(uid) { const v = items[uid]; return v && !v.deleted ? Object.assign({ uid }, v) : null; },
    put(kind, data, uid) {
        uid = uid || CF.uid();
        items[uid] = { kind, data, ts: Date.now(), deleted: false, dirty: true };
        if (kind === 'h') trim();
        save();
        Store.syncSoon();
        return uid;
    },
    update(uid, patch) {
        const v = items[uid];
        if (!v || v.deleted) return;
        v.data = Object.assign({}, v.data, patch);
        v.ts = Date.now();
        v.dirty = true;
        save();
        Store.syncSoon();
    },
    remove(uid) {
        if (!items[uid]) return;
        items[uid] = { kind: items[uid].kind, data: {}, ts: Date.now(), deleted: true, dirty: true };
        save();
        Store.syncSoon();
    },
    clear(kind) {
        Object.keys(items).forEach(k => { if (items[k].kind === kind && !items[k].deleted) items[k] = { kind, data: {}, ts: Date.now(), deleted: true, dirty: true }; });
        save();
        Store.syncSoon();
    },
    onChange(fn) { listeners.add(fn); return () => listeners.delete(fn); },
    /** Scan oder erstellten Code im Verlauf ablegen. src: scan | image | create | pair */
    addHistory(text, format, src, extra) {
        if (!CF.prefs.history) return null;
        const p = CF.Parse.content(text, extra || {}, CF.lang);
        // Gleicher Inhalt innerhalb von 10 Sekunden nur einmal
        const recent = Store.all('h').slice(0, 5).find(h => h.data.t === text && Date.now() - h.ts < 10000);
        if (recent) return recent.uid;
        return Store.put('h', { t: text, f: format || '', src, type: p.type, title: p.summary || p.title, fav: false });
    },
    pending() { return Object.values(items).filter(v => v.dirty).length; },

    syncSoon() { /* nur lokal: die Suchmaschine gleicht den Verlauf nicht mit dem Server ab */ },
};

window.addEventListener('storage', e => { if (e.key === 'sm.cf.' + KEY) { items = CF.ls.get(KEY, {}); listeners.forEach(fn => fn()); } });
CF.Store = Store;
})();
