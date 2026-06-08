/* Minimal offline shell for GIS map page */
const CACHE = 'gis-map-v1';
const ASSETS = [
    '/css/fiber-plant-map.css',
    '/css/gis-intelligence.css',
    '/js/fiber-plant-map.js',
    '/js/gis-intelligence.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS.filter(Boolean))).catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }
    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request)),
    );
});
