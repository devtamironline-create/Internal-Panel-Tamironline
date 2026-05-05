// تعمیرآنلاین — Service Worker پنل تکنسین
// استراتژی: network-first برای صفحات داینامیک، با fallback به cache در حالت آفلاین.
// Static assets (CSS/JS/تصاویر) → cache-first با revalidation.

const CACHE_VERSION = 'tech-v1';
const STATIC_CACHE = `static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `dynamic-${CACHE_VERSION}`;

const PRECACHE_URLS = [
    '/manifest.json',
    '/tech-pwa/icons/icon.svg',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => k !== STATIC_CACHE && k !== DYNAMIC_CACHE)
                    .map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;

    // GET فقط — POST/PUT/DELETE همیشه از شبکه
    if (request.method !== 'GET') return;

    // درخواست‌های API و auth را هرگز کش نکن
    const url = new URL(request.url);
    if (url.pathname.startsWith('/tech/auth/') ||
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/livewire/')) {
        return;
    }

    // Navigation (HTML pages) → network-first
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then(res => {
                    const copy = res.clone();
                    caches.open(DYNAMIC_CACHE).then(c => c.put(request, copy)).catch(() => {});
                    return res;
                })
                .catch(() => caches.match(request).then(r => r || caches.match('/tech')))
        );
        return;
    }

    // Static assets → cache-first
    event.respondWith(
        caches.match(request).then(cached => {
            if (cached) return cached;
            return fetch(request).then(res => {
                const copy = res.clone();
                caches.open(STATIC_CACHE).then(c => c.put(request, copy)).catch(() => {});
                return res;
            }).catch(() => cached);
        })
    );
});
