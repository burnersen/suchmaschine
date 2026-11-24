/* Suchmaschine – etwas Komfort im Browser. Ohne JavaScript funktioniert alles auch, nur schlichter. */
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
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

    // "/" springt ins Suchfeld
    document.addEventListener('keydown', function (ev) {
        var t = ev.target;
        var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
        if (ev.key === '/' && !typing && !ev.ctrlKey && !ev.metaKey) {
            var input = $('form[data-search] input[name="q"]');
            if (input) { ev.preventDefault(); input.focus(); input.select(); }
        }
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
            $('[data-lb-page]', lb).href = a.href;
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
        $('[data-lb-close]', lb).addEventListener('click', hide);
        fullBtn.addEventListener('click', toggleFull);
        $('[data-lb-prev]', lb).addEventListener('click', function () { show(cur - 1); });
        $('[data-lb-next]', lb).addEventListener('click', function () { show(cur + 1); });
        lb.addEventListener('click', function (ev) { if (ev.target === lb) hide(); });
        img.addEventListener('dblclick', toggleFull);
        document.addEventListener('keydown', function (ev) {
            if (lb.hidden || ev.ctrlKey || ev.metaKey || ev.altKey) return;
            if (ev.key === 'Escape') { ev.preventDefault(); if (isFull()) exitFull(); else hide(); }
            else if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(cur - 1); }
            else if (ev.key === 'ArrowRight') { ev.preventDefault(); show(cur + 1); }
            else if (ev.key === 'f' || ev.key === 'F') { ev.preventDefault(); toggleFull(); }
        });
    }
})();
