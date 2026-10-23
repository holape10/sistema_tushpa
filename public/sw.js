/*
 * Service worker de TUSHPA (app instalable).
 * - Pantallas: siempre desde internet (los datos de ventas y caja deben estar al día); sin conexión se muestra offline.html.
 *   Con "navigation preload" el navegador pide la pantalla mientras el service worker despierta: abrir pantallas
 *   en la app instalada es tan rápido como en el navegador.
 * - Archivos con versión en el nombre (/build/app-XXXX.css) o en la dirección (?v=...): se guardan en el equipo una vez
 *   y se sirven desde ahí. El resto (imágenes, fuentes) lo maneja la caché normal del navegador.
 * - La caché tiene tope: no crece sin límite con cada actualización.
 * - Nunca guarda POST ni respuestas de datos (JSON).
 * - Caja sin internet: su pantalla se guarda aparte (la guarda la misma página mientras hay internet) y sin conexión
 *   se abre desde la PC.
 */
const VERSION = 'tushpa-v3';
const CAJA = 'tushpa-caja';
const BASE = new URL('./', self.location).pathname;
const OFFLINE = BASE + 'offline.html';
const MAXIMO = 60;

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll([OFFLINE, BASE + 'imagenes/192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        // Borra la caché anterior (la v1 guardaba cada imagen y versión sin límite)
        const llaves = await caches.keys();
        await Promise.all(llaves.filter((k) => k !== VERSION && k !== CAJA).map((k) => caches.delete(k)));
        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable();
        }
        await self.clients.claim();
    })());
});

/** Deja solo los últimos archivos guardados (los de versiones viejas se van saliendo) */
async function recortar() {
    const cache = await caches.open(VERSION);
    const llaves = await cache.keys();
    const sobran = llaves.filter((r) => !r.url.endsWith('offline.html') && !r.url.endsWith('192.png')).slice(0, Math.max(0, llaves.length - MAXIMO));
    await Promise.all(sobran.map((r) => cache.delete(r)));
}

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Pantallas: internet primero (con la petición adelantada); sin conexión, la página de aviso
    if (req.mode === 'navigate' && url.pathname === BASE + 'caja-sin-internet') {
        e.respondWith((async () => {
            try {
                const res = (await e.preloadResponse) || await fetch(req);
                if (res.ok && !res.redirected) {
                    const copia = res.clone();
                    e.waitUntil(caches.open(CAJA).then((c) => c.put(url.pathname, copia)));
                }
                return res;
            } catch (error) {
                return (await caches.match(url.pathname, { cacheName: CAJA, ignoreSearch: true })) || (await caches.match(OFFLINE)) || Response.error();
            }
        })());
        return;
    }
    if (req.mode === 'navigate') {
        e.respondWith((async () => {
            try {
                return (await e.preloadResponse) || await fetch(req);
            } catch (error) {
                return (await caches.match(OFFLINE)) || Response.error();
            }
        })());
        return;
    }

    // Solo archivos que no cambian sin cambiar de nombre o de ?v=: se guardan una vez y se sirven del equipo
    const conVersion = url.pathname.startsWith(BASE + 'build/') || (url.searchParams.has('v') && /\.(?:css|js)$/.test(url.pathname));
    if (!conVersion) return;

    e.respondWith(caches.match(req).then((guardado) => guardado || fetch(req).then((res) => {
        if (res.ok && res.type === 'basic') {
            const copia = res.clone();
            e.waitUntil(caches.open(VERSION).then((c) => c.put(req, copia)).then(recortar));
        }
        return res;
    })));
});
