/* Suchmaschine – Lesezeichen „Bilder groß“ (Bookmarklet).
   Läuft auf einer fremden Seite (z. B. Bing- oder DuckDuckGo-Bildersuche), sammelt die Bilder in der größten Fassung,
   die die Seite verrät, und zeigt sie randlos auf Schwarz: Pfeile, Wischen, F = Vollbild, Esc = schließen.
   Schickt nichts irgendwohin und lädt nichts nach außer den Bildern selbst.
   Wird beim Ausliefern zu einer javascript:-Adresse (ganze Kommentarzeilen fallen weg, darum keine Kommentare hinter Code). */
(function () {
    var L = __SM_TEXTS__;
    var old = document.getElementById('sm-big');
    if (old) { old.remove(); return; }
    var seen = {}, list = [];
    function add(u, t) {
        if (!u || typeof u !== 'string' || u.indexOf('data:') === 0) return;
        try { u = new URL(u, location.href).href; } catch (e) { return; }
        if (!/^https?:/.test(u) || seen[u]) return;
        seen[u] = 1;
        list.push({ u: u, t: (t || '').trim().slice(0, 200) });
    }
    function attrJson(el, name) { try { return JSON.parse(el.getAttribute(name)); } catch (e) { return null; } }
    function unwrap(u) {
        try {
            var p = new URL(u, location.href).searchParams;
            return p.get('imgurl') || p.get('mediaurl') || p.get('u') || p.get('url') || '';
        } catch (e) { return ''; }
    }
    function bestSrc(img) {
        var best = img.currentSrc || img.src, bw = img.naturalWidth || 0;
        (img.getAttribute('srcset') || '').split(',').forEach(function (c) {
            var m = c.trim().split(/\s+/), w = parseInt(m[1], 10) || 0;
            if (m[0] && w > bw) { bw = w; best = m[0]; }
        });
        return [best, bw];
    }
    /* Bing (und Suchmaschinen, die Bing-Bilder zeigen): Original steht im Attribut m als murl */
    document.querySelectorAll('a.iusc[m], [m*="murl"]').forEach(function (a) { var m = attrJson(a, 'm'); if (m) add(m.murl, m.t); });
    /* Links mit dem Original in der Adresse (Google imgurl, Bing mediaurl, Proxys mit u=) */
    document.querySelectorAll('a[href*="imgurl="], a[href*="mediaurl="]').forEach(function (a) { add(unwrap(a.href), a.title); });
    /* Links direkt auf Bilddateien */
    document.querySelectorAll('a[href]').forEach(function (a) {
        if (/\.(jpe?g|png|webp|gif|avif)(\?|#|$)/i.test(a.href)) add(a.href, a.title || a.textContent);
    });
    /* Bilder auf der Seite: größte Fassung aus srcset, Vorschauen über Proxys ausgepackt, kleine Symbole nicht */
    document.querySelectorAll('img').forEach(function (img) {
        var b = bestSrc(img), u = b[0];
        var inner = /external-content\.duckduckgo\.com|\/iu\/\?u=|imgs\.search\.brave\.com/.test(u) ? unwrap(u) : '';
        if (inner && !/tse\d*\.(mm\.)?bing\.net|th\.bing\.com/.test(inner)) u = inner;
        if (Math.max(b[1], img.naturalWidth || 0, img.naturalHeight || 0) >= 160) add(u, img.alt);
    });
    if (!list.length) { alert(L.none); return; }

    var cur = 0, startX = 0, startY = 0, idle = 0;
    var box = document.createElement('div');
    box.id = 'sm-big';
    box.setAttribute('style', 'position:fixed;inset:0;z-index:2147483647;background:#000;display:flex;align-items:center;justify-content:center;touch-action:none;font:14px/1.4 system-ui,sans-serif;color:#fff');
    var img = document.createElement('img');
    img.setAttribute('style', 'max-width:100%;max-height:100%;width:100%;height:100%;object-fit:contain;display:block');
    img.referrerPolicy = 'no-referrer';
    var cap = document.createElement('div');
    cap.setAttribute('style', 'position:absolute;left:0;right:0;bottom:0;padding:30px 16px max(12px,env(safe-area-inset-bottom));text-align:center;background:linear-gradient(transparent,rgba(0,0,0,.6));white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:opacity .3s');
    function btn(txt, css, fn, title) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = txt;
        b.title = title || '';
        b.setAttribute('style', 'position:absolute;' + css + ';width:48px;height:48px;border:0;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;font-size:24px;line-height:48px;cursor:pointer;transition:opacity .3s');
        b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
        box.appendChild(b);
        return b;
    }
    box.appendChild(img);
    box.appendChild(cap);
    var bPrev = btn('‹', 'left:max(12px,env(safe-area-inset-left));top:50%;margin-top:-24px', function () { go(-1); }, L.prev);
    var bNext = btn('›', 'right:max(12px,env(safe-area-inset-right));top:50%;margin-top:-24px', function () { go(1); }, L.next);
    var bClose = btn('×', 'right:max(12px,env(safe-area-inset-right));top:max(12px,env(safe-area-inset-top))', close, L.close);
    var bFull = btn('⛶', 'right:max(70px,calc(env(safe-area-inset-right) + 58px));top:max(12px,env(safe-area-inset-top));font-size:20px', full, L.full);
    var bOpen = btn('↗', 'right:max(128px,calc(env(safe-area-inset-right) + 116px));top:max(12px,env(safe-area-inset-top));font-size:20px', function () { window.open(list[cur].u, '_blank', 'noopener'); }, L.open);
    var ctrls = [cap, bPrev, bNext, bClose, bFull, bOpen];

    function show(i) {
        cur = (i + list.length) % list.length;
        var it = list[cur];
        img.onerror = function () { cap.textContent = (cur + 1) + ' / ' + list.length + ' · ' + L.broken; };
        img.src = it.u;
        cap.textContent = (cur + 1) + ' / ' + list.length + (it.t ? ' · ' + it.t : '');
        var pre = new Image();
        pre.referrerPolicy = 'no-referrer';
        pre.src = list[(cur + 1) % list.length].u;
        wake();
    }
    function go(d) { show(cur + d); }
    function full() {
        var fe = document.fullscreenElement || document.webkitFullscreenElement;
        if (fe) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
        var rq = box.requestFullscreen || box.webkitRequestFullscreen;
        if (rq) { try { var p = rq.call(box); if (p && p.catch) p.catch(function () {}); } catch (e) {} }
    }
    function close() {
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
        else if (e.key === 'Escape' && !(document.fullscreenElement || document.webkitFullscreenElement)) close();
        else if (e.key === 'f' || e.key === 'F') full();
        else return;
        e.preventDefault();
        e.stopPropagation();
    }
    box.addEventListener('mousemove', wake);
    box.addEventListener('click', function (e) { if (e.target === box || e.target === img) { var r = box.getBoundingClientRect(); go(e.clientX > r.width / 2 ? 1 : -1); } });
    box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; wake(); }, { passive: true });
    box.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - startX, dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(dx < 0 ? 1 : -1);
        else if (dy > 90) close();
    });
    document.addEventListener('keydown', key, true);
    var ov = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    document.body.appendChild(box);
    show(0);
})();
