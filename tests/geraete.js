/**
 * Suchmaschine – Bedienbarkeit auf Geräten prüfen (Handy, Tablet, Rechner). Braucht Node.js und Playwright mit Chromium.
 *
 *   BASE=http://127.0.0.1:8765/ SM_USER=Admin PASS=geheim node tests/geraete.js
 *   DEVS="iPhone SE,Pixel 7" ONLY="tool=timer" SHOTS=1 OUT=/tmp/geraete node tests/geraete.js
 *
 * Ruft jede Seite und jedes Werkzeug als angemeldeter Administrator auf und meldet pro Gerät:
 *   keyboard  – beim Laden hat ein Eingabefeld den Fokus (am Handy geht dann ungefragt die Tastatur auf)
 *   overflow  – die Seite ist breiter als der Bildschirm (seitliches Wackeln)
 *   small     – Knöpfe oder Links kleiner als 24 px (schwer zu treffen)
 *   zoomInput – Felder mit weniger als 16 px Schrift (das iPhone zoomt beim Antippen hinein)
 *   errors    – JavaScript-Fehler
 * Am Rechner (Desktop) ist der Fokus im Suchfeld gewollt.
 */
const { chromium, devices } = require('playwright');
const fs = require('fs');
const D = (process.env.OUT || require('os').tmpdir() + '/sm-geraete') + '/';
fs.mkdirSync(D, { recursive: true });
const B = process.env.BASE || 'http://127.0.0.1:8765/';
const DEV = (process.env.DEVS || 'iPhone SE,Pixel 7,iPad Mini,Desktop').split(',');
const ONLY = process.env.ONLY ? new RegExp(process.env.ONLY) : null;
const SHOTS = !!process.env.SHOTS;

(async () => {
  const br = await chromium.launch();
  const report = {};
  for (const dn of DEV) {
    const opt = dn === 'Desktop' ? { viewport: { width: 1366, height: 860 } } : Object.assign({}, devices[dn]);
    delete opt.defaultBrowserType;
    const ctx = await br.newContext(opt);
    const pg = await ctx.newPage();
    let errs = [];
    pg.on('pageerror', e => errs.push('JS: ' + e.message));
    pg.on('console', m => { if (m.type() === 'error' && !/Content Security Policy|favicon|net::ERR/.test(m.text())) errs.push('console: ' + m.text().slice(0, 160)); });
    await pg.goto(B + '?a=login');
    await pg.fill('input[name=name]', process.env.USER_NAME || process.env.SM_USER || 'Admin');
    await pg.fill('input[name=pass]', process.env.PASS || 'geheim-123');
    await Promise.all([pg.waitForNavigation(), pg.click('button[type=submit]')]);
    let pages = ['', '?a=help', '?a=tools', '?q=2%2B3*4', '?q=wetter+berlin', '?a=settings', '?a=admin'];
    for (const t of ['look', 'domains', 'bangs', 'lenses', 'browser', 'account']) pages.push('?a=settings&tab=' + t);
    pages.push('?a=settings&tab=look&for=all', '?a=settings&tab=bangs&bq=wiki');
    for (const t of ['users', 'providers', 'usage', 'config', 'backup', 'update']) pages.push('?a=admin&tab=' + t);
    await pg.goto(B + '?a=tools');
    const tools = await pg.$$eval('[data-tool]', l => [...new Set(l.map(x => x.getAttribute('data-tool')))]);
    for (const t of tools) pages.push('?a=tools&tool=' + t);
    if (ONLY) pages = pages.filter(p => ONLY.test(p));
    for (const p of pages) {
      errs = [];
      try { await pg.goto(B + p, { waitUntil: 'load', timeout: 20000 }); } catch (e) { errs.push('Laden: ' + e.message.slice(0, 80)); }
      await pg.waitForTimeout(900);
      const r = await pg.evaluate(() => {
        const out = {};
        const a = document.activeElement;
        if (a && (a.tagName === 'TEXTAREA' || (a.tagName === 'INPUT' && !/^(button|submit|checkbox|radio|range|color|file|reset|hidden)$/.test(a.type))) && !a.readOnly && a.inputMode !== 'none')
          out.keyboard = a.tagName + '[' + (a.name || a.type || '') + ']' + (a.placeholder ? ' "' + a.placeholder.slice(0, 30) + '"' : '');
        const W = innerWidth;
        if (document.documentElement.scrollWidth > W + 1) {
          const wide = [];
          document.querySelectorAll('body *').forEach(e => {
            const b = e.getBoundingClientRect();
            if (b.right > W + 1 && b.width > 0 && getComputedStyle(e).position !== 'fixed') {
              let s = e, inScroll = false;
              while ((s = s.parentElement)) { const o = getComputedStyle(s).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden') { inScroll = true; break; } }
              if (!inScroll) wide.push(e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '') + '(' + Math.round(b.right) + ')');
            }
          });
          out.overflow = document.documentElement.scrollWidth + '>' + W + ' ' + wide.slice(0, 4).join(' ');
        }
        const small = [];
        document.querySelectorAll('a[href], button, input, select, summary, [role=button]').forEach(e => {
          if (e.closest('[hidden]') || e.type === 'hidden') return;
          const b = e.getBoundingClientRect();
          if (b.width === 0 || b.height === 0) return;
          const st = getComputedStyle(e);
          if (st.visibility === 'hidden' || st.opacity === '0') return;
          if (e.tagName === 'A' && st.display === 'inline' && e.closest('p,li,td,small,span.muted')) return;   // Links im Fließtext
          if (/^(checkbox|radio)$/.test(e.type) && e.closest('label')) return;
          if (b.height < 24 || b.width < 24) small.push((e.tagName + '.' + (typeof e.className === 'string' ? e.className.split(' ')[0] : '')) + ' ' + Math.round(b.width) + 'x' + Math.round(b.height) + ' "' + (e.innerText || e.value || e.getAttribute('aria-label') || '').trim().slice(0, 18) + '"');
        });
        if (small.length) out.small = small.length + ': ' + [...new Set(small)].slice(0, 4).join(' | ');
        const fs = [];
        document.querySelectorAll('input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]), textarea, select').forEach(e => {
          const b = e.getBoundingClientRect();
          if (b.width && parseFloat(getComputedStyle(e).fontSize) < 16) fs.push(e.name || e.type);
        });
        if (fs.length) out.zoomInput = fs.length + ' Felder < 16px (iOS zoomt hinein): ' + [...new Set(fs)].slice(0, 5).join(',');
        return out;
      });
      if (errs.length) r.errors = [...new Set(errs)].slice(0, 3);
      if (Object.keys(r).length) (report[p] = report[p] || {})[dn] = r;
      if (SHOTS) await pg.screenshot({ path: D + dn.replace(/\W+/g, '_') + '_' + (p.replace(/\W+/g, '_') || 'home') + '.png' });
    }
    await ctx.close();
  }
  fs.writeFileSync(D + 'report.json', JSON.stringify(report, null, 1));
  for (const [p, v] of Object.entries(report)) {
    console.log('== ' + (p || '/'));
    for (const [d, r] of Object.entries(v)) console.log('   ' + d + ': ' + JSON.stringify(r));
  }
  await br.close();
})();
