/**
 * Field Technician PWA — shell + static asset cache (UI only).
 */
const CACHE = 'field-ops-v1';
const ASSETS = [
    '/css/isp-os-pro.css',
    '/js/field-ops.js',
    '/manifest-field.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (ASSETS.some((a) => url.pathname.endsWith(a.replace(/^\//, '')))) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});
