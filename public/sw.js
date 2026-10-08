/*
 * Service worker de TUSHPA (app instalable).
 * - Pantallas: siempre desde internet (los datos de ventas y caja deben estar al día); sin conexión se muestra offline.html.
 * - Estilos, scripts e imágenes: se guardan en el celular para que la app abra rápido.
 * - Nunca guarda POST ni respuestas de datos (JSON).
 */
const VERSION = 'tushpa-v1';
const BASE = new URL('./', self.location).pathname;
const OFFLINE = BASE + 'offline.html';

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll([OFFLINE, BASE + 'imagenes/192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((llaves) => Promise.all(llaves.filter((k) => k !== VERSION).map((k) => caches.delete(k)))));
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Pantallas: internet primero; sin conexión, la página de aviso
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    const guardar = (res) => {
        if (res.ok && res.type === 'basic') {
            const copia = res.clone();
            caches.open(VERSION).then((c) => c.put(req, copia));
        }
        return res;
    };

    // /build: el nombre lleva huella (cambia con cada versión), se sirve directo del celular
    if (url.pathname.startsWith(BASE + 'build/')) {
        e.respondWith(caches.match(req).then((guardado) => guardado || fetch(req).then(guardar)));
        return;
    }
    // Otros archivos (js de pantallas, imágenes, fuentes): internet primero para recibir los cambios; sin conexión, lo guardado
    if (/\.(?:css|js|woff2?|png|jpe?g|svg|webp|ico)$/.test(url.pathname)) {
        e.respondWith(fetch(req).then(guardar).catch(() => caches.match(req)));
    }
});
