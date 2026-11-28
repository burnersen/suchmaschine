/* Suchmaschine – Service Worker der App (Handy, Startbildschirm). Bewusst klein gehalten:
   1. Ohne Netz kommt die eigene Seite „Keine Verbindung“ statt der Fehlerseite des Browsers.
   2. Aussehen, Skripte, Schriften und Symbole (Adressen mit Versionsnummer, die sich nie ändern) liegen auf dem Gerät,
      die App startet dadurch schneller.
   Suchen, Treffer, Einstellungen und alles Persönliche gehen immer direkt zum Server und werden hier nie gespeichert.
   __SM_…__ ersetzt der Server beim Ausliefern (app/lib/serve.php). */
'use strict';

var CACHE = 'sm-__SM_VERSION__';
var OFFLINE = '__SM_OFFLINE__';
var FIRST = __SM_FIRST__;

self.addEventListener('install', function (ev) {
    ev.waitUntil(caches.open(CACHE).then(function (c) {
        // Die Offline-Seite ist Pflicht, der Rest darf einzeln fehlschlagen
        return c.add(new Request(OFFLINE, { credentials: 'omit', cache: 'reload' })).then(function () {
            return Promise.all(FIRST.map(function (u) { return c.add(u).catch(function () {}); }));
        });
    }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (ev) {
    ev.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.map(function (k) { return k !== CACHE && k.indexOf('sm-') === 0 ? caches.delete(k) : null; }));
    }).then(function () { return self.clients.claim(); }));
});

/* Dateien, die sich unter ihrer Adresse nie ändern: ?a=asset|font|look|logo|appicon mit Versionsangabe (v=…) */
function isStatic(url) {
    var a = url.searchParams.get('a');
    return ['asset', 'font', 'look', 'logo', 'appicon'].indexOf(a) >= 0 && url.searchParams.has('v');
}

self.addEventListener('fetch', function (ev) {
    var req = ev.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (req.mode === 'navigate') {
        // Seiten immer frisch vom Server, nur ohne Netz die Offline-Seite
        ev.respondWith(fetch(req).catch(function () {
            return caches.match(OFFLINE).then(function (r) { return r || Response.error(); });
        }));
        return;
    }
    if (isStatic(url)) {
        ev.respondWith(caches.match(req).then(function (hit) {
            if (hit) return hit;
            return fetch(req).then(function (res) {
                if (res.ok && /immutable/.test(res.headers.get('Cache-Control') || '')) {
                    var copy = res.clone();
                    caches.open(CACHE).then(function (c) { c.put(req, copy); });
                }
                return res;
            });
        }));
        return;
    }
    // Das Logo im Stylesheet hat keine Versionsangabe: frisch vom Server, ohne Netz aus dem Vorrat
    if (url.searchParams.get('a') === 'asset') {
        ev.respondWith(fetch(req).catch(function () {
            return caches.match(req).then(function (r) { return r || Response.error(); });
        }));
    }
});
