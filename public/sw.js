/**
 * Service Worker mínimo para PWA "Tecno-Rexs".
 *
 * Estrategia:
 *  - HTML: network-first, fallback a cache (para que updates se vean rápido).
 *  - Assets estáticos (CSS/JS/img): cache-first, revalidar en background.
 *  - API (/api/*): network-only. NUNCA cachear respuestas autenticadas.
 *
 * Para activar la PWA: generar /public/icons/icon-192.png y icon-512.png
 * (con cualquier herramienta online) y registrar el SW desde app.ts:
 *   if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');
 */

const CACHE_NAME = 'tecno-rexs-v1';
const PRECACHE_URLS = [
    '/',
    '/productos',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo interceptar GET
    if (request.method !== 'GET') return;

    // Nunca cachear la API
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) {
        return;
    }

    // HTML: network-first
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    return res;
                })
                .catch(() => caches.match(request).then((r) => r || caches.match('/')))
        );
        return;
    }

    // Assets: cache-first
    event.respondWith(
        caches.match(request).then((cached) => {
            const network = fetch(request)
                .then((res) => {
                    if (res.ok && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || /\.(png|jpg|jpeg|svg|webp|css|js|woff2?)$/i.test(url.pathname))) {
                        const copy = res.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return res;
                })
                .catch(() => cached);
            return cached || network;
        })
    );
});
