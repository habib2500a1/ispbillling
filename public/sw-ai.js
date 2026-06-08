const CACHE = 'ai-copilot-v1';
const ASSETS = ['/css/ai-copilot-pro.css', '/js/ai-copilot.js', '/manifest-ai.json'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (ASSETS.some((a) => url.pathname.endsWith(a.replace(/^\//, '')))) {
        e.respondWith(caches.match(e.request).then((c) => c || fetch(e.request)));
    }
});
