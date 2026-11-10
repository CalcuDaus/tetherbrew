const CACHE_NAME = 'tetherbrew-static-v2';
const PRECACHE_URLS = [
    '/offline.html',
    '/assets/images/pwa-192.png',
    '/assets/images/pwa-512.png',
    '/assets/images/tether-icon-head.webp'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => (key !== CACHE_NAME ? caches.delete(key) : Promise.resolve()))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // 1. Abaikan semua request non-GET (POST, PUT, DELETE, dll.)
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // 2. Abaikan request lintas domain (CDN, tile peta Carto/OSM, Google Fonts)
    if (url.origin !== self.location.origin) return;

    // 3. Navigasi halaman HTML: WAJIB Network-Only (jangan pernah cache sesi/CSRF!),
    //    fallback ke /offline.html hanya jika jaringan benar-benar mati.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // 4. Endpoint API dinamis (/api/*): WAJIB Network-Only tanpa cache SW
    if (url.pathname.startsWith('/api/')) {
        return;
    }

    // 5. Aset statis lokal (/assets/* dan /uploads/image/*): Stale-While-Revalidate
    const isStaticAsset =
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/uploads/image/');

    if (isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(req).then((cachedResponse) => {
                    const networkFetch = fetch(req)
                        .then((networkResponse) => {
                            if (networkResponse && networkResponse.status === 200) {
                                cache.put(req, networkResponse.clone());
                            }
                            return networkResponse;
                        })
                        .catch(() => cachedResponse);

                    return cachedResponse || networkFetch;
                })
            )
        );
    }
});
