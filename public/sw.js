// تعمیرآنلاین — Service Worker پنل تکنسین
//
// ⚠️ این SW با scope='/' ثبت می‌شود، پس روی *کل دامنه* می‌نشیند — از جمله
// پنل ادمین. نسخهٔ قبلی هر GETی را که HTML نبود به شاخهٔ cache-first
// می‌فرستاد؛ نتیجه‌اش این بود که پاسخ‌های JSONِ بلادرنگ (پیام‌رسان، چت
// تکنسین، اعلان‌ها) یک‌بار کش می‌شدند و برای همیشه همان نسخهٔ کهنه
// برگردانده می‌شد. هدرهای no-store هم کاری نمی‌کردند چون caches.match
// اصلاً Cache-Control را نگاه نمی‌کند.
//
// قاعدهٔ جدید: هیچ‌چیز کش نمی‌شود مگر اینکه صریحاً «فایل ثابت» باشد.
// هر چیز دیگری دست‌نخورده به شبکه می‌رود.

// نسخه را بالا بردیم تا activate، کش‌های آلودهٔ نسخهٔ قبلی را روی همهٔ
// مرورگرها پاک کند — بدون اینکه کاربر لازم باشد دستی کاری بکند.
const CACHE_VERSION = 'tech-v3';
const STATIC_CACHE = `static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `dynamic-${CACHE_VERSION}`;

const PRECACHE_URLS = [
    '/manifest.json',
    '/tech-pwa/icons/icon.svg',
];

/** فقط این پیشوندها فایلِ ثابت‌اند و اجازهٔ کش‌شدن دارند. */
const STATIC_PREFIXES = ['/vendor/', '/css/', '/js/', '/fonts/', '/images/', '/tech-pwa/', '/build/'];

/** پسوندهای مجاز برای کش — حتی داخل پیشوندهای بالا. */
const STATIC_EXTENSIONS = /\.(css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|gif|svg|webp|avif|ico|mp3|wav)$/i;

/** مسیرهایی که هرگز نباید توسط SW لمس شوند (پنل‌های داینامیک و APIها). */
const NEVER_HANDLE_PREFIXES = ['/admin/', '/crm/', '/api/', '/livewire/', '/tech/auth/', '/file/', '/media/', '/storage/'];

/** فایل‌های تکیِ مجاز که زیر پیشوندهای بالا نیستند. */
const STATIC_FILES = ['/manifest.json', '/favicon.ico'];

function isStaticAsset(url) {
    // این‌ها پسوندشان با الگوی دارایی جور نیست (json) ولی صراحتاً مجازند.
    if (STATIC_FILES.includes(url.pathname)) {
        return true;
    }

    return STATIC_EXTENSIONS.test(url.pathname)
        && STATIC_PREFIXES.some(prefix => url.pathname.startsWith(prefix));
}

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .catch(() => {})
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

    const url = new URL(request.url);

    // دامنهٔ دیگر (CDN، آواتار خارجی) — کاری نداریم.
    if (url.origin !== self.location.origin) return;

    // پنل‌های داینامیک و APIها: اصلاً دست نزن. هر پاسخِ کش‌شده اینجا یعنی
    // دادهٔ کهنه — از پیامِ چت گرفته تا توکنِ CSRF.
    if (NEVER_HANDLE_PREFIXES.some(prefix => url.pathname.startsWith(prefix))) return;

    // درخواستی که JSON می‌خواهد هرگز کش نمی‌شود، هرجای سایت که باشد.
    const accept = request.headers.get('accept') || '';
    if (accept.includes('application/json')) return;

    // صفحات HTML → network-first با fallback آفلاین
    if (request.mode === 'navigate' || accept.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then(res => {
                    // فقط صفحات پنل تکنسین برای حالت آفلاین نگه داشته می‌شوند.
                    if (url.pathname.startsWith('/tech') && res.ok) {
                        const copy = res.clone();
                        caches.open(DYNAMIC_CACHE).then(c => c.put(request, copy)).catch(() => {});
                    }
                    return res;
                })
                .catch(() => caches.match(request).then(r => r || caches.match('/tech')))
        );
        return;
    }

    // فقط فایل‌های ثابتِ شناخته‌شده → cache-first
    if (! isStaticAsset(url)) return;

    event.respondWith(
        caches.match(request).then(cached => {
            if (cached) return cached;
            return fetch(request).then(res => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(request, copy)).catch(() => {});
                }
                return res;
            }).catch(() => cached);
        })
    );
});
