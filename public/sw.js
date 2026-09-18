/*
 * Service worker de DENTAL-PRO.
 *
 * - Precachea la página "sin conexión" y los iconos.
 * - Navegaciones: siempre red primero; si falla, offline.html. Nunca se
 *   guardan páginas del sistema (contienen datos clínicos y tokens CSRF).
 * - /build/* (Vite, con hash en el nombre) e iconos: caché primero.
 * - Todo lo demás (JSON de /admin/*, /api/*, POST…) va directo a la red.
 * - Al cambiar VERSION se eliminan las cachés antiguas.
 */
const VERSION = 'dentalpro-v2.0.0';
const CACHE_ESTATICO = `${VERSION}-estatico`;
const CACHE_ASSETS = `${VERSION}-assets`;
const OFFLINE = '/offline.html';

const PRECACHE = [
    OFFLINE,
    '/favicon.svg',
    '/iconos/icon-192.png',
    '/iconos/icon-512.png',
    '/iconos/maskable-192.png',
    '/iconos/maskable-512.png',
    '/iconos/apple-touch-icon.png',
];

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(CACHE_ESTATICO)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(
                claves
                    .filter((clave) => clave.startsWith('dentalpro-') && !clave.startsWith(VERSION))
                    .map((clave) => caches.delete(clave)),
            ))
            .then(() => self.clients.claim()),
    );
});

function esAssetCacheable(url) {
    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/iconos/')
        || url.pathname === '/favicon.svg'
        || url.pathname === '/favicon.ico';
}

function esRutaPrivada(url) {
    return url.pathname.startsWith('/admin')
        || url.pathname.startsWith('/api')
        || url.pathname.startsWith('/portal')
        || url.pathname.startsWith('/archivos');
}

async function cachePrimero(peticion) {
    const cache = await caches.open(CACHE_ASSETS);
    const guardada = await cache.match(peticion);
    if (guardada) return guardada;

    const respuesta = await fetch(peticion);
    if (respuesta.ok && respuesta.type === 'basic') {
        cache.put(peticion, respuesta.clone());
    }
    return respuesta;
}

async function redPrimeroNavegacion(peticion) {
    try {
        return await fetch(peticion);
    } catch (e) {
        const cache = await caches.open(CACHE_ESTATICO);
        const offline = await cache.match(OFFLINE);
        return offline || new Response('Sin conexión', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

self.addEventListener('fetch', (evento) => {
    const { request } = evento;

    // Sólo GET: los POST (formularios, subidas) nunca pasan por la caché.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        evento.respondWith(redPrimeroNavegacion(request));
        return;
    }

    // Peticiones JSON/AJAX del panel, API y archivos privados: siempre red.
    if (esRutaPrivada(url)) return;

    if (esAssetCacheable(url)) {
        evento.respondWith(cachePrimero(request));
    }
});

self.addEventListener('message', (evento) => {
    if (evento.data && evento.data.tipo === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
