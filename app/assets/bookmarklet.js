/* Suchmaschine – Lesezeichen „Bilder groß“ (Bookmarklet).
   Läuft auf einer fremden Seite (z. B. Bing- oder DuckDuckGo-Bildersuche), sammelt die Bilder in der größten Fassung,
   die die Seite verrät, und zeigt sie randlos auf Schwarz: Pfeile, Wischen, F = Vollbild, S = Diashow, G = Übersicht,
   Zoomen mit zwei Fingern, Doppeltipp oder Mausrad, Esc = schließen. Es beginnt mit dem Bild, das gerade zu sehen ist.
   Schickt nichts irgendwohin und lädt nichts nach außer den Bildern selbst.
   Wird beim Ausliefern zu einer javascript:-Adresse (ganze Kommentarzeilen fallen weg, darum keine Kommentare hinter Code). */
(function () {
    var L = __SM_TEXTS__;
    var old = document.getElementById('sm-big');
    if (old) { old.remove(); return; }
    var seen = {}, list = [];
    function add(u, t, el) {
        if (!u || typeof u !== 'string' || u.indexOf('data:') === 0) return;
        try { u = new URL(u, location.href).href; } catch (e) { return; }
        if (!/^https?:/.test(u) || seen[u] || /\.svg(\?|#|$)/i.test(u)) return;
        seen[u] = 1;
        list.push({ u: u, t: (t || '').trim().slice(0, 200), el: el || null });
    }
    function attrJson(el, name) { try { return JSON.parse(el.getAttribute(name)); } catch (e) { return null; } }
    function unwrap(u) {
        try {
            var p = new URL(u, location.href).searchParams;
            return p.get('imgurl') || p.get('mediaurl') || p.get('u') || p.get('url') || '';
        } catch (e) { return ''; }
    }
    function fromSet(set, best, bw) {
        (set || '').split(',').forEach(function (c) {
            var m = c.trim().split(/\s+/), w = parseFloat(m[1]) || 0;
            if (/x$/.test(m[1] || '')) w = w * 1000;
            if (m[0] && w > bw) { bw = w; best = m[0]; }
        });
        return [best, bw];
    }
    function bestSrc(img) {
        var lazy = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.getAttribute('data-original') || '';
        var cur = img.currentSrc || img.src || '';
        var b = [cur && cur.indexOf('data:') !== 0 ? cur : lazy, img.naturalWidth || 0];
        b = fromSet(img.getAttribute('srcset'), b[0], b[1]);
        b = fromSet(img.getAttribute('data-srcset'), b[0], b[1]);
        var pic = img.parentNode && img.parentNode.tagName === 'PICTURE' ? img.parentNode : null;
        if (pic) pic.querySelectorAll('source').forEach(function (so) {
            if (!/avif|jxl/i.test(so.type || '')) b = fromSet(so.getAttribute('srcset') || so.getAttribute('data-srcset'), b[0], b[1]);
        });
        return b;
    }
    function collect() {
        var n0 = list.length;
        /* Bing (und Suchmaschinen, die Bing-Bilder zeigen): Original steht im Attribut m als murl */
        document.querySelectorAll('a.iusc[m], [m*="murl"]').forEach(function (a) { var m = attrJson(a, 'm'); if (m) add(m.murl, m.t, a); });
        /* Links mit dem Original in der Adresse (Google imgurl, Bing mediaurl, Proxys mit u=) */
        document.querySelectorAll('a[href*="imgurl="], a[href*="mediaurl="]').forEach(function (a) { add(unwrap(a.href), a.title, a); });
        /* Bilder auf der Seite: größte Fassung aus srcset und <picture>, nachgeladene (data-src), Vorschauen über Proxys ausgepackt, kleine Symbole nicht.
           Führt ein Bild als Link zu einer Bilddatei, zählt die. */
        document.querySelectorAll('img').forEach(function (img) {
            if (box && box.contains(img)) return;
            var b = bestSrc(img), u = b[0];
            if (!u) return;
            var inner = /external-content\.duckduckgo\.com|\/iu\/\?u=|imgs\.search\.brave\.com/.test(u) ? unwrap(u) : '';
            if (inner && !/tse\d*\.(mm\.)?bing\.net|th\.bing\.com/.test(inner)) u = inner;
            var r = img.getBoundingClientRect();
            if (Math.max(b[1], img.naturalWidth || 0, img.naturalHeight || 0, r.width, r.height) < 160) return;
            var a = img.closest ? img.closest('a[href]') : null;
            if (a && /\.(jpe?g|png|webp|gif|avif)(\?|#|$)/i.test(a.href) && !/\/[^\/?#]*:[^\/?#]*$/.test(a.pathname)) u = a.href;
            add(u, img.alt || img.title, img);
        });
        /* Links direkt auf Bilddateien (nicht auf Seiten über ein Bild wie „Datei:Name.jpg“ bei Wikis) */
        document.querySelectorAll('a[href]').forEach(function (a) {
            if (/\.(jpe?g|png|webp|gif|avif)(\?|#|$)/i.test(a.href) && !/\/[^\/?#]*:[^\/?#]*$/.test(a.pathname)) add(a.href, a.title || a.textContent, a);
        });
        /* Große Hintergrundbilder (Galerien, die ohne <img> arbeiten) */
        var n = 0;
        document.querySelectorAll('div, a, li, figure, span, section').forEach(function (el) {
            if (n > 4000 || (box && box.contains(el))) return;
            n++;
            var r = el.getBoundingClientRect();
            if (r.width < 160 || r.height < 120) return;
            var m = /url\(["']?([^"')]+)["']?\)/.exec(getComputedStyle(el).backgroundImage || '');
            if (m) add(m[1], el.title || el.getAttribute('aria-label'), el);
        });
        return list.length - n0;
    }
    var box = null;
    collect();
    if (!list.length) { alert(L.none); return; }

    var cur = 0, startX = 0, startY = 0, idle = 0, slideT = 0, playing = false;
    /* Anfangen mit dem Bild, das gerade am nächsten an der Bildschirmmitte steht */
    (function () {
        var best = 1e9, mid = window.innerHeight / 2;
        list.forEach(function (it, i) {
            if (!it.el || !it.el.getBoundingClientRect) return;
            var r = it.el.getBoundingClientRect();
            if (!r.width || r.bottom < 0 || r.top > window.innerHeight) return;
            var d = Math.abs((r.top + r.bottom) / 2 - mid);
            if (d < best) { best = d; cur = i; }
        });
    })();
    box = document.createElement('div');
    box.id = 'sm-big';
    box.setAttribute('style', 'position:fixed;inset:0;z-index:2147483647;background:#000;display:flex;align-items:center;justify-content:center;touch-action:none;font:14px/1.4 system-ui,sans-serif;color:#fff');
    var img = document.createElement('img');
    img.setAttribute('style', 'max-width:100%;max-height:100%;width:100%;height:100%;object-fit:contain;display:block;transform-origin:0 0;will-change:transform');
    img.draggable = false;
    img.referrerPolicy = 'no-referrer';
    var cap = document.createElement('div');
    cap.setAttribute('style', 'position:absolute;left:0;right:0;bottom:0;padding:30px 16px max(12px,env(safe-area-inset-bottom));text-align:center;background:linear-gradient(transparent,rgba(0,0,0,.6));white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:opacity .3s');
    /* Symbole werden gezeichnet (SVG), nicht als Schriftzeichen gesetzt: Handys malen Zeichen wie ▶ oder ↗ sonst als bunte Emojis */
    var NS = 'http://www.w3.org/2000/svg';
    var ICONS = {
        prev: ['m15 5-7 7 7 7'], next: ['m9 5 7 7-7 7'], close: ['M6 6l12 12M18 6 6 18'], full: ['M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5'],
        open: ['M9 5H5v14h14v-4M13 5h6v6M19 5l-8 8'], play: ['M8 5.5v13l11-6.5z', 1], pause: ['M7 5h3.5v14H7zM13.5 5H17v14h-3.5z', 1],
        grid: ['M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'], send: ['M12 16V4M7 9l5-5 5 5M5 14v6h14v-6']
    };
    function icon(b, name) {
        while (b.firstChild) b.removeChild(b.firstChild);
        var d = ICONS[name], sv = document.createElementNS(NS, 'svg'), pa = document.createElementNS(NS, 'path');
        sv.setAttribute('viewBox', '0 0 24 24');
        sv.setAttribute('width', '24');
        sv.setAttribute('height', '24');
        sv.setAttribute('style', 'display:block;margin:auto;pointer-events:none');
        pa.setAttribute('d', d[0]);
        pa.setAttribute('fill', d[1] ? 'currentColor' : 'none');
        pa.setAttribute('stroke', d[1] ? 'none' : 'currentColor');
        pa.setAttribute('stroke-width', '2');
        pa.setAttribute('stroke-linecap', 'round');
        pa.setAttribute('stroke-linejoin', 'round');
        sv.appendChild(pa);
        b.appendChild(sv);
    }
    function btn(name, css, fn, title) {
        var b = document.createElement('button');
        b.type = 'button';
        icon(b, name);
        b.title = title || '';
        b.setAttribute('aria-label', title || '');
        b.setAttribute('style', 'position:absolute;' + css + ';width:48px;height:48px;padding:0;border:0;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;cursor:pointer;transition:opacity .3s;-webkit-appearance:none;appearance:none');
        b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
        box.appendChild(b);
        return b;
    }
    box.appendChild(img);
    box.appendChild(cap);
    var bPrev = btn('prev', 'left:max(12px,env(safe-area-inset-left));top:50%;margin-top:-24px', function () { go(-1); }, L.prev);
    var bNext = btn('next', 'right:max(12px,env(safe-area-inset-right));top:50%;margin-top:-24px', function () { go(1); }, L.next);
    var bClose = btn('close', 'right:max(12px,env(safe-area-inset-right));top:max(12px,env(safe-area-inset-top))', close, L.close);
    var bFull = btn('full', 'right:max(70px,calc(env(safe-area-inset-right) + 58px));top:max(12px,env(safe-area-inset-top))', full, L.full);
    var bOpen = btn('open', 'right:max(128px,calc(env(safe-area-inset-right) + 116px));top:max(12px,env(safe-area-inset-top))', function () { window.open(list[cur].u, '_blank', 'noopener'); }, L.open);
    /* Diashow: Knopf startet und stoppt, Auswahl = Sekunden pro Bild, nach dem letzten Bild wieder von vorne */
    var bPlay = btn('play', 'right:max(186px,calc(env(safe-area-inset-right) + 174px));top:max(12px,env(safe-area-inset-top))', function () { slide(!playing); }, L.play);
    var sel = document.createElement('select');
    sel.title = L.secs;
    sel.setAttribute('style', 'position:absolute;right:max(244px,calc(env(safe-area-inset-right) + 232px));top:max(16px,calc(env(safe-area-inset-top) + 4px));height:40px;padding:0 6px;border:0;border-radius:20px;background:rgba(0,0,0,.5);color:#fff;font:14px system-ui,sans-serif;transition:opacity .3s');
    [2, 3, 5, 8, 10, 15, 30, 60].forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = n + ' s'; sel.appendChild(o); });
    try { sel.value = localStorage.getItem('smBigSecs') || '5'; } catch (e) { sel.value = '5'; }
    if (!sel.value) sel.value = '5';
    sel.addEventListener('change', function () { try { localStorage.setItem('smBigSecs', sel.value); } catch (e) {} if (playing) next(); });
    sel.addEventListener('click', function (e) { e.stopPropagation(); });
    box.appendChild(sel);
    var bGrid = btn('grid', 'left:max(12px,env(safe-area-inset-left));top:max(12px,env(safe-area-inset-top))', function () { overview(!ovw); }, L.grid);
    var ctrls = [cap, bPrev, bNext, bClose, bFull, bOpen, bPlay, sel, bGrid];
    /* „An meine App schicken“: die gefundenen Bildadressen als Sammlung ins eigene Konto. Dort laufen sie randlos im Vollbild,
       was Safari am iPhone einer Webseite nicht erlaubt. Den Knopf gibt es nur im persönlichen Lesezeichen (mit Konto eingerichtet). */
    var SEND = __SM_SEND__;
    if (SEND) {
        var bSend = btn('send', 'left:max(12px,env(safe-area-inset-left));bottom:max(64px,calc(env(safe-area-inset-bottom) + 56px))', function () {
            var items = list.slice(0, 400).map(function (it) {
                var th = it.el && it.el.tagName === 'IMG' ? (it.el.currentSrc || it.el.src || '') : '';
                return { u: it.u, t: it.t, th: /^https?:/.test(th) && th !== it.u ? th : '' };
            });
            var f = document.createElement('form');
            f.method = 'post';
            f.action = SEND.url;
            f.acceptCharset = 'UTF-8';
            f.style.display = 'none';
            [['k', SEND.k], ['u', location.href], ['t', document.title], ['list', JSON.stringify(items)]].forEach(function (p) {
                var i = document.createElement('textarea');
                i.name = p[0];
                i.value = p[1];
                f.appendChild(i);
            });
            document.body.appendChild(f);
            f.submit();
        }, L.send);
        var sl = document.createElement('span');
        sl.textContent = L.send;
        sl.setAttribute('style', 'font:600 14px system-ui,sans-serif;margin-left:6px;white-space:nowrap');
        bSend.style.width = 'auto';
        bSend.style.padding = '0 16px 0 12px';
        bSend.style.borderRadius = '24px';
        bSend.style.background = '#4f46e5';
        bSend.style.display = 'flex';
        bSend.style.alignItems = 'center';
        bSend.firstChild.style.margin = '0';
        bSend.appendChild(sl);
        ctrls.push(bSend);
    }
    /* Übersicht: alle Bilder klein im Raster, antippen springt hin */
    var ovw = null;
    function overview(on) {
        if (ovw) { ovw.remove(); ovw = null; }
        if (!on) { wake(); return; }
        slide(false);
        ovw = document.createElement('div');
        ovw.setAttribute('style', 'position:absolute;inset:0;z-index:2;overflow:auto;-webkit-overflow-scrolling:touch;touch-action:pan-y;background:#000;padding:max(68px,calc(env(safe-area-inset-top) + 64px)) 8px max(12px,env(safe-area-inset-bottom));display:grid;grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:6px;align-content:start');
        list.forEach(function (it, i) {
            var t = document.createElement('img');
            t.loading = 'lazy';
            t.referrerPolicy = 'no-referrer';
            t.src = it.el && it.el.tagName === 'IMG' && (it.el.currentSrc || it.el.src) ? (it.el.currentSrc || it.el.src) : it.u;
            t.setAttribute('style', 'width:100%;height:104px;object-fit:cover;border-radius:6px;cursor:pointer;background:#222;outline:' + (i === cur ? '3px solid #7cc4ff' : '0'));
            t.addEventListener('click', function (e) { e.stopPropagation(); overview(false); show(i); });
            ovw.appendChild(t);
        });
        ovw.addEventListener('click', function (e) { e.stopPropagation(); });
        box.insertBefore(ovw, bPrev);
        box.appendChild(bGrid);
        box.appendChild(bClose);
        var c = ovw.children[cur];
        if (c && c.scrollIntoView) c.scrollIntoView({ block: 'center' });
    }
    /* Zoomen: zwei Finger, Doppeltipp oder Doppelklick. Vergrößert lässt sich das Bild mit dem Finger oder der Maus verschieben. */
    var z = 1, zx = 0, zy = 0, pinch = 0, pz = 1, panX = 0, panY = 0, lastTap = 0, moved = false, fails = 0;
    function zoomSet(s, x, y) {
        var w = box.clientWidth, h = box.clientHeight;
        z = Math.max(1, Math.min(6, s));
        zx = z === 1 ? 0 : Math.min(0, Math.max(w - w * z, x));
        zy = z === 1 ? 0 : Math.min(0, Math.max(h - h * z, y));
        img.style.transform = z === 1 ? '' : 'translate(' + zx + 'px,' + zy + 'px) scale(' + z + ')';
    }
    function zoomAt(s, cx, cy) { zoomSet(s, cx - (cx - zx) / z * Math.max(1, Math.min(6, s)), cy - (cy - zy) / z * Math.max(1, Math.min(6, s))); }
    function dist(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
    function next() {
        clearTimeout(slideT);
        if (playing) slideT = setTimeout(function () { show(cur + 1); }, (parseInt(sel.value, 10) || 5) * 1000);
    }
    function slide(on) {
        playing = on;
        icon(bPlay, on ? 'pause' : 'play');
        bPlay.title = on ? L.stop : L.play;
        bPlay.style.color = on ? '#7cc4ff' : '#fff';
        if (on) next(); else clearTimeout(slideT);
    }

    function show(i) {
        /* Am Ende angekommen: nachsehen, ob die Seite inzwischen mehr Bilder geladen hat */
        if (i >= list.length) collect();
        cur = (i + list.length) % list.length;
        var it = list[cur];
        zoomSet(1, 0, 0);
        /* Lädt ein Bild nicht, kommt gleich das nächste (höchstens so oft, wie es Bilder gibt) */
        img.onerror = function () {
            if (list.length > 1 && fails < list.length) { fails++; list.splice(cur, 1); show(cur >= list.length ? 0 : cur); return; }
            cap.textContent = (cur + 1) + ' / ' + list.length + ' · ' + L.broken;
        };
        img.onload = function () { fails = 0; };
        img.src = it.u;
        cap.textContent = (cur + 1) + ' / ' + list.length + (it.t ? ' · ' + it.t : '');
        var pre = new Image();
        pre.referrerPolicy = 'no-referrer';
        pre.src = list[(cur + 1) % list.length].u;
        wake();
        if (playing) next();
    }
    function go(d) { show(cur + d); }
    function full() {
        var fe = document.fullscreenElement || document.webkitFullscreenElement;
        if (fe) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
        var rq = box.requestFullscreen || box.webkitRequestFullscreen;
        if (rq) { try { var p = rq.call(box); if (p && p.catch) p.catch(function () {}); } catch (e) {} }
    }
    function close() {
        clearTimeout(slideT);
        playing = false;
        var fe = document.fullscreenElement || document.webkitFullscreenElement;
        if (fe) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        document.removeEventListener('keydown', key, true);
        box.remove();
        document.documentElement.style.overflow = ov;
    }
    function wake() {
        ctrls.forEach(function (c) { c.style.opacity = '1'; });
        box.style.cursor = '';
        clearTimeout(idle);
        idle = setTimeout(function () { ctrls.forEach(function (c) { c.style.opacity = '0'; }); box.style.cursor = 'none'; }, 2500);
    }
    function key(e) {
        if (e.key === 'ArrowRight' || e.key === ' ') go(1);
        else if (e.key === 'ArrowLeft') go(-1);
        else if (e.key === 'Escape' && ovw) overview(false);
        else if (e.key === 'Escape' && !(document.fullscreenElement || document.webkitFullscreenElement)) close();
        else if (e.key === 'g' || e.key === 'G') overview(!ovw);
        else if (e.key === '+' || e.key === '=') zoomAt(z * 1.5, box.clientWidth / 2, box.clientHeight / 2);
        else if (e.key === '-') zoomAt(z / 1.5, box.clientWidth / 2, box.clientHeight / 2);
        else if (e.key === 'f' || e.key === 'F') full();
        else if (e.key === 's' || e.key === 'S') slide(!playing);
        else return;
        e.preventDefault();
        e.stopPropagation();
    }
    box.addEventListener('mousemove', wake);
    /* Einmal tippen oder klicken blättert (rechte Hälfte vor, linke zurück), aber erst nach kurzem Warten: kommt ein zweiter Tipp, wird gezoomt */
    var tapT = 0, lastTouch = 0;
    function tapNav(x) {
        clearTimeout(tapT);
        tapT = setTimeout(function () { go(x > box.clientWidth / 2 ? 1 : -1); }, 280);
    }
    box.addEventListener('click', function (e) {
        if (Date.now() - lastTouch < 700) return;
        if ((e.target !== box && e.target !== img) || moved || z > 1) { moved = false; return; }
        tapNav(e.clientX);
    });
    box.addEventListener('dblclick', function (e) {
        if (Date.now() - lastTouch < 700 || e.target !== img) return;
        clearTimeout(tapT);
        zoomAt(z > 1 ? 1 : 2.5, e.clientX, e.clientY);
    });
    box.addEventListener('wheel', function (e) {
        if (ovw) return;
        e.preventDefault();
        zoomAt(z * (e.deltaY < 0 ? 1.2 : 1 / 1.2), e.clientX, e.clientY);
    }, { passive: false });
    box.addEventListener('mousedown', function (e) { if (z > 1 && e.target === img) { panX = e.clientX - zx; panY = e.clientY - zy; pinch = -1; e.preventDefault(); } });
    box.addEventListener('mousemove', function (e) { if (pinch === -1) { moved = true; zoomSet(z, e.clientX - panX, e.clientY - panY); } });
    window.addEventListener('mouseup', function () { if (pinch === -1) pinch = 0; });
    box.addEventListener('touchstart', function (e) {
        if (ovw) return;
        wake();
        if (e.touches.length === 2) { pinch = dist(e.touches); pz = z; return; }
        startX = e.touches[0].clientX; startY = e.touches[0].clientY;
        panX = startX - zx; panY = startY - zy;
    }, { passive: true });
    box.addEventListener('touchmove', function (e) {
        if (ovw) return;
        if (e.touches.length === 2 && pinch > 0) {
            zoomAt(pz * dist(e.touches) / pinch, (e.touches[0].clientX + e.touches[1].clientX) / 2, (e.touches[0].clientY + e.touches[1].clientY) / 2);
        } else if (z > 1 && e.touches.length === 1) {
            zoomSet(z, e.touches[0].clientX - panX, e.touches[0].clientY - panY);
        }
    }, { passive: true });
    box.addEventListener('touchend', function (e) {
        if (ovw) return;
        lastTouch = Date.now();
        if (pinch > 0) { if (!e.touches.length) pinch = 0; return; }
        var t = e.changedTouches[0], dx = t.clientX - startX, dy = t.clientY - startY, now = lastTouch;
        if (Math.abs(dx) < 12 && Math.abs(dy) < 12) {
            if (e.target !== img && e.target !== box) return;
            /* Doppeltipp: hinein und wieder heraus */
            if (now - lastTap < 320) { clearTimeout(tapT); zoomAt(z > 1 ? 1 : 2.5, t.clientX, t.clientY); lastTap = 0; }
            else { lastTap = now; if (z === 1) tapNav(t.clientX); }
            return;
        }
        if (z > 1) return;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(dx < 0 ? 1 : -1);
        else if (dy > 90) close();
    });
    document.addEventListener('keydown', key, true);
    var ov = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    document.body.appendChild(box);
    show(cur);
})();
