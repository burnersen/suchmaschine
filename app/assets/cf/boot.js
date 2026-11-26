/* Suchmaschine – startet die Werkzeug-App der Seite (<div data-cf-app="…">). Wird als letztes Skript geladen. */
(function () {
'use strict';
const CF = window.CF;
const host = document.querySelector('[data-cf-app]');
if (!CF || !host) return;
CF.mountRoot = host;
let cleanup = null;

CF.mount = (view, params) => {
    const v = CF.views[view];
    if (!v) return false;
    if (typeof cleanup === 'function') { try { cleanup(); } catch (e) { /* egal */ } }
    cleanup = null;
    host.replaceChildren();
    host.setAttribute('data-cf-app', view);
    try {
        cleanup = v.mount(host, params || {}) || null;
    } catch (e) {
        host.replaceChildren(CF.msg('danger', CF.T('Das Werkzeug konnte nicht starten: %s', e.message)));
        if (window.console) console.error(e);
        return false;
    }
    return true;
};

const tool = host.getAttribute('data-tool') || CF.boot.tool;
const view = host.getAttribute('data-cf-app');
// Werte aus der Adresse (z. B. ?a=tools&tool=qr&text=…) und von anderen Werkzeugen (CF.go)
const params = {};
new URLSearchParams(location.search).forEach((v, k) => { if (!['a', 'tool'].includes(k)) params[k] = v; });
Object.assign(params, CF.takeParams(tool) || {});
if (CF.mount(view, params)) {
    document.querySelectorAll('[data-cf-fallback]').forEach(f => { f.hidden = true; });
}
window.addEventListener('pagehide', () => { if (typeof cleanup === 'function') { try { cleanup(); } catch (e) { /* egal */ } } });
})();
