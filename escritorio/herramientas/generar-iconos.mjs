#!/usr/bin/env node
/**
 * Genera escritorio/icono.png (256×256) e icono.ico (16…256) a partir de
 * public/favicon.svg sin dependencias nativas: interpreta el SVG (rectángulo
 * redondeado + trazo de la muela) con un rasterizador propio por distancia,
 * escribe PNG con zlib y empaqueta los PNG en el formato ICO.
 *
 *   node escritorio/herramientas/generar-iconos.mjs [--svg ruta] [--salida carpeta]
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const opcion = (nombre, porDefecto) => {
    const i = args.indexOf(nombre);
    return i >= 0 && args[i + 1] ? args[i + 1] : porDefecto;
};
const rutaSvg = path.resolve(opcion('--svg', path.join(aqui, '..', '..', 'public', 'favicon.svg')));
const salida = path.resolve(opcion('--salida', path.join(aqui, '..')));

// ---------------------------------------------------------------- SVG mínimo
function leerSvg(ruta) {
    const texto = fs.readFileSync(ruta, 'utf8');
    const viewBox = /viewBox="([^"]+)"/.exec(texto)?.[1].split(/[\s,]+/).map(Number) ?? [0, 0, 32, 32];
    const rect = /<rect\s+([^>]*)\/?>/.exec(texto)?.[1] ?? '';
    const atributo = (cadena, nombre, def) => {
        const m = new RegExp(`${nombre}="([^"]*)"`).exec(cadena);
        return m ? m[1] : def;
    };
    const rectangulo = {
        ancho: Number(atributo(rect, 'width', viewBox[2])),
        alto: Number(atributo(rect, 'height', viewBox[3])),
        radio: Number(atributo(rect, 'rx', 0)),
        color: atributo(rect, 'fill', '#0d9488'),
    };
    const trazos = [];
    for (const m of texto.matchAll(/<path\s+([^>]*)\/?>/g)) {
        const a = m[1];
        trazos.push({
            d: atributo(a, 'd', ''),
            color: atributo(a, 'stroke', '#ffffff'),
            grosor: Number(atributo(a, 'stroke-width', 1)),
            relleno: atributo(a, 'fill', 'none'),
        });
    }
    return { viewBox, rectangulo, trazos };
}

function hexARgb(hex) {
    const h = hex.replace('#', '');
    const c = h.length === 3 ? h.split('').map((x) => x + x).join('') : h;
    return [parseInt(c.slice(0, 2), 16), parseInt(c.slice(2, 4), 16), parseInt(c.slice(4, 6), 16)];
}

/** Convierte un atributo `d` (M/L/C/S/Z, absolutos y relativos) en polilíneas. */
function aplanarPath(d, pasos = 24) {
    const tokens = d.match(/[MmLlCcSsZz]|-?\d*\.?\d+(?:e-?\d+)?/g) ?? [];
    const polilineas = [];
    let actual = [];
    let x = 0, y = 0, inicioX = 0, inicioY = 0, ctrlX = null, ctrlY = null;
    let comando = null;
    let i = 0;
    const num = () => Number(tokens[i++]);

    const cubica = (x1, y1, x2, y2, x3, y3) => {
        for (let s = 1; s <= pasos; s++) {
            const t = s / pasos, u = 1 - t;
            const px = u * u * u * x + 3 * u * u * t * x1 + 3 * u * t * t * x2 + t * t * t * x3;
            const py = u * u * u * y + 3 * u * u * t * y1 + 3 * u * t * t * y2 + t * t * t * y3;
            actual.push([px, py]);
        }
        ctrlX = x2; ctrlY = y2; x = x3; y = y3;
    };

    while (i < tokens.length) {
        if (/[A-Za-z]/.test(tokens[i])) {
            comando = tokens[i++];
        }
        const rel = comando === comando.toLowerCase();
        switch (comando.toUpperCase()) {
            case 'M':
                if (actual.length) polilineas.push(actual);
                x = (rel ? x : 0) + num(); y = (rel ? y : 0) + num();
                inicioX = x; inicioY = y; ctrlX = ctrlY = null;
                actual = [[x, y]];
                comando = rel ? 'l' : 'L';
                break;
            case 'L':
                x = (rel ? x : 0) + num(); y = (rel ? y : 0) + num();
                ctrlX = ctrlY = null;
                actual.push([x, y]);
                break;
            case 'C': {
                const bx = rel ? x : 0, by = rel ? y : 0;
                cubica(bx + num(), by + num(), bx + num(), by + num(), bx + num(), by + num());
                break;
            }
            case 'S': {
                const bx = rel ? x : 0, by = rel ? y : 0;
                const x1 = ctrlX === null ? x : 2 * x - ctrlX;
                const y1 = ctrlY === null ? y : 2 * y - ctrlY;
                cubica(x1, y1, bx + num(), by + num(), bx + num(), by + num());
                break;
            }
            case 'Z':
                actual.push([inicioX, inicioY]);
                x = inicioX; y = inicioY; ctrlX = ctrlY = null;
                polilineas.push(actual);
                actual = [];
                break;
            default:
                throw new Error(`Comando SVG no soportado: ${comando}`);
        }
    }
    if (actual.length) polilineas.push(actual);
    return polilineas;
}

// ---------------------------------------------------------------- rasterizado
function distanciaSegmento(px, py, ax, ay, bx, by) {
    const dx = bx - ax, dy = by - ay;
    const l2 = dx * dx + dy * dy;
    let t = l2 === 0 ? 0 : ((px - ax) * dx + (py - ay) * dy) / l2;
    t = Math.max(0, Math.min(1, t));
    const cx = ax + t * dx - px, cy = ay + t * dy - py;
    return Math.sqrt(cx * cx + cy * cy);
}

function rasterizar(svg, tamano, muestras = 4) {
    const [vx, vy, vw, vh] = svg.viewBox;
    const escala = tamano / vw;
    const rect = svg.rectangulo;
    const colorRect = hexARgb(rect.color);
    const trazos = svg.trazos.map((t) => ({
        color: hexARgb(t.color),
        radio: t.grosor / 2,
        segmentos: aplanarPath(t.d).flatMap((pl) => pl.slice(1).map((p, k) => [pl[k][0], pl[k][1], p[0], p[1]])),
    }));

    const datos = Buffer.alloc(tamano * tamano * 4);
    const paso = 1 / muestras;
    for (let py = 0; py < tamano; py++) {
        for (let px = 0; px < tamano; px++) {
            let r = 0, g = 0, b = 0, a = 0;
            for (let sy = 0; sy < muestras; sy++) {
                for (let sx = 0; sx < muestras; sx++) {
                    const x = vx + (px + (sx + 0.5) * paso) / escala;
                    const y = vy + (py + (sy + 0.5) * paso) / escala;
                    // Rectángulo redondeado (SDF).
                    const hx = rect.ancho / 2, hy = rect.alto / 2;
                    const qx = Math.abs(x - hx) - (hx - rect.radio);
                    const qy = Math.abs(y - hy) - (hy - rect.radio);
                    const d = Math.hypot(Math.max(qx, 0), Math.max(qy, 0)) + Math.min(Math.max(qx, qy), 0) - rect.radio;
                    if (d > 0) continue;
                    let color = colorRect;
                    for (const t of trazos) {
                        let min = Infinity;
                        for (const [ax, ay, bx, by] of t.segmentos) {
                            const dd = distanciaSegmento(x, y, ax, ay, bx, by);
                            if (dd < min) min = dd;
                            if (min <= t.radio) break;
                        }
                        if (min <= t.radio) { color = t.color; break; }
                    }
                    r += color[0]; g += color[1]; b += color[2]; a += 255;
                }
            }
            const n = muestras * muestras;
            const o = (py * tamano + px) * 4;
            if (a > 0) {
                const cubiertos = a / 255;
                datos[o] = Math.round(r / cubiertos);
                datos[o + 1] = Math.round(g / cubiertos);
                datos[o + 2] = Math.round(b / cubiertos);
                datos[o + 3] = Math.round(a / n);
            }
        }
    }
    return datos;
}

// ---------------------------------------------------------------- PNG e ICO
const tablaCrc = (() => {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        t[n] = c >>> 0;
    }
    return t;
})();
function crc32(buf) {
    let c = 0xffffffff;
    for (const byte of buf) c = tablaCrc[(c ^ byte) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
}
function chunk(tipo, datos) {
    const largo = Buffer.alloc(4); largo.writeUInt32BE(datos.length);
    const cuerpo = Buffer.concat([Buffer.from(tipo, 'ascii'), datos]);
    const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(cuerpo));
    return Buffer.concat([largo, cuerpo, crc]);
}
function codificarPng(rgba, tamano) {
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(tamano, 0); ihdr.writeUInt32BE(tamano, 4);
    ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
    const filas = Buffer.alloc((tamano * 4 + 1) * tamano);
    for (let y = 0; y < tamano; y++) {
        filas[y * (tamano * 4 + 1)] = 0;
        rgba.copy(filas, y * (tamano * 4 + 1) + 1, y * tamano * 4, (y + 1) * tamano * 4);
    }
    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(filas, { level: 9 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}
function codificarIco(imagenes) {
    const cabecera = Buffer.alloc(6);
    cabecera.writeUInt16LE(0, 0); cabecera.writeUInt16LE(1, 2); cabecera.writeUInt16LE(imagenes.length, 4);
    const entradas = [];
    let desplazamiento = 6 + 16 * imagenes.length;
    for (const { tamano, png } of imagenes) {
        const e = Buffer.alloc(16);
        e[0] = tamano >= 256 ? 0 : tamano; e[1] = tamano >= 256 ? 0 : tamano;
        e[2] = 0; e[3] = 0;
        e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6);
        e.writeUInt32LE(png.length, 8); e.writeUInt32LE(desplazamiento, 12);
        desplazamiento += png.length;
        entradas.push(e);
    }
    return Buffer.concat([cabecera, ...entradas, ...imagenes.map((i) => i.png)]);
}

// ---------------------------------------------------------------- principal
const svg = leerSvg(rutaSvg);
fs.mkdirSync(salida, { recursive: true });
const tamanos = [16, 24, 32, 48, 64, 128, 256];
const imagenes = tamanos.map((tamano) => ({ tamano, png: codificarPng(rasterizar(svg, tamano), tamano) }));
fs.writeFileSync(path.join(salida, 'icono.ico'), codificarIco(imagenes));
fs.writeFileSync(path.join(salida, 'icono.png'), imagenes.find((i) => i.tamano === 256).png);
fs.writeFileSync(path.join(salida, 'icono-32.png'), imagenes.find((i) => i.tamano === 32).png);
console.log(`Iconos generados en ${salida}: icono.ico (${tamanos.join(', ')}), icono.png (256), icono-32.png`);
