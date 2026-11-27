/* Suchmaschine – etwas Komfort im Browser. Ohne JavaScript funktioniert alles auch, nur schlichter. */
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    root.classList.add('has-js');
    var $ = function (sel, el) { return (el || document).querySelector(sel); };
    var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };

    function loading(on) { body.classList.toggle('is-loading', on); }
    window.addEventListener('pageshow', function () { loading(false); });

    function plainClick(ev) { return ev.button === 0 && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && !ev.altKey; }

    // Ladeanzeige bei Formularen und internen Links
    $$('form[data-busy], form[data-search]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (body.classList.contains('is-loading')) { ev.preventDefault(); return; }
            loading(true);
        });
    });
    $$('a[data-load], .brand').forEach(function (a) {
        a.addEventListener('click', function (ev) { if (plainClick(ev)) loading(true); });
    });

    // „Links in neuem Tab öffnen“: gilt für jeden Link nach draußen, egal wo er steht (Treffer, Wetter, Kurzinfo, Hilfe …).
    // Läuft vor allen anderen Klick-Handlern, damit niemand den Link vorher im selben Tab öffnet.
    var newTab = body.hasAttribute('data-newtab');
    function external(a) {
        if (!a || !a.href || a.hasAttribute('download')) return false;
        try { var u = new URL(a.href, location.href); return (u.protocol === 'https:' || u.protocol === 'http:') && u.origin !== location.origin; } catch (e) { return false; }
    }
    function forceNewTab(a) {
        if (!newTab || !external(a)) return;
        a.target = '_blank';
        var parts = (a.getAttribute('rel') || '').split(' ').filter(Boolean);
        ['noopener', 'noreferrer'].forEach(function (r) { if (parts.indexOf(r) < 0) parts.push(r); });
        a.setAttribute('rel', parts.join(' '));
    }
    document.addEventListener('click', function (ev) { forceNewTab(ev.target.closest && ev.target.closest('a[href]')); }, true);

    // Autofokus nur am Rechner: am Handy würde sonst sofort die Tastatur aufgehen und die halbe Seite verdecken
    var coarse = !!(window.matchMedia && matchMedia('(pointer: coarse)').matches);
    $$('[data-autofocus]').forEach(function (i) { if (!coarse) { i.focus(); } });

    /* ---------- Suchfeld: Löschen-Knopf und Vorschläge aus dem eigenen Verlauf ---------- */
    var HKEY = 'sm_history';
    function hist() { try { var h = JSON.parse(localStorage.getItem(HKEY) || '[]'); return Array.isArray(h) ? h : []; } catch (e) { return []; } }
    function histSave(h) { try { localStorage.setItem(HKEY, JSON.stringify(h.slice(0, 60))); } catch (e) {} }
    function histAdd(q) {
        q = q.trim();
        if (!q) return;
        var h = hist().filter(function (x) { return x.toLowerCase() !== q.toLowerCase(); });
        h.unshift(q);
        histSave(h);
    }
    function histDel(q) { histSave(hist().filter(function (x) { return x !== q; })); }

    $$('form[data-search]').forEach(function (form) {
        var input = $('input[name="q"]', form);
        var clear = $('[data-clear]', form);
        var list = $('.sbox__list', form);
        var useHist = form.getAttribute('data-history') === '1';
        var bangUrl = form.getAttribute('data-bangs');
        var sugUrl = form.getAttribute('data-suggest');
        var sugCache = {}, sugT = 0;
        var target = $('[data-bang-target]', form);
        var active = -1;
        var items = [];     // {kind: 'hist', text} oder {kind: 'bang', t, n, d, dead}
        var mode = '';
        var bangCache = {};
        var seq = 0, tSeq = 0;

        function sync() { form.classList.toggle('has-value', input.value.length > 0); }
        input.addEventListener('input', function () { sync(); render(); showTarget(); });
        sync();
        clear.addEventListener('click', function () { input.value = ''; sync(); input.focus(); render(); showTarget(); });

        form.addEventListener('submit', function (ev) {
            if (!input.value.trim()) { ev.preventDefault(); loading(false); input.focus(); return; }
            if (useHist) histAdd(input.value);
            close();
        });

        function close() { list.hidden = true; form.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); active = -1; mode = ''; }

        function mark(text, q) {
            var span = document.createElement('span');
            span.className = 'sbox__text';
            var i = q ? text.toLowerCase().indexOf(q.toLowerCase()) : -1;
            if (i === 0) {
                span.appendChild(document.createTextNode(text.slice(0, q.length)));
                var b = document.createElement('b');
                b.textContent = text.slice(q.length);
                span.appendChild(b);
            } else {
                span.textContent = text;
            }
            return span;
        }

        /* ---- Bangs: das Wort mit „!“ an der Schreibmarke, Vorschläge vom Server, Ziel unter dem Feld ---- */
        function bangAt() {
            var v = input.value, pos = input.selectionStart === null || input.selectionStart === undefined ? v.length : input.selectionStart;
            var start = v.lastIndexOf(' ', pos - 1) + 1, end = v.indexOf(' ', pos);
            if (end < 0) end = v.length;
            var w = v.slice(start, end);
            return w.charAt(0) === '!' ? { w: w.slice(1).toLowerCase(), start: start, end: end } : null;
        }
        function bangGet(q, cb) {
            if (!bangUrl) return;
            if (bangCache.hasOwnProperty(q)) { cb(bangCache[q]); return; }
            fetch(bangUrl + (bangUrl.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) { if (j) { bangCache[q] = j; cb(j); } })
                .catch(function () {});
        }
        // Erstes oder letztes Wort ist ein Bang? Dann zeigen, wohin es geht
        var tTimer = 0;
        function showTarget() {
            if (!target || !bangUrl) return;
            clearTimeout(tTimer);
            var words = input.value.trim().split(/\s+/), w = '', rest = '';
            if (words[0] && words[0].charAt(0) === '!' && words[0].length > 1) { w = words[0]; rest = words.slice(1).join(' '); }
            else if (words.length > 1 && words[words.length - 1].charAt(0) === '!' && words[words.length - 1].length > 1) { w = words[words.length - 1]; rest = words.slice(0, -1).join(' '); }
            if (!w) { target.hidden = true; form.classList.remove('has-bang'); return; }
            tTimer = setTimeout(function () {
                var my = ++tSeq;
                bangGet(w.slice(1).toLowerCase(), function (j) {
                    if (my !== tSeq) return;
                    var e = j.exact;
                    target.innerHTML = '';
                    if (!e) { target.hidden = true; form.classList.remove('has-bang'); return; }
                    var go = document.createElement('span');
                    go.className = 'sbox__go-to';
                    go.textContent = (form.getAttribute('data-label-go') || '→') + ' ';
                    var b = document.createElement('b');
                    b.textContent = e.n;
                    var d = document.createElement('small');
                    d.textContent = ' ' + e.d;
                    target.appendChild(go); target.appendChild(b); target.appendChild(d);
                    if (e.dead) { var x = document.createElement('span'); x.className = 'tag tag--warn'; x.textContent = form.getAttribute('data-label-dead'); target.appendChild(document.createTextNode(' ')); target.appendChild(x); }
                    if (!rest) { var h = document.createElement('span'); h.className = 'sbox__hint'; h.textContent = ' · ' + form.getAttribute('data-label-type'); target.appendChild(h); }
                    target.hidden = false;
                    form.classList.add('has-bang');
                });
            }, 80);
        }

        function open() {
            list.hidden = false;
            form.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }

        function render() {
            if (document.activeElement !== input) { close(); return; }
            var bw = bangUrl ? bangAt() : null;
            if (bw) {
                var my = ++seq;
                bangGet(bw.w, function (j) {
                    if (my !== seq || document.activeElement !== input) return;
                    items = (j.list || []).map(function (b) { return { kind: 'bang', t: b.t, n: b.n, d: b.d, dead: b.dead }; });
                    draw(bw.w);
                });
                return;
            }
            var q = input.value.trim();
            var lq = q.toLowerCase();
            var mine = !useHist ? [] : hist().filter(function (x) { return !lq || (x.toLowerCase().indexOf(lq) !== -1 && x.toLowerCase() !== lq); })
                .slice(0, lq ? 4 : 8).map(function (x) { return { kind: 'hist', text: x }; });
            var my = ++seq;   // noch laufende Bang-Vorschläge gelten nicht mehr
            items = mine;
            draw(q);
            // Vorschläge von Brave (Autosuggest, über den eigenen Server) unter den eigenen letzten Suchen
            if (!sugUrl || q.length < 2 || q.charAt(0) === '!') return;
            function add(list) {
                if (my !== seq || document.activeElement !== input || input.value.trim() !== q) return;
                var have = {};
                mine.forEach(function (it) { have[it.text.toLowerCase()] = true; });
                items = mine.concat(list.filter(function (x) { return !have[x.toLowerCase()] && x.toLowerCase() !== lq; })
                    .slice(0, 10 - mine.length).map(function (x) { return { kind: 'sug', text: x }; }));
                draw(q);
            }
            if (sugCache[lq]) { add(sugCache[lq]); return; }
            clearTimeout(sugT);
            sugT = setTimeout(function () {
                fetch(sugUrl + (sugUrl.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) { if (j && Array.isArray(j.list)) { sugCache[lq] = j.list; add(j.list); } })
                    .catch(function () {});
            }, 200);
        }

        function draw(q) {
            list.innerHTML = '';
            active = -1;
            if (!items.length) { close(); return; }
            mode = items[0].kind;
            items.forEach(function (it, i) {
                var li = document.createElement('li');
                li.className = 'sbox__opt' + (it.kind === 'bang' ? ' sbox__opt--bang' : '');
                li.setAttribute('role', 'option');
                li.id = 'sbox-opt-' + i;
                if (it.kind === 'bang') {
                    var c = document.createElement('code');
                    c.appendChild(mark('!' + it.t, '!' + q));
                    li.appendChild(c);
                    var n = document.createElement('span');
                    n.className = 'sbox__text';
                    n.textContent = it.n;
                    li.appendChild(n);
                    var d = document.createElement('small');
                    d.className = 'sbox__dom' + (it.dead ? ' is-dead' : '');
                    d.textContent = it.d;
                    if (it.dead) d.title = form.getAttribute('data-label-dead');
                    li.appendChild(d);
                    li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pickBang(it.t); });
                } else if (it.kind === 'sug') {
                    li.className += ' sbox__opt--sug';
                    li.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg>';
                    li.appendChild(mark(it.text, q));
                    li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(it.text); });
                } else {
                    li.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>';
                    li.appendChild(mark(it.text, q));
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'sbox__del';
                    del.textContent = form.getAttribute('data-label-remove') || '×';
                    del.addEventListener('mousedown', function (ev) { ev.preventDefault(); ev.stopPropagation(); histDel(it.text); render(); });
                    li.appendChild(del);
                    li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(it.text); });
                }
                list.appendChild(li);
            });
            open();
        }

        function pick(text) {
            input.value = text;
            sync();
            close();
            if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { histAdd(text); loading(true); form.submit(); }
        }

        // Bang übernehmen: Kürzel einsetzen, Leerzeichen dahinter, weiter tippen
        function pickBang(t) {
            var bw = bangAt(), v = input.value;
            if (!bw) { bw = { start: 0, end: 0 }; }
            var after = v.slice(bw.end).replace(/^\s+/, '');
            input.value = v.slice(0, bw.start) + '!' + t + ' ' + after;
            var pos = bw.start + t.length + 2;
            input.setSelectionRange(pos, pos);
            sync();
            close();
            showTarget();
            input.focus();
        }

        var typed = input.value;
        input.addEventListener('input', function () { typed = input.value; });

        function move(d) {
            if (list.hidden || !items.length) return;
            active += d;
            if (active >= items.length) active = -1;
            if (active < -1) active = items.length - 1;
            $$('.sbox__opt', list).forEach(function (li, i) { li.classList.toggle('is-active', i === active); });
            input.setAttribute('aria-activedescendant', active >= 0 ? 'sbox-opt-' + active : '');
            if (mode !== 'bang') { input.value = active >= 0 ? items[active].text : typed; sync(); }
        }

        input.addEventListener('focus', function () { typed = input.value; render(); });
        input.addEventListener('click', function () { if (bangAt()) render(); });
        input.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== input) close(); }, 120); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown') { ev.preventDefault(); if (list.hidden) render(); else move(1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); move(-1); }
            else if (ev.key === 'Escape') { if (!list.hidden) { ev.preventDefault(); close(); } else { input.blur(); } }
            else if (ev.key === 'Delete' && ev.shiftKey && active >= 0 && items[active].kind === 'hist') { ev.preventDefault(); histDel(items[active].text); render(); }
            else if (mode === 'bang' && !list.hidden && (ev.key === 'Tab' || (ev.key === 'Enter' && active >= 0))) {
                var it = items[active >= 0 ? active : 0];
                // Enter ohne Auswahl, wenn das Kürzel schon stimmt: ganz normal abschicken
                if (ev.key === 'Tab' && ev.shiftKey) return;
                ev.preventDefault();
                pickBang(it.t);
            }
        });
        // Knopf „!“: Bang an den Anfang setzen und die Liste öffnen
        var bbtn = $('[data-bang-btn]', form);
        if (bbtn) bbtn.addEventListener('click', function () {
            var v = input.value.replace(/^\s+/, '');
            if (v.charAt(0) !== '!') input.value = '!' + (v ? ' ' + v : '');
            input.focus();
            input.setSelectionRange(1, 1);
            sync();
            render();
        });
        showTarget();
    });

    // Verlauf löschen (Einstellungen)
    $$('[data-clear-history]').forEach(function (b) {
        b.addEventListener('click', function () {
            try { localStorage.removeItem(HKEY); } catch (e) {}
            b.textContent = b.getAttribute('data-done');
            b.disabled = true;
        });
    });

    /* ---------- Favicons: erst zeigen, wenn geladen; sonst bleibt der Anfangsbuchstabe ---------- */
    $$('.fav img').forEach(function (img) {
        function ok() { img.classList.add('is-loaded'); }
        if (img.complete && img.naturalWidth > 1) ok(); else img.addEventListener('load', function () { if (img.naturalWidth > 1) ok(); });
    });
    document.addEventListener('error', function (ev) {
        var el = ev.target;
        if (el && el.tagName === 'IMG') {
            if (el.parentNode && el.parentNode.classList && el.parentNode.classList.contains('fav')) el.remove();
            else if (el.closest('.grid__item')) el.closest('.grid__item').remove();
            else if (!el.hasAttribute('data-lb-img')) el.style.visibility = 'hidden';
        }
    }, true);

    /* ---------- Design umschalten: automatisch → hell → dunkel ---------- */
    var order = ['auto', 'light', 'dark'];
    $$('[data-theme-toggle]').forEach(function (btn) {
        function label() { btn.title = btn.getAttribute('data-label-' + root.getAttribute('data-theme')) || ''; }
        label();
        btn.addEventListener('click', function () {
            var next = order[(order.indexOf(root.getAttribute('data-theme')) + 1) % order.length];
            root.setAttribute('data-theme', next);
            // Nur für das Verzeichnis der Suchmaschine, nicht für andere Programme auf derselben Domain
            var path = btn.getAttribute('data-path') || '/';
            document.cookie = 'sm_theme=' + next + '; path=' + path + '; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            if (path !== '/') document.cookie = 'sm_theme=; path=/; max-age=0';   // alter Cookie von Version 1.4 und früher
            label();
        });
    });

    /* ---------- Reiter (Einstellungen, Verwaltung): der aktive ist immer zu sehen, Schatten zeigen, dass man wischen kann ---------- */
    $$('.subnav').forEach(function (bar) {
        function edges() {
            bar.classList.toggle('is-more-left', bar.scrollLeft > 4);
            bar.classList.toggle('is-more-right', bar.scrollLeft + bar.clientWidth < bar.scrollWidth - 4);
        }
        var cur = $('.is-active, [aria-current]', bar);
        if (cur && bar.scrollWidth > bar.clientWidth) bar.scrollLeft = cur.offsetLeft - (bar.clientWidth - cur.offsetWidth) / 2;
        edges();
        bar.addEventListener('scroll', edges, { passive: true });
        window.addEventListener('resize', edges);
    });

    /* ---------- Werkzeuge: alte Adressen mit #rechner, #qr … öffnen das Werkzeug direkt ---------- */
    if (/[?&]a=tools(&|$)/.test(location.search) && !/[?&]tool=/.test(location.search) && /^#[a-z]{2,12}$/.test(location.hash)) {
        var toolCard = document.getElementById(location.hash.slice(1));
        if (toolCard && toolCard.classList.contains('toolcard')) location.replace(toolCard.href);
    }

    /* ---------- Meine IP: die andere Version (IPv4 bei IPv6-Verbindung und umgekehrt) im Browser ermitteln ---------- */
    $$('[data-ip-other]').forEach(function (box) {
        var own = box.getAttribute('data-own'), ext = box.getAttribute('data-ext');
        var out = $('[data-ip-out]', box), msg = $('[data-ip-msg]', box), ask = $('[data-ip-ask]', box);
        function ask4(url) {
            msg.textContent = '';
            return fetch(url, { cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' })
                .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                .then(function (j) {
                    var ip = String(j.ip || '');
                    if (!/^[0-9a-fA-F:.]{3,45}$/.test(ip)) throw new Error();
                    $('output', out).textContent = ip;
                    $('[data-copy-text]', out).setAttribute('data-copy-text', ip);
                    out.hidden = false;
                    ask.hidden = true;
                });
        }
        function fail() { msg.textContent = box.getAttribute('data-fail'); }
        box.hidden = false;
        $('[data-ip-go]', box).addEventListener('click', function () { ask4(ext).catch(fail); });
        // Eigene Subdomain: kein fremder Dienst, darum gleich fragen. Klappt das nicht, bleibt der Knopf für ipify.org.
        if (own) ask4(own).catch(function () { ask.hidden = false; });
        else ask.hidden = false;
    });

    /* ---------- Menüs schließen, wenn man daneben klickt ---------- */
    document.addEventListener('click', function (ev) {
        $$('details.menu[open], details.tools[open], details.pop[open], details.resmenu[open]').forEach(function (d) {
            if (!d.contains(ev.target)) d.removeAttribute('open');
        });
    });
    // Zeitraum-Menü unter den Reiter setzen (das Reiter-Band scrollt seitlich)
    $$('details.tools').forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            var r = $('summary', d).getBoundingClientRect();
            var p = $('.tools__panel', d);
            p.style.left = Math.max(8, Math.min(r.left, window.innerWidth - p.offsetWidth - 8)) + 'px';
            p.style.top = r.bottom + 'px';
            $$('details.tools[open]').forEach(function (o) { if (o !== d) o.removeAttribute('open'); });
        });
    });
    // Beim Scrollen zu, außer man tippt gerade im Menü (eigener Dateityp; die Handy-Tastatur scrollt die Seite)
    window.addEventListener('scroll', function () {
        $$('details.tools[open]').forEach(function (d) {
            var f = document.activeElement;
            if (!(f && f.tagName === 'INPUT' && d.contains(f))) d.removeAttribute('open');
        });
    }, { passive: true });

    // Sicherheitsfragen (auch für Formulare, die erst später auf die Seite kommen, und für einzelne Knöpfe mit data-confirm-btn)
    document.addEventListener('submit', function (ev) {
        var f = ev.target, b = ev.submitter;
        var msg = b && b.getAttribute && b.getAttribute('data-confirm-btn') || (f.getAttribute && f.getAttribute('data-confirm'));
        if (msg && !window.confirm(msg)) { ev.preventDefault(); ev.stopImmediatePropagation(); loading(false); }
    }, true);
    // Auswahl, die sofort speichert (Suchmaschine der Gäste auf der Startseite), der Knopf wird dann nicht gebraucht
    $$('select[data-autosubmit-select]').forEach(function (s) {
        var b = $('[data-nojs]', s.form);
        if (b) b.hidden = true;
        s.addEventListener('change', function () { loading(true); s.form.submit(); });
    });
    // KI-Antwort von Brave: auf Knopfdruck oder automatisch, die Trefferliste wartet nicht darauf
    $$('[data-answer]').forEach(function (box) {
        var btn = $('[data-answer-go]', box), out = $('[data-answer-text]', box), spin = $('.spinner', box), go = $('.answer__go', box);
        function get() {
            if (!window.fetch) { box.hidden = true; return; }
            btn.disabled = true;
            spin.hidden = false;
            fetch(box.getAttribute('data-answer'), { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
                .then(function (r) { return r.status === 200 ? r.text() : ''; })
                .then(function (html) {
                    if (!html.trim()) { box.hidden = true; return; }
                    out.innerHTML = html;
                    go.hidden = true;
                    $('.answer__note', box).hidden = false;
                }, function () { out.textContent = box.getAttribute('data-error'); go.hidden = true; });
        }
        btn.addEventListener('click', get);
        if (box.hasAttribute('data-answer-auto')) get();
    });

    // Filter in der Trefferliste ohne OK-Knopf: Auswahl wirkt sofort, ein Zeitraum, sobald beide Tage stehen (oder einer, mit Enter)
    $$('.tools__form select').forEach(function (s) {
        s.addEventListener('change', function () { loading(true); s.form.submit(); });
    });
    $$('.tools__form--range').forEach(function (f) {
        var d = $$('input[type="date"]', f);
        d.forEach(function (i) {
            i.addEventListener('change', function () { if (d.every(function (x) { return x.value; })) { loading(true); f.submit(); } });
        });
    });

    // Schalter, die sofort speichern
    $$('form[data-autosubmit] input[type="checkbox"]').forEach(function (c) {
        c.addEventListener('change', function () { loading(true); c.form.submit(); });
    });

    // Passwortfelder für Keys: Anzeigen-Knopf
    $$('input[data-reveal]').forEach(function (input) {
        var wrap = document.createElement('span');
        wrap.className = 'reveal';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var b = document.createElement('button');
        b.type = 'button';
        var de = root.lang === 'de';
        b.textContent = de ? 'Zeigen' : 'Show';
        b.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            b.textContent = show ? (de ? 'Verbergen' : 'Hide') : (de ? 'Zeigen' : 'Show');
        });
        wrap.appendChild(b);
    });

    // Kopieren
    $$('[data-copy]').forEach(function (el) {
        el.addEventListener('click', function () {
            var t = el.textContent;
            if (navigator.clipboard) navigator.clipboard.writeText(t).then(function () {
                el.textContent = el.getAttribute('data-done');
                setTimeout(function () { el.textContent = t; }, 1400);
            });
        });
    });

    /* ---------- App-Symbol aus dem Logo zeichnen (512 × 512, weißer Grund) ---------- */
    function logoToPng(src, cb) {
        var img = new Image();
        img.onload = function () {
            var c = document.createElement('canvas');
            c.width = c.height = 512;
            var x = c.getContext('2d');
            x.fillStyle = '#ffffff';
            x.fillRect(0, 0, 512, 512);
            var w = img.naturalWidth || 512, h = img.naturalHeight || 512;
            var s = Math.min(410 / w, 410 / h);
            x.drawImage(img, (512 - w * s) / 2, (512 - h * s) / 2, w * s, h * s);
            try { cb(c.toDataURL('image/png')); } catch (e) { cb(''); }
        };
        img.onerror = function () { cb(''); };
        img.src = src;
    }
    var logoFile = $('input[name="logo"]');
    var logoPng = $('[data-logo-png]');
    if (logoFile && logoPng) {
        var pending = false, submitLater = false;
        logoFile.addEventListener('change', function () {
            logoPng.value = '';
            var f = logoFile.files && logoFile.files[0];
            if (!f || !window.FileReader) return;
            pending = true;
            var r = new FileReader();
            r.onload = function () {
                logoToPng(r.result, function (png) {
                    logoPng.value = png;
                    pending = false;
                    if (submitLater) { submitLater = false; logoFile.form.submit(); }
                });
            };
            r.onerror = function () { pending = false; };
            r.readAsDataURL(f);
        });
        logoFile.form.addEventListener('submit', function (ev) {
            if (pending) { ev.preventDefault(); submitLater = true; }
        });
    }
    // Logo aus einer älteren Version ohne App-Symbol: einmal still nachholen
    var miss = $('[data-appicon-missing]');
    if (miss && window.fetch && window.FormData) {
        logoToPng(miss.getAttribute('data-logo-src'), function (png) {
            if (!png) return;
            var fd = new FormData();
            fd.append('csrf', miss.getAttribute('data-csrf'));
            fd.append('do', 'appicon');
            fd.append('png', png);
            fetch(miss.getAttribute('data-post'), { method: 'POST', body: fd, credentials: 'same-origin' });
        });
    }

    /* ---------- Endlos-Scrollen mit Widerstand ----------
       Jedes Nachladen kostet eine Suche, darum soll es nicht aus Versehen passieren. Nachgeladen wird erst,
       wenn man ganz unten angekommen ist (die Seite scrollt nicht weiter) und dann bewusst weiterzieht:
       am Handy ein deutlicher Wisch nach oben, mit Mausrad oder Touchpad mehrere Rasten, mit der Tastatur mehrmals ↓ oder Leertaste.
       Schwung vom normalen Scrollen zählt nicht: Die ersten Bewegungen direkt nach dem Ankommen werden übergangen.
       Lässt man vorher los, läuft der Balken zurück. Der Knopf lädt sofort. */
    function sizeTiles(list) {
        list.forEach(function (a) {
            var w = parseInt(a.getAttribute('data-w'), 10), h = parseInt(a.getAttribute('data-h'), 10);
            if (w > 0 && h > 0) {
                var ratio = Math.max(.5, Math.min(2.6, w / h));
                a.style.flexGrow = String(ratio * 100);
                a.style.flexBasis = (ratio * (window.innerWidth < 640 ? 130 : 190)) + 'px';
            }
        });
    }
    function initMore(more) {
        if (!more || !window.fetch) return;
        var pull = $('.more__pull', more), bar = $('.more__bar i', more), btn = $('.more__btn', more);
        more.classList.add('is-pull');
        var busy = false;
        var PULL_WHEEL = 1400, PULL_TOUCH = 0.25, PULL_KEYS = 4;   // Mausrad in Pixeln, Wisch in Bildschirmhöhen, Tastendrücke
        var SETTLE = 450, IDLE = 700;                               // ms Ruhe nach dem Ankommen, ms bis der Balken zurückläuft
        var arrived = 0, acc = 0, idleT = 0, touchY = null, touchOk = false;
        function atBottom() {
            var doc = document.documentElement;
            return window.innerHeight + window.scrollY >= Math.max(doc.scrollHeight, body.scrollHeight) - 4;
        }
        function settled() { return arrived && Date.now() - arrived >= SETTLE; }
        function show(p) {
            if (bar) bar.style.transform = 'scaleX(' + Math.max(0, Math.min(1, p)).toFixed(3) + ')';
            more.classList.toggle('is-near', p > 0);
        }
        function reset() { acc = 0; show(0); }
        function add(p) {
            if (busy) return;
            acc = Math.min(1, acc + p);
            show(acc);
            clearTimeout(idleT);
            if (acc >= 1) { load(); return; }
            idleT = setTimeout(reset, IDLE);
        }
        function onScroll() {
            if (busy) return;
            if (atBottom()) { if (!arrived) arrived = Date.now(); }
            else { arrived = 0; reset(); }
        }
        function onWheel(ev) {
            if (busy || ev.deltaY <= 0) return;
            if (!atBottom()) { arrived = 0; return; }
            if (!arrived) arrived = Date.now();
            if (!settled()) { arrived = Date.now(); return; }   // noch Schwung vom Scrollen: Ruhezeit beginnt neu
            add((ev.deltaMode === 1 ? ev.deltaY * 40 : ev.deltaMode === 2 ? ev.deltaY * window.innerHeight : ev.deltaY) / PULL_WHEEL);
        }
        function onTouchStart(ev) {
            touchOk = !busy && atBottom() && settled() && ev.touches.length === 1;
            touchY = touchOk ? ev.touches[0].clientY : null;
        }
        function onTouchMove(ev) {
            if (!touchOk || touchY === null) return;
            var dy = touchY - ev.touches[0].clientY;   // Finger nach oben = weiterziehen
            acc = 0;
            add(Math.max(0, dy) / (window.innerHeight * PULL_TOUCH));
        }
        function onTouchEnd() {
            touchY = null;
            if (!busy && acc < 1) reset();
        }
        function onKey(ev) {
            if (busy || ev.altKey || ev.ctrlKey || ev.metaKey) return;
            var t = ev.target;
            if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
            if (['ArrowDown', 'PageDown', ' ', 'End'].indexOf(ev.key) < 0 || !atBottom()) return;
            if (!settled()) { if (!arrived) arrived = Date.now(); return; }
            add(1 / PULL_KEYS);
        }
        function stop() {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('wheel', onWheel);
            window.removeEventListener('touchstart', onTouchStart);
            window.removeEventListener('touchmove', onTouchMove);
            window.removeEventListener('touchend', onTouchEnd);
            window.removeEventListener('keydown', onKey);
        }
        function load() {
            if (busy) return;
            busy = true;
            clearTimeout(idleT);
            show(1);
            more.classList.remove('is-error');
            more.classList.add('is-busy');
            fetch(more.getAttribute('data-more'), { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
                .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.text(); })
                .then(function (html) {
                    stop();
                    var tpl = document.createElement('template');
                    tpl.innerHTML = html;
                    var next = tpl.content.querySelector('.more');
                    $$('.fav img', tpl.content).forEach(function (img) {
                        img.addEventListener('load', function () { if (img.naturalWidth > 1) img.classList.add('is-loaded'); });
                    });
                    if (more.hasAttribute('data-more-grid')) {
                        // Bilder: neue Kacheln ins Raster, der Rest (nächste Zugzone) ans Ende
                        var g = $('[data-grid]'), tiles = $$('.grid__item', tpl.content);
                        tiles.forEach(function (t) { g.appendChild(t); });
                        sizeTiles(tiles);
                    }
                    more.replaceWith(tpl.content);
                    if (next) initMore(next);
                })
                .catch(function () {
                    busy = false;
                    reset();
                    more.classList.remove('is-busy');
                    more.classList.add('is-error');
                });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('wheel', onWheel, { passive: true });
        window.addEventListener('touchstart', onTouchStart, { passive: true });
        window.addEventListener('touchmove', onTouchMove, { passive: true });
        window.addEventListener('touchend', onTouchEnd, { passive: true });
        window.addEventListener('keydown', onKey);
        if (btn) btn.addEventListener('click', function (ev) { if (!plainClick(ev)) return; ev.preventDefault(); load(); });
        onScroll();
    }
    initMore($('.more[data-more]'));

    /* ---------- Bilder: Raster mit natürlichen Seitenverhältnissen, Vorschau im Großformat ---------- */
    var grid = $('[data-grid]');
    if (grid) {
        var tiles = $$('.grid__item', grid);
        sizeTiles(tiles);

        var lb = $('[data-lightbox]');
        var cur = -1;
        var img = $('[data-lb-img]', lb);
        var fullBtn = $('[data-lb-full]', lb);
        var prevBtn = $('[data-lb-prev]', lb);
        var nextBtn = $('[data-lb-next]', lb);
        var fsApi = lb.requestFullscreen ? 'std' : (lb.webkitRequestFullscreen ? 'webkit' : '');

        function show(i) {
            tiles = $$('.grid__item', grid);
            if (i < 0 || i >= tiles.length) return;
            cur = i;
            var a = tiles[i];
            img.src = a.getAttribute('data-img');
            img.alt = a.getAttribute('data-title');
            $('[data-lb-title]', lb).textContent = a.getAttribute('data-title');
            $('[data-lb-host]', lb).textContent = a.getAttribute('data-host');
            $('[data-lb-size]', lb).textContent = a.getAttribute('data-size');
            $('[data-lb-cap]', lb).textContent = [a.getAttribute('data-host'), a.getAttribute('data-title')].filter(Boolean).join(' · ');
            $('[data-lb-count]', lb).textContent = (i + 1) + ' / ' + tiles.length;
            $('[data-lb-page]', lb).href = a.getAttribute('data-page') || a.href;
            // Server-Großansicht (ohne JavaScript geöffnet): deren großes Bild wird hier nicht mehr gebraucht
            var hiOld = $('[data-lb-hi]', lb);
            if (hiOld && hiOld.parentNode) hiOld.parentNode.removeChild(hiOld);
            prevBtn.hidden = i <= 0;
            nextBtn.hidden = i >= tiles.length - 1;
            var src = a.getAttribute('data-src');
            var srcA = $('[data-lb-src]', lb);
            srcA.hidden = !src;
            if (src) srcA.href = src;
            lb.hidden = false;
            body.style.overflow = 'hidden';
            themeDark(true);
            // Volle Auflösung über den eigenen Server nachladen, bis dahin bleibt das Vorschaubild stehen
            var full = a.getAttribute('data-full');
            lb.classList.remove('is-sharp');
            if (full) {
                var hi = new Image();
                hi.onload = function () { if (cur === i && hi.naturalWidth > 1) { img.src = full; lb.classList.add('is-sharp'); } };
                hi.src = full;
            }
            wake();
            if (playing) slideNext();
        }
        /* Diashow: läuft nur, wenn man sie startet. Nach dem letzten Bild geht es von vorne los. Sekunden werden im Browser gemerkt. */
        var playBtn = $('[data-lb-play]', lb), secsSel = $('[data-lb-secs]', lb), slideT = 0, playing = false;
        var store = function (k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } };
        if (playBtn && secsSel) {
            playBtn.hidden = false;
            $('[data-lb-secs-wrap]', lb).hidden = false;
            secsSel.value = store('sm.slide') || '5';
            if (!secsSel.value) secsSel.value = '5';
            secsSel.addEventListener('change', function () { store('sm.slide', secsSel.value); if (playing) slideNext(true); });
            playBtn.addEventListener('click', function () { slide(!playing); });
        }
        function slideNext(restart) {
            clearTimeout(slideT);
            if (!playing) return;
            slideT = setTimeout(function () {
                var n = $$('.grid__item', grid).length;
                if (!n || lb.hidden) { slide(false); return; }
                show(cur + 1 < n ? cur + 1 : 0);
            }, (parseInt(secsSel.value, 10) || 5) * 1000);
        }
        function slide(on) {
            playing = !!on && !!playBtn;
            if (!playBtn) return;
            lb.classList.toggle('is-playing', playing);
            playBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
            playBtn.setAttribute('aria-label', playBtn.getAttribute(playing ? 'data-label-off' : 'data-label-on'));
            playBtn.title = playBtn.getAttribute('aria-label');
            if (playing) slideNext(); else clearTimeout(slideT);
        }

        function hide() {
            slide(false);
            if (isFull()) exitFull();
            lb.hidden = true;
            body.style.overflow = '';
            themeDark(false);
            // War die Großansicht über die Adresse (?view=…) geöffnet: zurück zur Rasteradresse
            if (/[?&]view=/.test(location.search) && window.history && history.replaceState) history.replaceState(null, '', lb.getAttribute('data-grid-url'));
            if (tiles[cur]) tiles[cur].focus();
        }

        /* Statusleiste schwarz, solange die Vorschau offen ist: Als App auf dem Startbildschirm (iPad, iPhone) färbt das System
           den Streifen oben nach theme-color. Sonst bleibt dort beim Vollbild ein grauer oder weißer Rand. */
        function themeDark(on) {
            $$('meta[name="theme-color"]').forEach(function (m) {
                if (!m.hasAttribute('data-orig')) m.setAttribute('data-orig', m.getAttribute('content'));
                m.setAttribute('content', on ? '#000000' : m.getAttribute('data-orig'));
            });
        }

        /* Vollbild: Fullscreen-API des Browsers, ohne sie (iPhone, App auf dem Startbildschirm) ein randloses Ersatz-Vollbild per CSS */
        function nativeEl() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
        function isFull() { return lb.classList.contains('is-full'); }
        function sync() {
            var on = nativeEl() === lb || lb.classList.contains('is-pseudo');
            lb.classList.toggle('is-full', on);
            fullBtn.setAttribute('aria-label', fullBtn.getAttribute(on ? 'data-label-off' : 'data-label-on'));
            fullBtn.title = fullBtn.getAttribute('aria-label');
            wake();
        }
        function pseudo(on) { lb.classList.toggle('is-pseudo', on); sync(); }
        function enterFull() {
            if (fsApi === 'std') {
                var pr = lb.requestFullscreen({ navigationUI: 'hide' });
                if (pr && pr.catch) pr.catch(function () { pseudo(true); });
            } else if (fsApi === 'webkit') {
                lb.webkitRequestFullscreen();
                // Manche iPads melden die Funktion, tun als App vom Startbildschirm aber nichts: dann das Ersatz-Vollbild
                setTimeout(function () { if (nativeEl() !== lb && !isFull()) pseudo(true); }, 400);
            } else {
                pseudo(true);
            }
        }
        function exitFull() {
            if (nativeEl() === lb) {
                if (document.exitFullscreen) document.exitFullscreen(); else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
            }
            pseudo(false);
        }
        function toggleFull() { if (isFull()) exitFull(); else enterFull(); }
        document.addEventListener('fullscreenchange', sync);
        document.addEventListener('webkitfullscreenchange', sync);

        // Im Vollbild verschwinden Knöpfe und Mauszeiger nach kurzer Ruhe und kommen bei Bewegung wieder
        var idleT = 0;
        function wake() {
            lb.classList.remove('is-idle');
            clearTimeout(idleT);
            if (isFull()) idleT = setTimeout(function () { lb.classList.add('is-idle'); }, 2200);
        }
        lb.addEventListener('mousemove', wake);

        // Wischen zum Blättern
        var tx = null, ty = 0;
        lb.addEventListener('touchstart', function (ev) {
            wake();
            if (ev.touches.length === 1) { tx = ev.touches[0].clientX; ty = ev.touches[0].clientY; }
        }, { passive: true });
        lb.addEventListener('touchend', function (ev) {
            if (tx === null || !ev.changedTouches.length) return;
            var dx = ev.changedTouches[0].clientX - tx, dy = ev.changedTouches[0].clientY - ty;
            tx = null;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(cur + (dx < 0 ? 1 : -1));
        }, { passive: true });

        grid.addEventListener('click', function (ev) {
            var a = ev.target.closest('.grid__item');
            if (!a || !plainClick(ev)) return;
            ev.preventDefault();
            show($$('.grid__item', grid).indexOf(a));
        });
        $('[data-lb-close]', lb).addEventListener('click', function (ev) { ev.preventDefault(); hide(); });
        fullBtn.addEventListener('click', toggleFull);
        prevBtn.addEventListener('click', function (ev) { ev.preventDefault(); show(cur - 1); });
        nextBtn.addEventListener('click', function (ev) { ev.preventDefault(); show(cur + 1); });
        lb.addEventListener('click', function (ev) { if (ev.target === lb) hide(); });
        img.addEventListener('dblclick', toggleFull);
        // Schon vom Server geöffnet (?view=N): mit dem passenden Bild weitermachen, Wischen und Pfeiltasten gehen dann auch
        if (lb.hasAttribute('data-view')) {
            var vt = $$('.grid__item', grid).filter(function (t) { return t.getAttribute('data-i') === lb.getAttribute('data-view'); })[0];
            if (vt) show($$('.grid__item', grid).indexOf(vt));
        }
        document.addEventListener('keydown', function (ev) {
            if (lb.hidden || ev.ctrlKey || ev.metaKey || ev.altKey || isTyping(ev.target)) return;
            if (document.querySelector('.overlay:not([hidden])')) return;
            if (ev.key === 'Escape') { ev.preventDefault(); if (isFull()) exitFull(); else hide(); }
            else if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(cur - 1); }
            else if (ev.key === 'ArrowRight') { ev.preventDefault(); show(cur + 1); }
            else if (ev.key === 'f' || ev.key === 'F') { ev.preventDefault(); toggleFull(); }
            else if (ev.key === 's' || ev.key === 'S') { ev.preventDefault(); slide(!playing); }
        });
    }

    /* ==================== 1.2: Kopieren, Overlays, Tastatur, Werkzeuge ==================== */

    function isTyping(t) {
        return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
    }
    function copyText(text, btn) {
        function done() {
            if (!btn) return;
            var old = btn.textContent;
            btn.textContent = btn.getAttribute('data-done') || '✓';
            setTimeout(function () { btn.textContent = old; }, 1400);
        }
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); }); }
        else { fallbackCopy(text); done(); }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        ta.remove();
    }
    // Teilen über das Teilen-Menü des Geräts (Handy, iPad, Mac), nur wo der Browser es kann
    if (navigator.share) {
        var showShare = function (root) { $$('[data-share]', root).forEach(function (b) { b.hidden = false; }); };
        showShare(document);
        new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) showShare(n); }); }); })
            .observe(document.body, { childList: true, subtree: true });
        document.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-share]');
            if (!b) return;
            ev.preventDefault();
            navigator.share({ url: b.getAttribute('data-share'), title: b.getAttribute('data-share-title') || undefined }).catch(function () {});
        });
    }
    // Kopier-Knöpfe (auch in nachgeladenen Treffern)
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-copy-text]');
        if (b) { ev.preventDefault(); copyText(b.getAttribute('data-copy-text'), b); }
    });

    // Warnhinweis „Guthaben knapp“: für heute ausblenden
    $$('[data-warn-off]').forEach(function (b) {
        b.addEventListener('click', function () {
            var end = new Date(); end.setHours(23, 59, 59, 0);
            document.cookie = 'sm_warn_off=1; path=/; max-age=' + Math.max(60, Math.round((end - new Date()) / 1000)) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            var bar = b.closest('[data-warnbar]');
            if (bar) bar.remove();
        });
    });

    /* ---------- Overlays: Tastenkürzel, QR-Code ---------- */
    var lastFocus = null;
    function openOverlay(o) {
        if (!o) return;
        lastFocus = document.activeElement;
        o.hidden = false;
        body.classList.add('has-overlay');
        var c = $('[data-overlay-close]', o);
        if (c) c.focus();
    }
    function closeOverlays() {
        var any = false;
        $$('.overlay:not([hidden])').forEach(function (o) { o.hidden = true; any = true; });
        body.classList.remove('has-overlay');
        if (any && lastFocus && lastFocus.focus) lastFocus.focus();
        return any;
    }
    $$('.overlay').forEach(function (o) {
        o.addEventListener('click', function (ev) { if (ev.target === o || ev.target.closest('[data-overlay-close]')) closeOverlays(); });
    });
    $$('[data-keys-open]').forEach(function (b) { b.addEventListener('click', function () { openOverlay($('[data-keys]')); }); });
    var qrDlg = $('[data-qr-dialog]');
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-qr]');
        if (!b || !qrDlg) return;
        ev.preventDefault();
        $('[data-qr-img]', qrDlg).src = b.getAttribute('data-qr-src');
        $('[data-qr-img]', qrDlg).alt = b.getAttribute('data-qr');
        $('[data-qr-text]', qrDlg).textContent = b.getAttribute('data-qr');
        var d = b.closest('details'); if (d) d.removeAttribute('open');
        openOverlay(qrDlg);
    });

    /* ---------- Tastatur: Treffer mit ↑↓ / J K wählen, Enter öffnet, / sucht, ? hilft, Esc schließt ---------- */
    var cursor = -1;
    function results() { return $$('[data-res]').filter(function (el) { return el.offsetParent !== null; }); }
    function mark(i) {
        var list = results();
        if (!list.length) return;
        cursor = Math.max(0, Math.min(list.length - 1, i));
        list.forEach(function (el, n) { el.classList.toggle('is-kbd', n === cursor); });
        var el = list[cursor];
        var r = el.getBoundingClientRect();
        if (r.top < 90 || r.bottom > window.innerHeight - 20) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
    function linkOf(el) { return el.matches('a') ? el : $('[data-res-link]', el) || $('a', el); }
    document.addEventListener('keydown', function (ev) {
        if (ev.defaultPrevented || ev.altKey) return;
        var k = ev.key;
        if (k === 'Escape') {
            var acted = closeOverlays();
            $$('details[open]').forEach(function (d) { if (d.matches('.menu, .tools, .pop, .resmenu')) { d.removeAttribute('open'); acted = true; } });
            if (!acted && cursor >= 0 && !isTyping(ev.target)) { results().forEach(function (el) { el.classList.remove('is-kbd'); }); cursor = -1; }
            return;
        }
        if (isTyping(ev.target)) return;
        var lbOpen = document.querySelector('[data-lightbox]:not([hidden])');
        var ovOpen = document.querySelector('.overlay:not([hidden])');
        if (k === '?' ) { ev.preventDefault(); if (ovOpen) closeOverlays(); else openOverlay($('[data-keys]')); return; }
        if (ovOpen || lbOpen || ev.ctrlKey && k !== 'Enter' || ev.metaKey && k !== 'Enter') return;
        if (k === '/') {
            var input = $('[data-tools-find]') || $('form[data-search] input[name="q"]');
            if (input) { ev.preventDefault(); input.focus(); input.select(); }
            return;
        }
        if (!results().length) return;
        if (k === 'ArrowDown' || k === 'j' || k === 'J') { ev.preventDefault(); mark(cursor + 1); }
        else if (k === 'ArrowUp' || k === 'k' || k === 'K') { ev.preventDefault(); mark(cursor - 1); }
        else if (k === 'Enter' && cursor >= 0) {
            var el = results()[cursor];
            if (!el) return;
            ev.preventDefault();
            if (el.classList.contains('grid__item') && !(ev.ctrlKey || ev.metaKey)) { el.click(); return; }
            var a = linkOf(el);
            if (!a) return;
            if (ev.ctrlKey || ev.metaKey) window.open(a.href, '_blank', 'noopener,noreferrer');
            else { forceNewTab(a); loading(a.target !== '_blank'); a.click(); }
        }
        else if (k === 'ArrowRight' || k === 'ArrowLeft') {
            var p = $(k === 'ArrowRight' ? '.pager a[rel="next"]' : '.pager a[rel="prev"]');
            if (p) { ev.preventDefault(); loading(true); location.href = p.href; }
        }
    });
    // Mit der Maus gewählt: dort weitermachen
    document.addEventListener('focusin', function (ev) {
        var r = ev.target.closest && ev.target.closest('[data-res]');
        if (r) { var i = results().indexOf(r); if (i >= 0) { cursor = i; results().forEach(function (el, n) { el.classList.toggle('is-kbd', n === i); }); } }
    });

    /* ---------- Zufall im Browser (Web Crypto) ---------- */
    function rnd(n) {
        if (n <= 1) return 0;
        var max = Math.floor(4294967296 / n) * n, buf = new Uint32Array(1), x;
        do { crypto.getRandomValues(buf); x = buf[0]; } while (x >= max);
        return x % n;
    }
    $$('[data-reroll]').forEach(function (b) {
        b.addEventListener('click', function () {
            var box = b.closest('.ia');
            var kind = b.getAttribute('data-reroll');
            if (kind === 'coin') { var c = $('[data-coin]', box); c.textContent = rnd(2) ? c.getAttribute('data-heads') : c.getAttribute('data-tails'); c.classList.remove('is-anim'); void c.offsetWidth; c.classList.add('is-anim'); }
            if (kind === 'random') { var r = $('[data-random]', box), mi = parseInt(r.getAttribute('data-min'), 10), ma = parseInt(r.getAttribute('data-max'), 10); r.textContent = mi + rnd(ma - mi + 1); }
            if (kind === 'dice') {
                var d = $('[data-dice]', box), n = parseInt(d.getAttribute('data-n'), 10), f = parseInt(d.getAttribute('data-faces'), 10), sum = 0;
                d.innerHTML = '';
                for (var i = 0; i < n; i++) { var v = 1 + rnd(f); sum += v; var s = document.createElement('span'); s.className = 'die is-anim'; s.textContent = v; d.appendChild(s); }
                var sm = $('[data-dice-sum]', box); if (sm) sm.textContent = sum;
            }
        });
    });

    /* ---------- Passwort-Generator ---------- */
    var WORDS = root.lang === 'de' ? 'apfel abend acker adler ampel anker arena asche atlas auge bach bahn balkon ball band bank bart bauer baum beere berg besen biene birne blatt blitz blume boden boot brett brief brille brot bruder brunnen buch burg busch butter dach dame dampf decke delfin dorf drache dusche ecke eiche eimer eisen elch engel ente erbse erde esel eule fabel fackel faden falke farbe feder fee feld felsen fenster ferne fest feuer figur filz fisch flagge flasche fluss flur flut fohlen forelle frosch frucht fuchs funke gabel gans garten gast geige geld gipfel glas glocke gold gras gurke hafen hagel hahn hammer hand harfe hase haus hecke heft held hemd herbst herd herz heu himmel hirsch hof honig horn hose hund hut igel insel jacke jagd juwel kaffee kahn kakao kamel kamm kanal kanne kapelle karte kater katze kegel keller kerze kessel kiefer kino kirsche kiste klee knopf koch koffer kohle komet korb kraut krone kuchen kugel kurve lager lampe land laterne laub leder leiter lerche licht linde loewe luft mantel marmor maus meer mehl melone milch mond moos motor muehle muschel nadel nagel nebel nest netz nudel ofen oliven onkel orgel paket palme panda papier park pfeil pferd pflaume pilot pinsel pirat planet platz pony post puppe quelle rabe rad rahmen rakete rasen regen reh reise rinde ring rose rubin ruder saal sack saft salz sand sattel schaf schal schatz schiff schild schloss schnee schrank schuh schwan see segel seife seil sonne spiegel spinne stadt stall stein stern stiefel strand strauss stuhl sturm tafel tal tanne tasse taube teich teller tiger tisch tomate topf traum treppe tulpe turm ufer uhr vase vogel wagen wald wal wand wasser welle wiese wind winter wolke wolle wurm zange zaun zebra zelt ziege zimmer zitrone zucker zug zwerg'.split(' ') : 'acorn anchor apple arrow autumn badge bakery balloon bamboo banjo barrel basket beacon beaver bench berry bicycle biscuit blanket blossom bonfire bottle breeze brick bridge brook bubble bucket buffalo butter cabin cactus camel candle canoe canyon carpet carrot castle cedar cello chalk cherry chimney cinnamon circus cliff clover coconut comet copper coral cotton cradle crayon cricket crystal cupboard dagger daisy desert dolphin donkey dragon drum eagle easel ember engine falcon feather fern ferry fiddle finch flame flute forest fossil fountain fox galaxy garden garlic gazelle geyser ginger glacier globe goose granite grape gravel guitar hammer harbor harvest hazel hedge helmet heron hill honey horizon hornet iceberg igloo island ivory jacket jaguar jelly jungle kayak kettle kitten koala ladder lagoon lantern lemon lettuce library lily lizard lobster locket magnet mango maple marble meadow melon meteor mirror mitten monkey moose mountain muffin mushroom napkin needle nest nickel noodle novel oak oasis ocean olive onion orange orchard otter owl paddle palace panda paper parrot peach pebble pencil pepper piano pickle pigeon pillow pine planet plum pocket pony poppy potato prism puddle pumpkin puzzle quarry quill rabbit raccoon radish rainbow raven reef ribbon river robin rocket saddle salmon satchel saucer scarf seashell shadow sheep shelter shovel silver sketch sled snail spider sponge spruce squirrel stable stamp statue stone storm stream sunset swallow table tablet teapot thimble thunder tiger timber toast tomato tower trail tulip tunnel turtle umbrella valley velvet violin volcano wagon walnut walrus wander whale wheat willow window winter wizard yarn zebra zipper'.split(' ');
    $$('[data-pwgen]').forEach(function (g) {
        if (!window.crypto || !crypto.getRandomValues) return;
        var out = $('[data-pw-out]', g), info = $('[data-pw-info]', g), bar = $('[data-pw-bar]', g);
        var len = $('[data-pw-len]', g), lenOut = $('[data-pw-len-out]', g), words = $('[data-pw-words]', g), wordsOut = $('[data-pw-words-out]', g);
        var SETS = { lower: 'abcdefghijklmnopqrstuvwxyz', upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', digits: '0123456789', symbols: '!#$%&*+-=?@^_~.,:;()[]{}' };
        function mode() { var m = $('[data-pw-mode]:checked', g); return m ? m.value : 'pw'; }
        function gen() {
            var pw = '', bits = 0;
            $('[data-pw-opts-pw]', g).hidden = mode() !== 'pw';
            $('[data-pw-opts-phrase]', g).hidden = mode() === 'pw';
            if (mode() === 'pw') {
                var noAmb = $('[data-pw-noambig]', g).checked, pools = [];
                $$('[data-pw-set]', g).forEach(function (c) {
                    if (!c.checked) return;
                    var set = SETS[c.getAttribute('data-pw-set')];
                    if (noAmb) set = set.replace(/[lI1O0o|]/g, '');
                    pools.push(set);
                });
                if (!pools.length) { pools = [SETS.lower]; $('[data-pw-set="lower"]', g).checked = true; }
                var all = pools.join(''), n = parseInt(len.value, 10), chars = [];
                lenOut.textContent = n;
                // Aus jeder gewählten Gruppe mindestens ein Zeichen, dann mischen
                pools.forEach(function (p) { if (chars.length < n) chars.push(p[rnd(p.length)]); });
                while (chars.length < n) chars.push(all[rnd(all.length)]);
                for (var i = chars.length - 1; i > 0; i--) { var j = rnd(i + 1), t = chars[i]; chars[i] = chars[j]; chars[j] = t; }
                pw = chars.join('');
                bits = n * Math.log2(all.length);
            } else {
                var w = parseInt(words.value, 10), cap = $('[data-pw-cap]', g).checked, num = $('[data-pw-num]', g).checked, sep = $('[data-pw-sep]', g).value, parts = [];
                wordsOut.textContent = w;
                for (var k = 0; k < w; k++) { var word = WORDS[rnd(WORDS.length)]; parts.push(cap ? word.charAt(0).toUpperCase() + word.slice(1) : word); }
                if (num) parts.splice(rnd(parts.length + 1), 0, String(rnd(100)));
                pw = parts.join(sep);
                bits = w * Math.log2(WORDS.length) + (num ? Math.log2(100) + Math.log2(w + 1) : 0);
            }
            out.value = pw;
            var b = Math.round(bits);
            var lvl = b < 50 ? 'weak' : (b < 80 ? 'ok' : 'strong');
            info.textContent = '≈ ' + b + ' ' + info.getAttribute('data-l-bits') + ' · ' + info.getAttribute('data-l-' + lvl);
            bar.style.width = Math.min(100, b / 1.28) + '%';
            bar.className = 'is-' + lvl;
        }
        g.addEventListener('input', gen);
        g.addEventListener('change', gen);
        $('[data-pw-new]', g).addEventListener('click', gen);
        $('[data-pw-copy]', g).addEventListener('click', function (ev) { copyText(out.value, ev.currentTarget); });
        out.addEventListener('focus', function () { out.select(); });
        gen();
    });

    /* ---------- Uhr, Countdown, Timer, Stoppuhr ---------- */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    $$('[data-clock]').forEach(function (c) {
        setInterval(function () { var d = new Date(); c.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }, 1000);
    });
    $$('[data-countdown]').forEach(function (c) {
        var ts = parseInt(c.getAttribute('data-ts'), 10) * 1000;
        function tick() {
            var s = Math.max(0, Math.floor((ts - Date.now()) / 1000));
            if (!s) { c.textContent = c.getAttribute('data-l-done'); return; }
            c.textContent = Math.floor(s / 86400) + ' ' + c.getAttribute('data-l-days') + ' ' + pad(Math.floor(s % 86400 / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
        }
        tick(); setInterval(tick, 1000);
    });
    /* Töne: echte Audiodateien vom eigenen Server. Browser erlauben Ton nur nach einem Fingertipp, darum wird er beim Start
       (oder beim Testen) freigeschaltet. Ohne Datei bleibt als Rückfall ein erzeugter Piepton. */
    var actx = null, player = null;
    function unlockSound(url) {
        try {
            var A = window.AudioContext || window.webkitAudioContext;
            if (A && !actx) actx = new A();
            if (actx && actx.state === 'suspended') actx.resume();
        } catch (e) {}
        if (!player && window.Audio) player = new Audio();
        if (player && url) {
            if (player.getAttribute('data-src') !== url) { player.src = url; player.setAttribute('data-src', url); player.load(); }
            var was = player.muted;
            player.muted = true;
            var p = player.play();
            var undo = function () { player.pause(); player.currentTime = 0; player.muted = was; };
            if (p && p.then) p.then(undo, function () { player.muted = was; }); else undo();
        }
    }
    function beep() {
        try {
            if (!actx) return;
            [0, 0.35, 0.7].forEach(function (t) {
                var o = actx.createOscillator(), gn = actx.createGain();
                o.frequency.value = 880; o.connect(gn); gn.connect(actx.destination);
                gn.gain.setValueAtTime(0.25, actx.currentTime + t); gn.gain.exponentialRampToValueAtTime(0.001, actx.currentTime + t + 0.3);
                o.start(actx.currentTime + t); o.stop(actx.currentTime + t + 0.3);
            });
        } catch (e) {}
    }
    function playOnce(url) {
        if (!url) return;
        if (player) {
            player.muted = false;
            player.currentTime = 0;
            var p = player.play();
            if (p && p.catch) p.catch(beep);
        } else beep();
    }
    $$('[data-timer]').forEach(function (tm) {
        var total = parseInt(tm.getAttribute('data-sec'), 10) || 0, watch = total === 0;
        var show = $('[data-timer-show]', tm), go = $('[data-timer-start]', tm), reset = $('[data-timer-reset]', tm);
        var sounds = JSON.parse(tm.getAttribute('data-sounds') || '{}'), pick = $('[data-sound-pick]', tm), mute = $('[data-timer-mute]', tm);
        var ringIv = 0, ringEnd = 0;
        if (pick) {
            $('[data-timer-sound]', tm).hidden = false;
            try { var saved = localStorage.getItem('sm_sound'); if (saved !== null) pick.value = saved; } catch (e) {}
            pick.addEventListener('change', function () { try { localStorage.setItem('sm_sound', pick.value); } catch (e) {} unlockSound(sounds[pick.value]); });
            $('[data-sound-test]', tm).addEventListener('click', function () { unlockSound(sounds[pick.value]); setTimeout(function () { playOnce(sounds[pick.value]); }, 60); });
        }
        var soundUrl = function () { return pick ? sounds[pick.value] || '' : ''; };
        function stopRing() { clearInterval(ringIv); ringIv = 0; if (player) player.pause(); if (mute) mute.hidden = true; }
        function ring() {
            var url = soundUrl();
            if (!url) return;
            stopRing();
            ringEnd = Date.now() + 60000;
            playOnce(url);
            ringIv = setInterval(function () { if (Date.now() > ringEnd) { stopRing(); return; } playOnce(url); }, 3500);
            if (mute) mute.hidden = false;
        }
        if (mute) mute.addEventListener('click', stopRing);
        var left = total * 1000, run = false, last = 0, iv = 0, title = document.title;
        function fmt(ms) {
            var s = Math.round(ms / 1000), h = Math.floor(s / 3600);
            return (h ? h + ':' : '') + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60) + (watch ? '.' + Math.floor(ms % 1000 / 100) : '');
        }
        function draw() { show.textContent = fmt(watch ? left : Math.max(0, left)); if (run) document.title = fmt(left) + ' · ' + title; }
        function step() {
            var now = Date.now(); left += watch ? now - last : -(now - last); last = now;
            if (!watch && left <= 0) {
                left = 0; stop(); draw(); tm.classList.add('is-done'); show.textContent = tm.getAttribute('data-done'); document.title = '⏰ ' + title; ring();
                if ('vibrate' in navigator) navigator.vibrate([300, 150, 300]);
                return;
            }
            draw();
        }
        function stop() { run = false; clearInterval(iv); go.textContent = go.getAttribute('data-label-go'); }
        go.addEventListener('click', function () {
            stopRing();
            if (run) { stop(); document.title = title; return; }
            if (!watch) unlockSound(soundUrl());   // jetzt, beim Fingertipp, damit der Ton am Ende spielen darf
            if (!watch && left <= 0) left = total * 1000;
            tm.classList.remove('is-done');
            run = true; last = Date.now(); iv = setInterval(step, watch ? 100 : 250); go.textContent = go.getAttribute('data-label-pause');
        });
        reset.addEventListener('click', function () { stopRing(); stop(); left = watch ? 0 : total * 1000; tm.classList.remove('is-done'); document.title = title; draw(); });
    });

    /* ---------- QR-Generator unter Werkzeuge: Vorschau beim Tippen ---------- */
    var qrIn = $('[data-qr-input]');
    if (qrIn) {
        var qrT = 0;
        qrIn.addEventListener('input', function () {
            clearTimeout(qrT);
            qrT = setTimeout(function () {
                var v = qrIn.value.trim(), form = qrIn.form, prev = $('[data-qr-preview]');
                if (!v) return;
                if (!prev) { prev = document.createElement('div'); prev.className = 'ia__qr'; prev.setAttribute('data-qr-preview', ''); prev.innerHTML = '<img alt="" width="240" height="240">'; form.after(prev); }
                $('img', prev).src = form.getAttribute('action') + '?a=qr&d=' + encodeURIComponent(v);
            }, 350);
        });
    }
    /* ---------- Einstellungen → Aussehen: Live-Vorschau und Farbmischer (aus „Kaufen“) ----------
       Rechnet die Farben genauso wie der Server (look.php), damit die Vorschau zeigt, was nach dem Speichern kommt. */
    var lookForm = $('[data-look]');
    if (lookForm) (function () {
        var PALS = JSON.parse(lookForm.getAttribute('data-pals') || '{}');
        var NAMES = JSON.parse(lookForm.getAttribute('data-names') || '{}');
        var prev = $('[data-look-prev]', lookForm);
        var hex2rgb = function (x) { return [1, 3, 5].map(function (i) { return parseInt(x.slice(i, i + 2), 16); }); };
        var rgb2hex = function (a) { return '#' + a.map(function (v) { return ('0' + Math.round(Math.max(0, Math.min(255, v))).toString(16)).slice(-2); }).join(''); };
        var mix = function (a, b, t) { var x = hex2rgb(a), y = hex2rgb(b); return rgb2hex(x.map(function (v, i) { return v + (y[i] - v) * t; })); };
        var lum = function (x) { var c = hex2rgb(x).map(function (v) { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; };
        var contrast = function (a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
        function hsl2hex(hsl) {
            var hh = hsl[0], s = hsl[1] / 100, l = hsl[2] / 100, a = s * Math.min(l, 1 - l);
            var f = function (n) { var k = (n + hh / 30) % 12; return 255 * (l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1))); };
            return rgb2hex([f(0), f(8), f(4)]);
        }
        function hex2hsl(x) {
            var c = hex2rgb(x).map(function (v) { return v / 255; }), r = c[0], g = c[1], b = c[2];
            var mx = Math.max(r, g, b), mn = Math.min(r, g, b), d = mx - mn, l = (mx + mn) / 2, hh = 0;
            if (d) hh = ((mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4) * 60 + 360) % 360;
            return [Math.round(hh), Math.round((d ? d / (1 - Math.abs(2 * l - 1)) : 0) * 100), Math.round(l * 100)];
        }
        var shift = function (x, deg) { var h = hex2hsl(x); h[0] = (h[0] + deg + 360) % 360; return hsl2hex(h); };
        function readable(c, bg, toward, goal) {
            for (var t = 0; t <= 1.0001; t += .05) { var x = mix(c, toward, t); if (contrast(x, bg) >= (goal || 4.5)) return x; }
            return toward;
        }
        /** Wie smLookVars() in look.php */
        function vars(c) {
            var link = c.link || readable(c.acc, c.card, c.text);
            return {
                '--bg': c.bg, '--bg-2': mix(c.bg, c.text, .03), '--surface': c.card, '--surface-2': mix(c.card, c.text, .06),
                '--line': mix(c.text, c.bg, .82), '--line-2': mix(c.text, c.bg, .91), '--text': c.text, '--text-2': c.mut || mix(c.text, c.bg, .3),
                '--text-3': readable(mix(c.text, c.bg, .45), c.bg, c.text, 3.2), '--link': link, '--accent': c.acc, '--accent-2': shift(c.acc, 35)
            };
        }
        // Welche Seite die Vorschau zeigt: zuletzt bearbeitet, am Anfang die, die gerade zu sehen ist
        var theme = root.getAttribute('data-theme');
        var side = theme === 'dark' || (theme === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        var sec = function (s) { return $('[data-side="' + s + '"]', lookForm); };
        var key = function (s) { return s === 'dark' ? 'pd' : 'pl'; };
        function ownCols(s) {
            var c = {};
            $$('input[type=color]', sec(s)).forEach(function (i) {
                var k = i.getAttribute('data-part'), box = i.parentNode.querySelector('input[type=checkbox]');
                if (!box || box.checked) c[k] = i.value.toLowerCase();
            });
            return c;
        }
        function sideCols(s) {
            var r = lookForm.querySelector('input[name="' + key(s) + '"]:checked'), id = r ? r.value : 'standard';
            if (id === 'eigen') return ownCols(s);
            var p = PALS[s][id] || PALS[s].standard, c = { bg: p.bg, card: p.card, text: p.text, acc: p.acc };
            if (p.link) c.link = p.link;
            return c;
        }
        function paint() {
            var c = sideCols(side), v = vars(c);
            Object.keys(v).forEach(function (k) { prev.style.setProperty(k, v[k]); });
            // Musterkarte „Eigenes“ geht mit
            ['light', 'dark'].forEach(function (s) {
                var o = ownCols(s), sw = $('[data-sw-own]', sec(s));
                if (sw && o.bg) { sw.style.setProperty('--sw-bg', o.bg); sw.style.setProperty('--sw-text', o.text); sw.style.setProperty('--sw-acc', o.acc); }
            });
            // Lesbarkeit prüfen (nur was selbst gewählt ist)
            ['light', 'dark'].forEach(function (s) {
                var w = $('.mixer__warn', sec(s)), o = ownCols(s), bad = [];
                if (!w || !o.bg) return;
                if (contrast(o.text, o.bg) < 4.5 || contrast(o.text, o.card) < 4.5) bad.push(NAMES.text);
                if (o.link && contrast(o.link, o.card) < 3.5) bad.push(NAMES.link);
                if (o.mut && contrast(o.mut, o.bg) < 3) bad.push(NAMES.mut);
                w.textContent = lookForm.getAttribute('data-warn').replace('%s', bad.join(', '));
                w.hidden = !bad.length;
            });
            // Schrift, Größen, Abstände
            var font = lookForm.querySelector('input[name=font]:checked');
            var fspan = font && $('.fontpick--' + font.value + ' span', lookForm);
            prev.style.fontFamily = fspan && font.value !== 'system' ? getComputedStyle(fspan).fontFamily : '';
            var num = function (n) { var i = lookForm.querySelector('input[name=' + n + ']'); return i ? +i.value : 100; };
            prev.style.setProperty('--ft', num('ft') / 100);
            prev.style.setProperty('--fd', num('fd') / 100);
            prev.style.setProperty('--lh', num('lh') / 100);
            // „Alles zusammen“ wirkt auf der Seite über zoom; die Seite selbst ist schon mit dem gespeicherten Wert gezoomt
            prev.style.zoom = num('fs') / (+prev.getAttribute('data-zoom') || 100);
            $$('[data-side-btn]', lookForm).forEach(function (b) { var on = b.getAttribute('data-side-btn') === side; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
            prev.classList.toggle('is-dark', side === 'dark');
            var gap = lookForm.querySelector('input[name=gap]:checked');
            prev.style.setProperty('--gap', gap ? { compact: .55, normal: 1, airy: 1.45 }[gap.value] : 1);
            $$('output[data-out]', lookForm).forEach(function (o) {
                var k = o.getAttribute('data-out'), v = num(k);
                o.textContent = k === 'lh' ? (v / 100).toFixed(2).replace('.', ',') : v + ' %';
            });
        }
        // Farbmischer je Seite: ein Teil ist aktiv, die Regler und der Farbcode bearbeiten ihn
        ['light', 'dark'].forEach(function (s) {
            var box = sec(s);
            if (!box) return;
            var js = $('.mixer__js', box);
            if (js) js.hidden = false;
            var active = $('input[type=color][data-part=acc]', box);
            var sl = $$('input[data-sl]', box), hexIn = $('.mixer__hex', box);
            function chooseOwn() { var r = box.querySelector('input[value=eigen]'); if (r) r.checked = true; side = s; }
            function sync() {
                $$('.mixer__part', box).forEach(function (p) { p.classList.toggle('is-active', p.contains(active)); });
                var hsl = hex2hsl(active.value);
                sl.forEach(function (r) { r.value = hsl[+r.getAttribute('data-sl')]; });
                hexIn.value = active.value;
                var sat = $('.sl--sat input', box), lig = $('.sl--lig input', box);
                if (sat) sat.style.setProperty('--sl-bg', 'linear-gradient(90deg,' + hsl2hex([hsl[0], 0, hsl[2]]) + ',' + hsl2hex([hsl[0], 100, hsl[2]]) + ')');
                if (lig) lig.style.setProperty('--sl-bg', 'linear-gradient(90deg,#000,' + hsl2hex([hsl[0], hsl[1], 50]) + ',#fff)');
            }
            function changed(own) {
                var b = active.parentNode.querySelector('input[type=checkbox]');
                if (b && own) b.checked = true;
                chooseOwn();
                sync();
                paint();
            }
            $$('input[type=color]', box).forEach(function (i) {
                i.addEventListener('focus', function () { active = i; sync(); });
                i.addEventListener('click', function () { active = i; sync(); });
                i.addEventListener('input', function () { active = i; changed(true); });
            });
            $$('.mixer__part', box).forEach(function (p) {
                p.addEventListener('pointerdown', function () { active = $('input[type=color]', p); sync(); });
            });
            $$('input[type=checkbox]', box).forEach(function (c) { c.addEventListener('change', function () { chooseOwn(); paint(); }); });
            sl.forEach(function (r) {
                r.addEventListener('input', function () {
                    var hsl = sl.map(function (x) { return +x.value; });
                    active.value = hsl2hex(hsl);
                    changed(true);
                });
            });
            hexIn.addEventListener('change', function () {
                var v = hexIn.value.trim().toLowerCase().replace(/^#/, '');
                if (/^[0-9a-f]{3}$/.test(v)) v = v.replace(/./g, '$&$&');
                if (/^[0-9a-f]{6}$/.test(v)) { active.value = '#' + v; changed(true); } else sync();
            });
            $('.mixer__from', box).addEventListener('change', function (ev) {
                var v = ev.target.value.split(':'), p = PALS[v[0]] && PALS[v[0]][v[1]];
                ev.target.value = '';
                if (!p) return;
                ['bg', 'card', 'text', 'acc'].forEach(function (k) { $('input[data-part=' + k + ']', box).value = p[k]; });
                var lk = $('input[data-part=link]', box), lb = lk.parentNode.querySelector('input[type=checkbox]');
                if (p.link) { lk.value = p.link; lb.checked = true; } else lb.checked = false;
                $('input[data-part=mut]', box).parentNode.querySelector('input[type=checkbox]').checked = false;
                chooseOwn(); sync(); paint();
            });
            // Abstimmen: Titel/Links und Nebentext wieder automatisch aus Akzent und Schrift, lesbar auf den Flächen
            $('[data-match]', box).addEventListener('click', function () {
                var o = ownCols(s);
                $('input[data-part=link]', box).value = readable(o.acc, o.card, o.text);
                $('input[data-part=mut]', box).value = mix(o.text, o.bg, .3);
                $$('input[type=checkbox]', box).forEach(function (c) { c.checked = false; });
                chooseOwn(); sync(); paint();
            });
            box.addEventListener('change', function (ev) { if (ev.target.name === key(s)) { side = s; paint(); } });
            sync();
        });
        lookForm.addEventListener('input', function (ev) { if (ev.target.type === 'range' && !ev.target.hasAttribute('data-sl')) paint(); });
        lookForm.addEventListener('change', function (ev) {
            if (ev.target.name === 'mode' && ev.target.value !== 'auto') side = ev.target.value;
            if (['font', 'gap', 'width', 'mode'].indexOf(ev.target.name) >= 0) paint();
        });
        // Vorschau zwischen hell und dunkel umschalten, egal was gerade auf dem Gerät aktiv ist
        var sideSeg = $('[data-prev-side]', lookForm);
        if (sideSeg) {
            sideSeg.hidden = false;
            $$('[data-side-btn]', sideSeg).forEach(function (b) {
                b.addEventListener('click', function () { side = b.getAttribute('data-side-btn'); paint(); });
            });
        }
        // Wer im Bereich „Dunkles Design“ etwas anfasst, sieht die dunkle Vorschau
        ['light', 'dark'].forEach(function (s) {
            var box = sec(s);
            if (!box) return;
            box.addEventListener('pointerdown', function () { if (side !== s) { side = s; paint(); } });
            box.addEventListener('focusin', function () { if (side !== s) { side = s; paint(); } });
        });
        paint();
    })();
    /* ---------- Login per Token hinter dem # (…/?a=login#token=…): per POST schicken, damit er nie in einem Server-Protokoll steht ---------- */
    var tokForm = $('[data-token-login]');
    if (tokForm && /^#token=/.test(location.hash)) {
        var tok = decodeURIComponent(location.hash.slice(7));
        try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
        if (tok) { tokForm.elements.token.value = tok; tokForm.submit(); }
    }

    /* ---------- Drucken: jeder Knopf mit data-print (Lesemodus, Jahreskalender …) ---------- */
    $$('[data-print]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.body.classList.remove('print-area');   // normale Seite drucken, nicht die Druckfläche der QR-Werkzeuge
            window.print();
        });
    });

    /* ---------- Rezept im Lesemodus: Portionen umrechnen (Zutaten, Ausdruck und PDF rechnen mit) ---------- */
    $$('[data-yield]').forEach(function (facts) {
        var base = parseFloat(facts.getAttribute('data-yield')), cur = base;
        var ings = $$('[data-ing]');
        if (!base || !ings.length) return;
        var de = (document.documentElement.lang || 'de') !== 'en';
        var frac = { '½': 0.5, '⅓': 1 / 3, '⅔': 2 / 3, '¼': 0.25, '¾': 0.75, '⅛': 0.125 };
        var numRe = /^(\d+\s+\d+\/\d+|\d+\/\d+|\d+(?:[.,]\d+)?(?:\s*[½⅓⅔¼¾⅛])?|[½⅓⅔¼¾⅛])(\s*[-–]\s*(\d+(?:[.,]\d+)?))?/;
        function num(t) {
            t = t.trim();
            var m;
            if ((m = /^(\d+)\s+(\d+)\/(\d+)$/.exec(t))) return +m[1] + m[2] / m[3];
            if ((m = /^(\d+)\/(\d+)$/.exec(t))) return m[1] / m[2];
            if ((m = /^(\d+(?:[.,]\d+)?)?\s*([½⅓⅔¼¾⅛])$/.exec(t))) return (m[1] ? parseFloat(m[1].replace(',', '.')) : 0) + frac[m[2]];
            return parseFloat(t.replace(',', '.'));
        }
        function fmt(v) {
            if (v >= 20) v = Math.round(v);
            else if (v >= 3) v = Math.round(v * 2) / 2;
            else v = Math.round(v * 4) / 4;
            if (v === 0) v = 0.25;
            var i = Math.floor(v), r = v - i, sym = { 0.25: '¼', 0.5: '½', 0.75: '¾' }[r];
            if (sym) return (i ? i : '') + sym;
            return String(v).replace('.', de ? ',' : '.');
        }
        ings.forEach(function (li) { li.setAttribute('data-orig', li.textContent); });
        var factsOrig = facts.textContent;
        function apply() {
            var f = cur / base;
            ings.forEach(function (li) {
                var o = li.getAttribute('data-orig'), m = numRe.exec(o);
                if (!m || f === 1) { li.textContent = o; return; }
                var out = fmt(num(m[1]) * f) + (m[3] ? '–' + fmt(num(m[3]) * f) : '');
                li.textContent = out + o.slice(m[0].length);
            });
            facts.firstChild.textContent = factsOrig.replace(/\d+(?:[.,]\d+)?/, fmt(cur));
            val.textContent = fmt(cur);
            minus.disabled = cur <= 1;
        }
        var box = document.createElement('div');
        box.className = 'servings';
        box.innerHTML = '<span class="servings__label"></span><button type="button" class="iconbtn servings__btn">−</button><strong class="servings__val"></strong><button type="button" class="iconbtn servings__btn">+</button>';
        $('.servings__label', box).textContent = facts.getAttribute('data-label');
        var btns = $$('button', box), minus = btns[0], plus = btns[1], val = $('.servings__val', box);
        minus.setAttribute('aria-label', facts.getAttribute('data-minus'));
        plus.setAttribute('aria-label', facts.getAttribute('data-plus'));
        minus.addEventListener('click', function () { cur = Math.max(1, cur > base && cur - 1 < base ? base : cur - 1); apply(); });
        plus.addEventListener('click', function () { cur = cur < base && cur + 1 > base ? base : cur + 1; apply(); });
        facts.parentNode.insertBefore(box, facts.nextSibling);
        apply();
    });

    /* ---------- Lesemodus: Drucken und als PDF speichern (mit Vorschau, Bilder nach Wahl) ---------- */
    var readerDoc = $('[data-reader]');
    if (readerDoc) (function () {
        var data = JSON.parse(($('#reader-data') || {}).textContent || 'null');
        var panel = $('[data-pdfpanel]'), openBtn = $('[data-pdf-open]');
        if (!data || !panel || !openBtn || !window.HTMLCanvasElement) return;
        openBtn.hidden = false;
        var canvas = $('[data-pdf-canvas]', panel), pageLbl = $('[data-pdf-page]', panel);
        var imgs = {};       // Blocknummer -> { el, data, pw, ph, on }
        var page = 0, lay = null, ready = null;

        function bin(buf) {
            var u = new Uint8Array(buf), s = '';
            for (var i = 0; i < u.length; i += 8192) s += String.fromCharCode.apply(null, u.subarray(i, i + 8192));
            return s;
        }
        /** Bild als JPEG (höchstens 1400 Pixel breit). Kommt über den eigenen Server, die Leinwand bleibt also benutzbar. */
        function jpeg(el) {
            return new Promise(function (ok) {
                function go() {
                    if (!el.naturalWidth) return ok(null);
                    var sc = Math.min(1, 1400 / el.naturalWidth), c = document.createElement('canvas');
                    c.width = Math.round(el.naturalWidth * sc); c.height = Math.round(el.naturalHeight * sc);
                    var x = c.getContext('2d');
                    x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
                    try { x.drawImage(el, 0, 0, c.width, c.height); } catch (e) { return ok(null); }
                    c.toBlob(function (b) {
                        if (!b) return ok(null);
                        b.arrayBuffer().then(function (buf) { ok({ el: el, data: bin(buf), pw: c.width, ph: c.height, w: c.width, h: c.height }); }, function () { ok(null); });
                    }, 'image/jpeg', .85);
                }
                if (el.complete) go(); else { el.loading = 'eager'; el.addEventListener('load', go); el.addEventListener('error', function () { ok(null); }); }
            });
        }
        function prepare() {
            if (ready) return ready;
            var box = $('.pdfpanel__imgs', panel), jobs = [];
            data.blocks.forEach(function (b, i) {
                if (b.t !== 'img') return;
                var el = $('img[data-pdf-img="' + i + '"]', readerDoc);
                if (!el) return;
                jobs.push(jpeg(el).then(function (im) {
                    if (!im) return;
                    im.on = true;
                    imgs[i] = im;
                    var t = document.createElement('button');
                    t.type = 'button'; t.className = 'pdfpanel__img is-on';
                    t.setAttribute('aria-pressed', 'true');
                    var th = new Image(); th.src = el.src; th.alt = '';
                    t.appendChild(th);
                    t.addEventListener('click', function () { im.on = !im.on; t.classList.toggle('is-on', im.on); t.setAttribute('aria-pressed', String(im.on)); render(); });
                    box.appendChild(t);
                }));
            });
            ready = Promise.all(jobs).then(function () { $('[data-pdf-imgs]', panel).hidden = !Object.keys(imgs).length; });
            return ready;
        }
        var val = function (n) { var e = panel.querySelector('input[name=' + n + ']:checked') || panel.querySelector('input[name=' + n + ']'); return e ? e.value : ''; };
        function build() {
            // Text so, wie er gerade auf der Seite steht (z. B. nach dem Umrechnen der Portionen)
            var live = function (b, i) { var el = readerDoc.querySelector('[data-b="' + i + '"]'); return el ? { t: b.t, x: el.textContent, c: b.c } : b; };
            var blocks = data.blocks.map(function (b, i) { return b.t === 'img' ? (imgs[i] && imgs[i].on ? { t: 'img', img: imgs[i] } : null) : live(b, i); }).filter(Boolean);
            var tmpl = pageLbl.getAttribute('data-tmpl') || '%d / %d';
            return window.smPdfLayout({
                title: data.title, meta: data.meta, source: data.source, note: data.note, blocks: blocks,
                page: function (a, n) { return a + ' / ' + n; },
                opt: { font: val('pf'), size: +val('ps') / 100, space: +val('pl') / 100, cols: +val('pc'), margin: val('pm') }
            });
        }
        function render() {
            if (!window.smPdfLayout) return;
            lay = build();
            page = Math.min(page, lay.pages - 1);
            var w = Math.min(canvas.parentNode.clientWidth || 420, 520), dpr = window.devicePixelRatio || 1, sc = w / lay.width;
            canvas.width = Math.round(w * dpr); canvas.height = Math.round(lay.height * sc * dpr);
            canvas.style.width = w + 'px'; canvas.style.height = Math.round(lay.height * sc) + 'px';
            var ctx = canvas.getContext('2d');
            ctx.setTransform(sc * dpr, 0, 0, sc * dpr, 0, 0);
            lay.draw(ctx, page);
            pageLbl.textContent = (page + 1) + ' / ' + lay.pages;
        }
        openBtn.addEventListener('click', function () {
            panel.hidden = false;
            body.classList.add('has-overlay');
            prepare().then(render);
            render();
        });
        $('[data-pdf-close]', panel).addEventListener('click', function () { panel.hidden = true; body.classList.remove('has-overlay'); });
        panel.addEventListener('click', function (ev) { if (ev.target === panel) { panel.hidden = true; body.classList.remove('has-overlay'); } });
        $('[data-pdf-prev]', panel).addEventListener('click', function () { if (page > 0) { page--; render(); } });
        $('[data-pdf-next]', panel).addEventListener('click', function () { if (lay && page < lay.pages - 1) { page++; render(); } });
        panel.addEventListener('input', render);
        panel.addEventListener('change', render);
        $('[data-pdf-save]', panel).addEventListener('click', function () {
            prepare().then(function () {
                var l = build(), blob = new Blob([l.bytes()], { type: 'application/pdf' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (data.title || 'artikel').replace(/[^\wäöüÄÖÜß -]+/g, '').trim().slice(0, 80).replace(/\s+/g, '-') + '.pdf';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(function () { URL.revokeObjectURL(a.href); }, 30000);
            });
        });
    })();
    /* ---------- Werkzeuge: Tastenfeld für den Rechner, passende Einheiten, Datumsrechner ---------- */
    $$('[data-calc]').forEach(function (f) {
        var pad = $('[data-keypad]', f), inp = $('[data-calc-in]', f);
        if (!pad || !inp) return;
        pad.hidden = false;
        pad.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-key]');
            if (!b) return;
            var k = b.getAttribute('data-key');
            if (k === 'C') inp.value = '';
            else if (k === '⌫') inp.value = inp.value.slice(0, -1);
            else inp.value += k;
        });
    });
    $$('[data-units]').forEach(function (f) {
        var from = f.querySelector('select[name=from]'), to = f.querySelector('select[name=to]');
        function sync() {
            var grp = from.options[from.selectedIndex].getAttribute('data-grp');
            $$('optgroup', to).forEach(function (og) { var on = og.getAttribute('data-grp') === grp; og.hidden = !on; og.disabled = !on; });
            if (to.options[to.selectedIndex].getAttribute('data-grp') !== grp) {
                var first = to.querySelector('option[data-grp="' + grp + '"]:not([value="' + from.value + '"])') || to.querySelector('option[data-grp="' + grp + '"]');
                if (first) to.value = first.value;
            }
        }
        from.addEventListener('change', sync);
        sync();
    });
    $$('[data-datemode]').forEach(function (f) {
        function sync() {
            var m = (f.querySelector('input[name=m]:checked') || {}).value || 'diff';
            $$('[data-dm]', f).forEach(function (el) { el.hidden = el.getAttribute('data-dm').split(' ').indexOf(m) < 0; });
            var l = $('[data-dm-label]', f);
            if (l) l.textContent = l.getAttribute(m === 'age' ? 'data-l-age' : 'data-l-other');
        }
        f.addEventListener('change', sync);
        sync();
    });

    /* ---------- Werkzeuge: Suche, Favoriten (☆) und „Zuletzt benutzt“, alles nur im Browser gespeichert ---------- */
    var TF = 'sm_tools_fav', TR = 'sm_tools_recent';
    function tget(k) { try { var v = JSON.parse(localStorage.getItem(k) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
    function tset(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
    var page = $('[data-tool-page]');
    if (page) {
        var tid = page.getAttribute('data-tool-page');
        tset(TR, [tid].concat(tget(TR).filter(function (x) { return x !== tid; })).slice(0, 8));
        var pin = $('[data-tool-pin]');
        if (pin) {
            var paintPin = function () {
                var on = tget(TF).indexOf(tid) >= 0;
                pin.textContent = on ? '★' : '☆';
                pin.classList.toggle('is-on', on);
                pin.title = pin.getAttribute(on ? 'data-on' : 'data-off');
                pin.setAttribute('aria-label', pin.title);
            };
            pin.addEventListener('click', function () {
                var f = tget(TF);
                tset(TF, f.indexOf(tid) >= 0 ? f.filter(function (x) { return x !== tid; }) : f.concat([tid]));
                paintPin();
            });
            paintPin();
        }
    }
    var find = $('[data-tools-find]');
    if (find) {
        var cards = $$('.toolcard[data-tool]');
        var byId = {};
        cards.forEach(function (c) { byId[c.getAttribute('data-tool')] = c; });
        var fillBox = function (box, list, ids) {
            list.textContent = '';
            ids.forEach(function (id) {
                if (!byId[id]) return;
                var c = byId[id].cloneNode(true);
                c.removeAttribute('id');
                c.classList.add('toolcard--copy');
                var pb = c.querySelector('.toolcard__pin');
                if (pb) pb.remove();
                list.appendChild(c);
            });
            box.hidden = !list.children.length;
        };
        var favs = function () {
            fillBox($('[data-tools-pinned]'), $('[data-tools-pinned-list]'), tget(TF));
            fillBox($('[data-tools-recent]'), $('[data-tools-recent-list]'), tget(TR).filter(function (x) { return tget(TF).indexOf(x) < 0; }).slice(0, 4));
        };
        // Stern an jeder Kachel
        cards.forEach(function (c) {
            var id = c.getAttribute('data-tool');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'toolcard__pin';
            var paint = function () {
                var on = tget(TF).indexOf(id) >= 0;
                b.textContent = on ? '★' : '☆';
                b.classList.toggle('is-on', on);
                b.title = b.getAttribute('aria-label');
            };
            b.setAttribute('aria-label', '☆');
            b.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                var f = tget(TF);
                tset(TF, f.indexOf(id) >= 0 ? f.filter(function (x) { return x !== id; }) : f.concat([id]));
                paint();
                favs();
            });
            paint();
            c.appendChild(b);
        });
        favs();
        var norm = function (s) { return s.toLowerCase().replace(/ä/g, 'a').replace(/ö/g, 'o').replace(/ü/g, 'u').replace(/ß/g, 'ss'); };
        var run = function () {
            var q = norm(find.value.trim());
            var words = q.split(/\s+/).filter(Boolean);
            var any = false;
            $$('[data-tools-sec]').forEach(function (sec) {
                var vis = 0;
                $$('.toolcard', sec).forEach(function (c) {
                    var kw = norm(c.getAttribute('data-kw') || '');
                    var on = words.every(function (w) { return kw.indexOf(w) >= 0; });
                    c.hidden = !on;
                    if (on) vis++;
                });
                sec.hidden = !vis;
                if (vis) any = true;
            });
            if (q) $$('[data-tools-pinned], [data-tools-recent]').forEach(function (b) { b.hidden = true; });
            else favs();
            $('[data-tools-none]').hidden = any;
        };
        find.addEventListener('input', run);
        find.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                var first = $$('[data-tools-sec] .toolcard').filter(function (c) { return !c.hidden; })[0];
                if (first) { ev.preventDefault(); location.href = first.href; }
            } else if (ev.key === 'Escape') { find.value = ''; run(); }
        });
    }
    /* ---------- Einstellungen → Bangs: Suche während des Tippens, Bearbeiten ohne Neuladen ---------- */
    var bangFind = $('[data-bangfind]');
    var bangRes = $('[data-bang-results]');
    if (bangFind && bangRes) (function () {
        var timer = 0, seq = 0;
        function load() {
            var p = new URLSearchParams(new FormData(bangFind));
            var url = bangRes.getAttribute('data-frag') + '&' + p.toString();
            var my = ++seq;
            bangRes.classList.add('is-busy');
            fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
                if (my !== seq) return;
                bangRes.innerHTML = html;
                bangRes.classList.remove('is-busy');
                // Adresse mitführen, damit Neuladen und Zurück dieselbe Suche zeigen
                if (window.history && history.replaceState) {
                    var u = new URL(location.href);
                    ['bq', 'bf', 'bl'].forEach(function (k) { var v = p.get(k); if (v) u.searchParams.set(k, v); else u.searchParams.delete(k); });
                    u.searchParams.delete('edit');
                    u.searchParams.delete('bp');
                    history.replaceState(null, '', u.pathname + u.search);
                }
            }).catch(function () { bangRes.classList.remove('is-busy'); });
        }
        bangFind.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 180); });
        bangFind.addEventListener('change', function (ev) { if (ev.target.tagName === 'SELECT') load(); });
        bangFind.addEventListener('submit', function (ev) { ev.preventDefault(); load(); });
        var bf = $('[data-bang-form]'), title = $('[data-bang-form-title]'), cancel = $('[data-bang-new]');
        function fill(b) {
            bf.elements.t.value = b ? b.t : '';
            bf.elements.n.value = b ? b.n : '';
            bf.elements.u.value = b ? b.u : '';
            bf.elements.orig.value = b ? b.t : '';
            title.textContent = title.getAttribute(b ? 'data-edit' : 'data-new');
            if (cancel) cancel.hidden = !b;
            $$('.bang.is-editing').forEach(function (r) { r.classList.remove('is-editing'); });
        }
        // Mehrfach löschen: Häkchen zählen, „alle auf dieser Seite“ markieren
        function picks() {
            var boxes = $$('.bang__pick', bangRes), on = boxes.filter(function (x) { return x.checked; }).length;
            var btn = $('[data-bang-bulk]', bangRes), cnt = $('[data-bang-count]', bangRes), all = $('[data-bang-all]', bangRes);
            if (btn) btn.disabled = !on;
            if (cnt) cnt.textContent = on ? '(' + on + ')' : '';
            if (all) { all.checked = on > 0 && on === boxes.length; all.indeterminate = on > 0 && on < boxes.length; }
        }
        bangRes.addEventListener('change', function (ev) {
            if (ev.target.matches('[data-bang-all]')) $$('.bang__pick', bangRes).forEach(function (x) { x.checked = ev.target.checked; });
            if (ev.target.matches('[data-bang-all], .bang__pick')) picks();
        });
        bangRes.addEventListener('click', function (ev) {
            var a = ev.target.closest('[data-bang-edit]');
            if (!a || !plainClick(ev)) return;
            ev.preventDefault();
            var row = a.closest('[data-bang]');
            fill(JSON.parse(row.getAttribute('data-bang')));
            row.classList.add('is-editing');
            bf.scrollIntoView({ behavior: 'smooth', block: 'center' });
            bf.elements.u.focus();
        });
        if (cancel) cancel.addEventListener('click', function (ev) { if (!plainClick(ev)) return; ev.preventDefault(); fill(null); });
    })();

    /* ---------- Hintergrundarbeit (Bang-Liste aktualisieren, Erreichbarkeit prüfen): kurz nach dem Laden, ohne auf die Antwort zu warten ---------- */
    var tick = body.getAttribute('data-tick');
    if (tick && window.fetch) setTimeout(function () { fetch(tick, { credentials: 'same-origin', keepalive: true }).catch(function () {}); }, 2500);
})();
