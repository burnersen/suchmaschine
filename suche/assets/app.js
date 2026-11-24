/* Private Suche: etwas Komfort, alles optional. Ohne JS funktioniert die Seite auch. */
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;

    function startLoading() { body.classList.add('is-loading'); }
    function stopLoading() { body.classList.remove('is-loading'); }

    // Ladezustand beim Absenden der Suche
    document.querySelectorAll('[data-search-form], [data-busy-form]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (body.classList.contains('is-loading')) { ev.preventDefault(); return; } // Doppelklick
            var input = form.querySelector('input[name="q"]');
            if (input && !input.value.trim()) { ev.preventDefault(); input.focus(); return; }
            startLoading();
        });
    });

    // Ladezustand auch beim Blättern
    document.querySelectorAll('.pager a, .notice a, .brand').forEach(function (a) {
        a.addEventListener('click', function (ev) {
            if (!ev.ctrlKey && !ev.metaKey && !ev.shiftKey && ev.button === 0) { startLoading(); }
        });
    });

    // Zurück-Button (bfcache): Ladezustand zurücksetzen
    window.addEventListener('pageshow', stopLoading);

    // Löschen-Knopf im Suchfeld
    document.querySelectorAll('[data-search-form]').forEach(function (form) {
        var input = form.querySelector('input[name="q"]');
        var clear = form.querySelector('[data-clear]');
        function sync() { form.classList.toggle('has-value', input.value.length > 0); }
        input.addEventListener('input', sync);
        sync();
        clear.addEventListener('click', function () { input.value = ''; sync(); input.focus(); });
    });

    // "/" fokussiert das Suchfeld, Esc verlässt es
    document.addEventListener('keydown', function (ev) {
        var t = ev.target;
        var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
        var input = document.querySelector('[data-search-form] input[name="q"]');
        if (!input) { return; }
        if (ev.key === '/' && !typing) {
            ev.preventDefault();
            input.focus();
            input.select();
        } else if (ev.key === 'Escape' && t === input) {
            input.blur();
        }
    });

    // Favicon erst zeigen, wenn es geladen ist (bis dahin den Anfangsbuchstaben)
    function showFav(img) { img.classList.add('is-loaded'); }
    document.querySelectorAll('.fav img').forEach(function (img) {
        if (img.complete && img.naturalWidth > 0) { showFav(img); }
        else { img.addEventListener('load', function () { showFav(img); }); }
    });

    // Favicon kaputt? Dann den Buchstaben stehen lassen
    document.addEventListener('error', function (ev) {
        var img = ev.target;
        if (img && img.tagName === 'IMG' && img.parentNode && img.parentNode.classList.contains('fav')) {
            img.remove();
        }
    }, true);

    // Design: auto → hell → dunkel → auto (wird im Cookie gemerkt)
    var order = ['auto', 'light', 'dark'];
    var labels = { auto: 'Design: automatisch', light: 'Design: hell', dark: 'Design: dunkel' };
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.title = labels[root.dataset.theme] || labels.auto;
        btn.addEventListener('click', function () {
            var next = order[(order.indexOf(root.dataset.theme) + 1) % order.length];
            root.dataset.theme = next;
            btn.title = labels[next];
            document.cookie = 'theme=' + next + '; path=/; max-age=31536000; SameSite=Lax';
        });
    });

    // Sicherheitsabfrage (z. B. Nutzer löschen)
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { ev.preventDefault(); }
        });
    });

    // Benutzermenü bei Klick daneben schließen
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('details.usermenu[open]').forEach(function (d) {
            if (!d.contains(ev.target)) { d.removeAttribute('open'); }
        });
    });
})();
