/* CodeForge – Scan-Engine im Hintergrund (Web Worker)
 *
 * Lädt zxing-wasm (zxing-cpp als WebAssembly, liegt auf dem eigenen Server) und erledigt
 * Lesen und Schreiben, ohne die Oberfläche zu bremsen.
 */
'use strict';
const V = new URLSearchParams(self.location.search).get('v') || '';
const assetUrl = name => new URL('?a=asset&f=vendor/' + name + '&v=' + encodeURIComponent(V), self.location.href).href;
importScripts(assetUrl('zxing.js'));

ZXingWASM.prepareZXingModule({
    overrides: { locateFile: (path) => (path.endsWith('.wasm') ? assetUrl('zxing.wasm') : path) },
});

const MATRIX = ['QRCode', 'MicroQRCode', 'RMQRCode', 'DataMatrix', 'Aztec', 'PDF417', 'CompactPDF417', 'MicroPDF417', 'MaxiCode'];

function b64(bytes) {
    let s = '';
    for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
    return btoa(s);
}

function slim(r, withSymbol) {
    const out = {
        format: r.format, text: r.text, contentType: r.contentType, ecLevel: r.ecLevel, version: r.version,
        sid: r.symbologyIdentifier, position: r.position, mirrored: r.isMirrored, inverted: r.isInverted,
        orientation: r.orientation, hasECI: r.hasECI,
        seq: r.sequenceSize > 1 ? { size: r.sequenceSize, index: r.sequenceIndex, id: r.sequenceId } : null,
    };
    if (r.bytes && r.bytes.length && r.bytes.length <= 8192 && (r.contentType === 'Binary' || r.contentType === 'Mixed' || r.contentType === 'UnknownECI')) out.bytes = b64(r.bytes);
    if (withSymbol && r.symbol && r.symbol.width && MATRIX.indexOf(r.format) >= 0 && r.symbol.width * r.symbol.height <= 40000) {
        out.symbol = { width: r.symbol.width, height: r.symbol.height, data: Array.from(r.symbol.data) };
    }
    return out;
}

self.onmessage = async (e) => {
    const msg = e.data || {};
    const { id, op } = msg;
    try {
        if (op === 'warm') {
            await ZXingWASM.prepareZXingModule({ fireImmediately: true });
            self.postMessage({ id, ok: true });
        } else if (op === 'read') {
            const o = msg.opts || {};
            const res = await ZXingWASM.readBarcodes(msg.image, {
                formats: o.formats || [],
                tryHarder: o.tryHarder !== false,
                tryRotate: o.tryRotate !== false,
                tryInvert: o.tryInvert !== false,
                tryDownscale: true,
                tryDenoise: !!o.tryDenoise,
                isPure: !!o.isPure,
                maxNumberOfSymbols: o.max || 16,
                textMode: 'HRI',
            });
            self.postMessage({ id, ok: true, results: res.filter(r => r.isValid).map(r => slim(r, o.symbol !== false)) });
        } else if (op === 'write') {
            const r = await ZXingWASM.writeBarcode(msg.text, { format: msg.format, options: msg.options || '', scale: 1, addQuietZones: false });
            if (r.error || !r.symbol || !r.symbol.width) self.postMessage({ id, ok: false, error: r.error || 'write' });
            else self.postMessage({ id, ok: true, symbol: { width: r.symbol.width, height: r.symbol.height, data: Array.from(r.symbol.data) } });
        } else {
            self.postMessage({ id, ok: false, error: 'op' });
        }
    } catch (err) {
        self.postMessage({ id, ok: false, error: String((err && err.message) || err) });
    }
};
