/* Suchmaschine: Artikel aus dem Lesemodus als PDF, ohne fremde Bibliothek.
   Grundlage ist der PDF-Baukasten aus „Kaufen“ (Schriftbreiten, WinAnsi-Kodierung, PDF-Schreiber), hier für Artikel mit
   Überschriften, Absätzen, Listen, Zitaten und Bildern (JPEG, unverändert eingebettet). Eine oder zwei Spalten, Schriftgröße,
   Zeilenabstand, Ränder. Auf jeder Seite stehen Quelle und Hinweis „nur zum privaten Gebrauch“.
   Die Schriften sind die drei, die jedes PDF-Programm mitbringt (Helvetica, Times, Courier): Umlaute, ß und € gehen,
   Zeichen außerhalb von Westeuropa (z. B. Kyrillisch, Emojis) fallen weg. Dafür gibt es den Druckknopf des Browsers.
   smPdfLayout(d) liefert das Layout mit .pages, .draw(ctx, seite) und .bytes(). */
(function () {
    'use strict';
    // Breiten der Zeichen 32 bis 255 in Tausendsteln der Schriftgröße (aus den Adobe-Metriken, WinAnsi). Courier ist überall 600 breit.
    const W = {
        r: [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500],
        b: [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556],
        tr: [250,333,408,500,500,833,778,180,333,333,500,564,250,333,250,278,500,500,500,500,500,500,500,500,500,500,278,278,564,564,564,444,921,722,667,667,722,611,556,722,722,333,389,722,611,889,722,722,556,722,667,556,611,722,722,944,722,722,611,333,278,333,469,500,333,444,500,444,500,444,333,500,500,278,278,500,278,778,500,500,500,500,333,389,278,500,500,722,500,500,444,480,200,480,541,350,500,350,333,500,444,1000,500,500,333,1000,556,333,889,350,611,350,350,333,333,444,444,350,500,1000,333,980,389,333,722,350,444,722,250,333,500,500,500,500,200,500,333,760,276,500,564,333,760,333,400,564,300,300,333,500,453,250,333,300,310,500,750,750,750,444,722,722,722,722,722,722,889,667,611,611,611,611,333,333,333,333,722,722,722,722,722,722,722,564,722,722,722,722,722,722,556,500,444,444,444,444,444,444,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,564,500,500,500,500,500,500,500,500],
        tb: [250,333,555,500,500,1000,833,278,333,333,500,570,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,570,570,570,500,930,722,667,722,722,667,611,778,778,389,500,778,667,944,722,778,611,778,722,556,667,722,722,1000,722,722,667,333,278,333,581,500,333,500,556,444,556,444,333,500,556,278,333,556,278,833,556,500,556,556,444,389,333,556,500,722,500,500,444,394,220,394,520,350,500,350,333,500,500,1000,500,500,333,1000,556,333,1000,350,667,350,350,333,333,500,500,350,500,1000,333,1000,389,333,722,350,444,722,250,333,500,500,500,500,220,500,333,747,300,500,570,333,747,333,400,570,300,300,333,556,540,250,333,300,330,500,750,750,750,500,722,722,722,722,722,722,1000,722,667,667,667,667,389,389,389,389,722,722,778,778,778,778,778,570,778,722,722,722,722,722,611,556,500,500,500,500,500,500,722,444,444,444,444,444,278,278,278,278,500,556,500,500,500,500,500,570,500,556,556,556,556,500,556,500],
    };
    const FONTS = {
        helvetica: { r: 'Helvetica', b: 'Helvetica-Bold', wr: 'r', wb: 'b', css: 'Helvetica, Arial, "Liberation Sans", "Nimbus Sans", sans-serif' },
        times: { r: 'Times-Roman', b: 'Times-Bold', wr: 'tr', wb: 'tb', css: '"Times New Roman", Times, "Liberation Serif", "Nimbus Roman", serif' },
        courier: { r: 'Courier', b: 'Courier-Bold', wr: '', wb: '', css: '"Courier New", Courier, "Liberation Mono", "Nimbus Mono PS", monospace' },
    };
    // Zeichen, die WinAnsi zwischen 128 und 159 anders belegt als Unicode
    const WIN = { '€': 128, '‚': 130, 'ƒ': 131, '„': 132, '…': 133, '†': 134, '‡': 135, 'ˆ': 136, '‰': 137, 'Š': 138, '‹': 139, 'Œ': 140, 'Ž': 142,
        '‘': 145, '’': 146, '“': 147, '”': 148, '•': 149, '–': 150, '—': 151, '˜': 152, '™': 153, 'š': 154, '›': 155, 'œ': 156, 'ž': 158, 'Ÿ': 159 };
    const UNWIN = {};
    for (const ch in WIN) UNWIN[WIN[ch]] = ch;

    /** Text in WinAnsi-Bytes (als String mit Zeichencodes unter 256). Unbekanntes ohne Akzent, sonst weg. */
    function enc(s) {
        let out = '';
        for (const ch of String(s == null ? '' : s).replace(/\s+/g, ' ')) {
            const c = ch.codePointAt(0);
            if ((c >= 32 && c < 127) || (c >= 160 && c < 256)) out += ch;
            else if (WIN[ch]) out += String.fromCharCode(WIN[ch]);
            else {
                const base = ch.normalize('NFD').replace(/[̀-ͯ]/g, '');
                if (base && base !== ch && base.codePointAt(0) < 256) out += enc(base);
            }
        }
        return out.replace(/ {2,}/g, ' ').trim();
    }
    /** Zurück in Unicode, für die Vorschau im Browser */
    const dec = s => s.replace(/[\x80-\x9f]/g, ch => UNWIN[ch.charCodeAt(0)] || '');
    const esc = s => s.replace(/[\\()]/g, '\\$&');
    const f = n => (Math.round(n * 100) / 100).toString();
    const clamp = (v, lo, hi, d) => (isFinite(v) && v !== null && v !== '' ? Math.min(hi, Math.max(lo, +v)) : d);


    // Seite A4 in Punkt (1/72 Zoll). Ränder: schmal, normal, breit
    const PW = 595.28, PH = 841.89;
    const MARGINS = { narrow: 32, normal: 48, wide: 68 };

    /**
     * Layout eines Artikels. d = { title, meta, source, note, page: (i, n) => text, blocks: [{ t, x | img }], opt: { font, size, space, cols, margin } }
     * Blöcke: h, h3 (Überschriften), p, li, oli, quote, pre, cap (Bildunterschrift), img ({ img: { data (JPEG-Bytes als String), w, h, el (für die Vorschau) } })
     */
    function layout(d) {
        const o = d.opt || {};
        const fam = FONTS[o.font] ? o.font : 'helvetica', F = FONTS[fam];
        const fs = clamp(o.size, .7, 1.6, 1), sp = clamp(o.space, .8, 2, 1.3), N = +o.cols === 2 ? 2 : 1;
        const M = MARGINS[o.margin] || MARGINS.normal, MT = M, MB = M + 14;
        const width = (s, size, bold) => {
            const t = W[bold ? F.wb : F.wr];
            let w = 0;
            for (let i = 0; i < s.length; i++) w += t ? t[s.charCodeAt(i) - 32] || 556 : 600;
            return w * size / 1000;
        };
        function wrap(s, size, bold, max) {
            const lines = [];
            let line = '';
            for (let word of s.split(' ')) {
                while (width(word, size, bold) > max) {
                    let k = word.length - 1;
                    while (k > 1 && width(word.slice(0, k), size, bold) > max) k--;
                    if (line) { lines.push(line); line = ''; }
                    lines.push(word.slice(0, k));
                    word = word.slice(k);
                }
                const t = line ? line + ' ' + word : word;
                if (line && width(t, size, bold) > max) { lines.push(line); line = word; } else line = t;
            }
            if (line || !lines.length) lines.push(line);
            return lines;
        }
        const GUT = 24, CW = (PW - 2 * M - GUT * (N - 1)) / N;
        const BODY = 10.5 * fs, LH = BODY * sp;
        const STY = {
            h: { size: 14 * fs, bold: true, before: 12 * fs, after: 4 * fs },
            h3: { size: 12 * fs, bold: true, before: 9 * fs, after: 3 * fs },
            p: { size: BODY, before: 0, after: 6 * fs },
            li: { size: BODY, before: 0, after: 2.5 * fs, indent: 14 * fs },
            oli: { size: BODY, before: 0, after: 3.5 * fs, indent: 18 * fs },
            quote: { size: BODY, before: 2 * fs, after: 7 * fs, indent: 14 * fs, g: .3 },
            pre: { size: 9 * fs, before: 2 * fs, after: 7 * fs, indent: 8 * fs, mono: true },
            cap: { size: 8.5 * fs, before: 0, after: 8 * fs, g: .4 },
        };
        const title = enc(d.title || ''), meta = enc(d.meta || ''), source = enc(d.source || ''), note = enc(d.note || '');

        /* ---------- 1. Bausteine mit Höhe ---------- */
        const items = [];
        let n = 0;
        for (const b of d.blocks || []) {
            if (b.t === 'img') {
                if (!b.img || !b.img.w) continue;
                const maxH = (PH - MT - MB) * .45;
                let w = Math.min(CW, b.img.w * .75), h = w * b.img.h / b.img.w;
                if (h > maxH) { h = maxH; w = h * b.img.w / b.img.h; }
                items.push({ t: 'img', img: b.img, w, h, before: 4 * fs, after: 6 * fs });
                continue;
            }
            const st = STY[b.t] || STY.p;
            if (b.t === 'oli') n++; else if (b.t !== 'li') n = 0;
            const ind = st.indent || 0, size = st.size, lh = size * (b.t === 'h' || b.t === 'h3' ? 1.25 : sp);
            const lines = wrap(enc(b.x), size, !!st.bold, CW - ind);
            // Lange Absätze dürfen über Spalten und Seiten laufen: Zeile für Zeile
            lines.forEach((s, j) => items.push({ t: 'line', s, size, bold: !!st.bold, g: st.g || 0, x: ind, lh,
                bullet: j === 0 ? (b.t === 'li' ? '\x95' : b.t === 'oli' ? n + '.' : '') : '',
                before: j === 0 ? st.before : 0, after: j === lines.length - 1 ? st.after : 0, keep: b.t === 'h' || b.t === 'h3' }));
        }

        /* ---------- 2. Auf Spalten und Seiten verteilen ---------- */
        const HEAD1 = (() => {   // Kopf der ersten Seite: Titel, Meta, Linie
            const tl = wrap(title, 19 * fs, true, PW - 2 * M);
            return { tl, h: tl.length * 23 * fs + 18 + 16 };
        })();
        const pages = [];
        let page = null, col = 0, y = 0;
        const top = () => (pages.length === 1 ? MT + HEAD1.h : MT + 22);
        const colH = () => PH - MB - top();
        const newPage = () => { page = { cols: Array.from({ length: N }, () => []) }; pages.push(page); col = 0; y = 0; };
        const next = () => { if (col < N - 1) { col++; y = 0; } else newPage(); };
        newPage();
        for (let i = 0; i < items.length; i++) {
            const it = items[i];
            const h = it.t === 'img' ? it.h : it.lh;
            let need = (y ? it.before : 0) + h;
            // Überschrift nicht allein unten stehen lassen
            if (it.keep && items[i + 1]) need += items[i + 1].t === 'img' ? items[i + 1].h : items[i + 1].lh * 2;
            if (y && y + need > colH()) next();
            const gap = y ? it.before : 0;
            page.cols[col].push({ it, y: y + gap });
            y += gap + h + it.after;
        }

        /* ---------- 3. Zeichenbefehle ---------- */
        const ops = pages.map((p, pi) => {
            const o = [];
            const text = (x, yy, s, size, bold, g, r) => o.push({ k: 't', x, y: yy, s, size, bold: !!bold, g: g || 0, r: !!r });
            const line = (x1, y1, x2, y2, w, g) => o.push({ k: 'l', x1, y1, x2, y2, w, g });
            if (pi === 0) {
                HEAD1.tl.forEach((s, j) => text(M, MT + 18 * fs + j * 23 * fs, s, 19 * fs, true));
                const my = MT + HEAD1.tl.length * 23 * fs + 8;
                text(M, my, meta, 9, false, .4);
                line(M, my + 10, PW - M, my + 10, 1, 0);
            } else {
                text(M, MT + 8, title.length > 90 ? title.slice(0, 88) + '\x85' : title, 9, true, .3);
                line(M, MT + 14, PW - M, MT + 14, .5, .6);
            }
            const t0 = pi === 0 ? MT + HEAD1.h : MT + 22;
            p.cols.forEach((c, ci) => {
                const x0 = M + ci * (CW + GUT);
                c.forEach(({ it, y }) => {
                    if (it.t === 'img') { o.push({ k: 'i', x: x0 + (CW - it.w) / 2, y: t0 + y, w: it.w, h: it.h, img: it.img }); return; }
                    const base = t0 + y + it.size * .95;
                    if (it.bullet) text(x0 + it.x - (it.bullet === '\x95' ? 10 : 16) * fs, base, it.bullet, it.size, false, it.g);
                    text(x0 + it.x, base, it.s, it.size, it.bold, it.g);
                });
            });
            if (N === 2) line(M + CW + GUT / 2, t0, M + CW + GUT / 2, PH - MB, .4, .8);
            // Fuß: Quelle, Hinweis, Seitenzahl. Steht auf jeder Seite, damit die Herkunft nie verloren geht.
            line(M, PH - MB + 12, PW - M, PH - MB + 12, .4, .75);
            const pg = d.page ? enc(d.page(pi + 1, pages.length)) : '';
            const room = PW - 2 * M - width(pg, 7.5) - 12;
            let src = source;
            while (src.length > 10 && width(src, 7.5) > room) src = src.slice(0, -2);
            if (src !== source) src += '\x85';
            text(M, PH - MB + 23, src, 7.5, false, .45);
            if (note) text(M, PH - MB + 33, note, 7.5, false, .45);
            if (pg) text(PW - M, PH - MB + 23, pg, 7.5, false, .45, true);
            return o;
        });

        /* ---------- 4a. PDF-Datei ---------- */
        function bytes() {
            const imgs = [];
            const imgName = img => { let k = imgs.indexOf(img); if (k < 0) { imgs.push(img); k = imgs.length - 1; } return '/Im' + (k + 1); };
            const streams = ops.map(list => list.map(op => {
                if (op.k === 't') {
                    const x = op.r ? op.x - width(op.s, op.size, op.bold) : op.x;
                    return (op.g ? f(op.g) + ' g ' : '') + 'BT /' + (op.bold ? 'F2 ' : 'F1 ') + f(op.size) + ' Tf ' + f(x) + ' ' + f(PH - op.y) + ' Td (' + esc(op.s) + ') Tj ET' + (op.g ? ' 0 g' : '');
                }
                if (op.k === 'l') return 'q ' + f(op.w) + ' w ' + f(op.g) + ' G ' + f(op.x1) + ' ' + f(PH - op.y1) + ' m ' + f(op.x2) + ' ' + f(PH - op.y2) + ' l S Q';
                return 'q ' + f(op.w) + ' 0 0 ' + f(op.h) + ' ' + f(op.x) + ' ' + f(PH - op.y - op.h) + ' cm ' + imgName(op.img) + ' Do Q';
            }).join('\n'));
            const objs = [];
            const add = s => { objs.push(s); return objs.length; };
            const cat = add(''), pagesId = add('');
            const f1 = add('<< /Type /Font /Subtype /Type1 /BaseFont /' + F.r + ' /Encoding /WinAnsiEncoding >>');
            const f2 = add('<< /Type /Font /Subtype /Type1 /BaseFont /' + F.b + ' /Encoding /WinAnsiEncoding >>');
            const imIds = imgs.map(im => add('<< /Type /XObject /Subtype /Image /Width ' + im.pw + ' /Height ' + im.ph + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + im.data.length + ' >>\nstream\n' + im.data + '\nendstream'));
            const xo = imIds.length ? ' /XObject << ' + imIds.map((id, i) => '/Im' + (i + 1) + ' ' + id + ' 0 R').join(' ') + ' >>' : '';
            const kids = streams.map(s => {
                const c = add('<< /Length ' + s.length + ' >>\nstream\n' + s + '\nendstream');
                return add('<< /Type /Page /Parent ' + pagesId + ' 0 R /MediaBox [0 0 ' + f(PW) + ' ' + f(PH) + '] /Resources << /Font << /F1 ' + f1 + ' 0 R /F2 ' + f2 + ' 0 R >>' + xo + ' >> /Contents ' + c + ' 0 R >>');
            });
            objs[cat - 1] = '<< /Type /Catalog /Pages ' + pagesId + ' 0 R >>';
            objs[pagesId - 1] = '<< /Type /Pages /Kids [' + kids.map(k => k + ' 0 R').join(' ') + '] /Count ' + kids.length + ' >>';
            const info = add('<< /Title (' + esc(title) + ') /Subject (' + esc(source) + ') /Producer (Suchmaschine) >>');
            let out = '%PDF-1.4\n%\xe2\xe3\xcf\xd3\n';
            const offs = objs.map((s, i) => { const at = out.length; out += (i + 1) + ' 0 obj\n' + s + '\nendobj\n'; return at; });
            const xref = out.length;
            out += 'xref\n0 ' + (objs.length + 1) + '\n0000000000 65535 f \n' + offs.map(n => String(n).padStart(10, '0') + ' 00000 n \n').join('');
            out += 'trailer\n<< /Size ' + (objs.length + 1) + ' /Root ' + cat + ' 0 R /Info ' + info + ' 0 R >>\nstartxref\n' + xref + '\n%%EOF\n';
            const res = new Uint8Array(out.length);
            for (let i = 0; i < out.length; i++) res[i] = out.charCodeAt(i) & 255;
            return res;
        }

        /* ---------- 4b. Vorschau auf Canvas (1 Einheit = 1 Punkt) ---------- */
        function draw(ctx, i) {
            const gray = g => { const v = Math.round(g * 255); return 'rgb(' + v + ',' + v + ',' + v + ')'; };
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, PW, PH);
            for (const op of ops[i] || []) {
                if (op.k === 't') {
                    ctx.font = (op.bold ? 'bold ' : '') + op.size + 'px ' + F.css;
                    ctx.fillStyle = gray(op.g);
                    ctx.textAlign = op.r ? 'right' : 'left';
                    ctx.fillText(dec(op.s), op.x, op.y);
                    ctx.textAlign = 'left';
                } else if (op.k === 'l') {
                    ctx.strokeStyle = gray(op.g);
                    ctx.lineWidth = op.w;
                    ctx.beginPath(); ctx.moveTo(op.x1, op.y1); ctx.lineTo(op.x2, op.y2); ctx.stroke();
                } else if (op.img.el) {
                    ctx.drawImage(op.img.el, op.x, op.y, op.w, op.h);
                }
            }
        }
        return { pages: ops.length, width: PW, height: PH, draw, bytes };
    }

    self.smPdfLayout = layout;
})();
