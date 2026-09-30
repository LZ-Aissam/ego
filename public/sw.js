const CACHE = 'ego-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key !== CACHE) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

function putInCache(request, response) {
    const copy = response.clone();
    caches.open(CACHE).then((cache) => cache.put(request, copy));
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok) putInCache(request, response);
            return response;
        })));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).then((response) => {
            if (response.ok) putInCache(request, response);
            return response;
        }).catch(() => caches.match(request).then((hit) => hit || caches.match('/workout'))));
    }
});
