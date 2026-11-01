/*
 * Service worker de TUSHPA (app instalable).
 * - Pantallas: siempre desde internet (los datos de ventas y caja deben estar al día); sin conexión se muestra offline.html.
 *   Con "navigation preload" el navegador pide la pantalla mientras el service worker despierta: abrir pantallas
 *   en la app instalada es tan rápido como en el navegador.
 * - Archivos con versión en el nombre (/build/app-XXXX.css) o en la dirección (?v=...): se guardan en el equipo una vez
 *   y se sirven desde ahí. El resto (imágenes, fuentes) lo maneja la caché normal del navegador.
 * - La caché tiene tope: no crece sin límite con cada actualización.
 * - Nunca guarda POST ni respuestas de datos (JSON).
 * - Avisos al celular (Web Push): muestra el aviso y al tocarlo abre la pantalla de mesas.
 * - Caja sin internet: su pantalla se guarda aparte (la guarda la misma página mientras hay internet) y sin conexión
 *   se abre desde la PC.
 */
const VERSION = 'tushpa-v4';
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
                // ignoreVary: el servidor manda "Vary: Accept-Encoding" y con eso la copia guardada a veces no se encuentra
                const opciones = { ignoreSearch: true, ignoreVary: true };
                return (await caches.match(url.pathname, { ...opciones, cacheName: CAJA })) || (await caches.match(req, opciones))
                    || (await caches.match(OFFLINE)) || Response.error();
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

// Avisos al celular del mozo (pedido del QR, llaman al mozo, cuenta, plato listo): suenan aunque la pantalla esté apagada
self.addEventListener('push', (e) => {
    let aviso = {};
    try { aviso = e.data ? e.data.json() : {}; } catch (error) { aviso = { cuerpo: e.data ? e.data.text() : '' }; }
    e.waitUntil(self.registration.showNotification(aviso.titulo || 'TUSHPA', {
        body: aviso.cuerpo || '',
        icon: BASE + 'imagenes/192.png',
        badge: BASE + 'imagenes/192.png',
        tag: aviso.etiqueta || undefined,       // el mismo aviso repetido no se amontona
        renotify: true,
        vibrate: [200, 100, 200],
        data: { url: aviso.url || BASE },
    }));
});

// Tocar el aviso abre (o trae al frente) la pantalla de mesas
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const destino = new URL(e.notification.data?.url || BASE, self.location.origin).href;
    e.waitUntil((async () => {
        const abiertas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const misma = abiertas.find((c) => c.url.split('#')[0] === destino);
        if (misma) return misma.focus();
        return self.clients.openWindow(destino);
    })());
});
