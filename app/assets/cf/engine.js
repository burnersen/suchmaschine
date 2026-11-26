/* CodeForge – Verbindung zur Scan-Engine
 *
 * Die Engine (zxing-wasm) läuft in einem Web Worker. Klappt das nicht, läuft sie im Hauptfenster.
 * read(): Bild lesen, write(): DataMatrix, Aztec, PDF417, Micro QR, rMQR erzeugen.
 */
(function (root) {
'use strict';
const CF = root.CF = root.CF || {};

let worker = null, seq = 0, inline = null, state = 'idle', readyPromise = null;
const waiting = new Map();

function version() { return (CF.boot && CF.boot.v) || ''; }
function assetUrl(name) { return (CF.boot && CF.boot.base || './') + '?a=asset&f=' + (name === 'worker.js' ? 'cf/' : 'vendor/') + name + '&v=' + encodeURIComponent(version()); }

function startWorker() {
    try {
        worker = new Worker(assetUrl('worker.js'));
        worker.onmessage = (e) => {
            const w = waiting.get(e.data.id);
            if (!w) return;
            waiting.delete(e.data.id);
            e.data.ok ? w.resolve(e.data) : w.reject(new Error(e.data.error || 'engine'));
        };
        worker.onerror = () => { fallback(); };
        return true;
    } catch (err) {
        return false;
    }
}

/** Ersatz ohne Worker: Bibliothek als Skript laden */
function fallback() {
    if (inline) return inline;
    if (worker) { try { worker.terminate(); } catch (e) { /* egal */ } worker = null; }
    inline = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = assetUrl('zxing.js');
        s.onload = () => {
            root.ZXingWASM.prepareZXingModule({ overrides: { locateFile: (p) => (p.endsWith('.wasm') ? assetUrl('zxing.wasm') : p) } });
            resolve(root.ZXingWASM);
        };
        s.onerror = reject;
        document.head.appendChild(s);
    });
    // Wartende Aufträge neu schicken
    const pending = Array.from(waiting.values());
    waiting.clear();
    pending.forEach(p => call(p.msg).then(p.resolve, p.reject));
    return inline;
}

async function callInline(msg) {
    const Z = await inline;
    if (msg.op === 'warm') { await Z.prepareZXingModule({ fireImmediately: true }); return { ok: true }; }
    if (msg.op === 'read') {
        const o = msg.opts || {};
        const res = await Z.readBarcodes(msg.image, { formats: o.formats || [], tryHarder: o.tryHarder !== false, isPure: !!o.isPure, maxNumberOfSymbols: o.max || 16, textMode: 'HRI' });
        return { ok: true, results: res.filter(r => r.isValid).map(r => ({ format: r.format, text: r.text, contentType: r.contentType, ecLevel: r.ecLevel, version: r.version, sid: r.symbologyIdentifier, position: r.position, symbol: r.symbol && r.symbol.width ? { width: r.symbol.width, height: r.symbol.height, data: Array.from(r.symbol.data) } : null })) };
    }
    if (msg.op === 'write') {
        const r = await Z.writeBarcode(msg.text, { format: msg.format, options: msg.options || '', scale: 1, addQuietZones: false });
        if (r.error) throw new Error(r.error);
        return { ok: true, symbol: { width: r.symbol.width, height: r.symbol.height, data: Array.from(r.symbol.data) } };
    }
    throw new Error('op');
}

function call(msg, transfer) {
    if (!worker && !inline && !startWorker()) fallback();
    if (inline) return callInline(msg);
    return new Promise((resolve, reject) => {
        const id = ++seq;
        msg.id = id;
        waiting.set(id, { resolve, reject, msg });
        try { worker.postMessage(msg, transfer || []); } catch (e) { waiting.delete(id); reject(e); }
    });
}

function ready() {
    if (!readyPromise) {
        state = 'loading';
        readyPromise = call({ op: 'warm' }).then(() => { state = 'ready'; }, (e) => { state = 'error'; readyPromise = null; throw e; });
    }
    return readyPromise;
}

/** Liest ImageData (oder {data,width,height}). opts: formats, tryHarder, isPure, max, symbol */
async function read(image, opts) {
    const copy = { data: image.data, width: image.width, height: image.height, colorSpace: 'srgb' };
    const r = await call({ op: 'read', image: copy, opts: opts || {} }, worker && image.data.buffer ? [image.data.buffer] : []);
    return r.results || [];
}

async function write(text, format, options) {
    const r = await call({ op: 'write', text, format, options: options || '' });
    return r.symbol;
}

/** Bilddatei (auch Fotos vom Handy) in ImageData umwandeln, große Bilder werden verkleinert */
async function imageDataFromBlob(blob, maxSide) {
    maxSide = maxSide || 2400;
    let src, w, h;
    if (root.createImageBitmap) {
        try { src = await createImageBitmap(blob); w = src.width; h = src.height; } catch (e) { src = null; }
    }
    if (!src) {
        src = await new Promise((resolve, reject) => {
            const url = URL.createObjectURL(blob);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
            img.src = url;
        });
        w = src.naturalWidth; h = src.naturalHeight;
    }
    const k = Math.min(1, maxSide / Math.max(w, h));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * k));
    c.height = Math.max(1, Math.round(h * k));
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(src, 0, 0, c.width, c.height);
    if (src.close) src.close();
    return { image: ctx.getImageData(0, 0, c.width, c.height), canvas: c };
}

async function readBlob(blob) {
    const { image, canvas } = await imageDataFromBlob(blob);
    const res = await read(image, { tryHarder: true, tryDenoise: true });
    return { results: res, canvas };
}

function readCanvas(canvas, opts) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    return read(ctx.getImageData(0, 0, canvas.width, canvas.height), opts);
}

CF.Engine = { ready, read, write, readBlob, readCanvas, imageDataFromBlob, get state() { return state; } };
})(typeof self !== 'undefined' ? self : globalThis);
