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
        var active = -1;
        var items = [];

        function sync() { form.classList.toggle('has-value', input.value.length > 0); }
        input.addEventListener('input', function () { sync(); render(); });
        sync();
        clear.addEventListener('click', function () { input.value = ''; sync(); input.focus(); render(); });

        form.addEventListener('submit', function (ev) {
            if (!input.value.trim()) { ev.preventDefault(); loading(false); input.focus(); return; }
            if (useHist) histAdd(input.value);
            close();
        });

        function close() { list.hidden = true; form.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); active = -1; }

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

        function render() {
            if (!useHist || document.activeElement !== input) { close(); return; }
            var q = input.value.trim();
            var lq = q.toLowerCase();
            items = hist().filter(function (x) { return !lq || (x.toLowerCase().indexOf(lq) !== -1 && x.toLowerCase() !== lq); }).slice(0, 8);
            list.innerHTML = '';
            active = -1;
            if (!items.length) { close(); return; }
            items.forEach(function (text, i) {
                var li = document.createElement('li');
                li.className = 'sbox__opt';
                li.setAttribute('role', 'option');
                li.id = 'sbox-opt-' + i;
                li.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>';
                li.appendChild(mark(text, q));
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'sbox__del';
                del.textContent = form.getAttribute('data-label-remove') || '×';
                del.addEventListener('mousedown', function (ev) { ev.preventDefault(); ev.stopPropagation(); histDel(text); render(); });
                li.appendChild(del);
                li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(text); });
                list.appendChild(li);
            });
            list.hidden = false;
            form.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }

        function pick(text) {
            input.value = text;
            sync();
            close();
            if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { histAdd(text); loading(true); form.submit(); }
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
            input.value = active >= 0 ? items[active] : typed;
            sync();
        }

        input.addEventListener('focus', function () { typed = input.value; render(); });
        input.addEventListener('blur', function () { setTimeout(close, 120); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown') { ev.preventDefault(); if (list.hidden) render(); else move(1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); move(-1); }
            else if (ev.key === 'Escape') { if (!list.hidden) { ev.preventDefault(); close(); } else { input.blur(); } }
            else if (ev.key === 'Delete' && ev.shiftKey && active >= 0) { ev.preventDefault(); histDel(items[active]); render(); }
        });
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
            document.cookie = 'sm_theme=' + next + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            label();
        });
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

    // Sicherheitsfragen
    $$('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); ev.stopImmediatePropagation(); loading(false); }
        }, true);
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

    /* ---------- Endlos-Scrollen: am Ende der Liste die nächsten Treffer holen ---------- */
    function initMore(more) {
        if (!more || !('IntersectionObserver' in window) || !window.fetch) return;
        more.classList.add('is-auto');
        var busy = false;
        var io = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting || busy) return;
            busy = true;
            more.classList.remove('is-error');
            more.classList.add('is-auto');
            fetch(more.getAttribute('data-more'), { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
                .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.text(); })
                .then(function (html) {
                    io.disconnect();
                    var tpl = document.createElement('template');
                    tpl.innerHTML = html;
                    var next = tpl.content.querySelector('.more');
                    $$('.fav img', tpl.content).forEach(function (img) {
                        img.addEventListener('load', function () { if (img.naturalWidth > 1) img.classList.add('is-loaded'); });
                    });
                    more.replaceWith(tpl.content);
                    if (next) initMore(next);
                })
                .catch(function () {
                    busy = false;
                    more.classList.remove('is-auto');
                    more.classList.add('is-error');
                });
        }, { rootMargin: '600px 0px' });
        io.observe(more);
    }
    initMore($('.more[data-more]'));

    /* ---------- Bilder: Raster mit natürlichen Seitenverhältnissen, Vorschau im Großformat ---------- */
    var grid = $('[data-grid]');
    if (grid) {
        var tiles = $$('.grid__item', grid);
        tiles.forEach(function (a) {
            var w = parseInt(a.getAttribute('data-w'), 10), h = parseInt(a.getAttribute('data-h'), 10);
            if (w > 0 && h > 0) {
                var ratio = Math.max(.5, Math.min(2.6, w / h));
                a.style.flexGrow = String(ratio * 100);
                a.style.flexBasis = (ratio * (window.innerWidth < 640 ? 130 : 190)) + 'px';
            }
        });

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
            // Volle Auflösung über den eigenen Server nachladen, bis dahin bleibt das Vorschaubild stehen
            var full = a.getAttribute('data-full');
            lb.classList.remove('is-sharp');
            if (full) {
                var hi = new Image();
                hi.onload = function () { if (cur === i && hi.naturalWidth > 1) { img.src = full; lb.classList.add('is-sharp'); } };
                hi.src = full;
            }
            wake();
        }
        function hide() {
            if (isFull()) exitFull();
            lb.hidden = true;
            body.style.overflow = '';
            // War die Großansicht über die Adresse (?view=…) geöffnet: zurück zur Rasteradresse
            if (/[?&]view=/.test(location.search) && window.history && history.replaceState) history.replaceState(null, '', lb.getAttribute('data-grid-url'));
            if (tiles[cur]) tiles[cur].focus();
        }

        /* Vollbild: Fullscreen-API des Browsers, ohne sie (iPhone) ein randloses Ersatz-Vollbild per CSS */
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
            if (lb.hidden || ev.ctrlKey || ev.metaKey || ev.altKey) return;
            if (document.querySelector('.overlay:not([hidden])')) return;
            if (ev.key === 'Escape') { ev.preventDefault(); if (isFull()) exitFull(); else hide(); }
            else if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(cur - 1); }
            else if (ev.key === 'ArrowRight') { ev.preventDefault(); show(cur + 1); }
            else if (ev.key === 'f' || ev.key === 'F') { ev.preventDefault(); toggleFull(); }
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
            var input = $('form[data-search] input[name="q"]');
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
    function beep() {
        try {
            var A = window.AudioContext || window.webkitAudioContext, ctx = new A();
            [0, 0.35, 0.7].forEach(function (t) {
                var o = ctx.createOscillator(), gn = ctx.createGain();
                o.frequency.value = 880; o.connect(gn); gn.connect(ctx.destination);
                gn.gain.setValueAtTime(0.25, ctx.currentTime + t); gn.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.3);
                o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.3);
            });
        } catch (e) {}
    }
    $$('[data-timer]').forEach(function (tm) {
        var total = parseInt(tm.getAttribute('data-sec'), 10) || 0, watch = total === 0;
        var show = $('[data-timer-show]', tm), go = $('[data-timer-start]', tm), reset = $('[data-timer-reset]', tm);
        var left = total * 1000, run = false, last = 0, iv = 0, title = document.title;
        function fmt(ms) {
            var s = Math.round(ms / 1000), h = Math.floor(s / 3600);
            return (h ? h + ':' : '') + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60) + (watch ? '.' + Math.floor(ms % 1000 / 100) : '');
        }
        function draw() { show.textContent = fmt(watch ? left : Math.max(0, left)); if (run) document.title = fmt(left) + ' · ' + title; }
        function step() {
            var now = Date.now(); left += watch ? now - last : -(now - last); last = now;
            if (!watch && left <= 0) {
                left = 0; stop(); draw(); tm.classList.add('is-done'); show.textContent = tm.getAttribute('data-done'); document.title = '⏰ ' + title; beep();
                if ('vibrate' in navigator) navigator.vibrate([300, 150, 300]);
                return;
            }
            draw();
        }
        function stop() { run = false; clearInterval(iv); go.textContent = go.getAttribute('data-label-go'); }
        go.addEventListener('click', function () {
            if (run) { stop(); document.title = title; return; }
            if (!watch && left <= 0) left = total * 1000;
            tm.classList.remove('is-done');
            run = true; last = Date.now(); iv = setInterval(step, watch ? 100 : 250); go.textContent = go.getAttribute('data-label-pause');
        });
        reset.addEventListener('click', function () { stop(); left = watch ? 0 : total * 1000; tm.classList.remove('is-done'); document.title = title; draw(); });
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
})();
