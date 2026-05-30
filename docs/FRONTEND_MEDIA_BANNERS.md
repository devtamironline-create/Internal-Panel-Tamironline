# مستند فرانت — Media Library و سیستم Banner

> Media Library = مخزن مرکزی همه‌ی فایل‌ها مثل WP. Banner = نمایش بنر بهینه با cache layer + webhook revalidation.

---

## ۱) خلاصه API

| داده | Endpoint | Method | Cache |
|---|---|---|---|
| بنرهای یک زون | `/v1/banners/{zoneSlug}` | GET | s-maxage=60 + ETag |
| ثبت impression بنر | `/v1/banners/{id}/impression` | POST | — |
| ثبت click بنر | `/v1/banners/{id}/click` | POST | — |

---

## ۲) `GET /v1/banners/{zoneSlug}`

```jsonc
{
  "zone": {
    "slug": "blog_sidebar",
    "name": "بلاگ — Sidebar",
    "recommended": { "width": 300, "height": 250 }
  },
  "banners": [
    {
      "id": "01HX...",
      "title": "...",
      "subtitle": "...",
      "link": { "url": "/order", "label": "ثبت سفارش" },
      "image": {
        "desktop": {
          "url": "https://.../media/ab/cd/abcdef.webp",
          "alt": "...",
          "width": 1200,
          "height": 300,
          "aspect_ratio": "4:1",
          "variants": {
            "thumb":  { "url": "...-thumb.webp",  "width": 150,  "height": 38 },
            "small":  { "url": "...-small.webp",  "width": 400,  "height": 100 },
            "medium": { "url": "...-medium.webp", "width": 800,  "height": 200 },
            "large":  { "url": "...-large.webp",  "width": 1600, "height": 400 }
          }
        },
        "mobile":  { "url": "...", "variants": {...} }   // یا null
      },
      "sort_order": 0
    }
  ],
  "updated_at": "2026-05-25T10:30:00Z"
}
```

**نکات بهینه‌سازی:**
- response دارای `ETag` است. فرانت می‌تواند با `If-None-Match` در درخواست بعدی، اگر چیزی عوض نشده باشد، **304 Not Modified** دریافت کند (بدون body).
- `Cache-Control: public, max-age=60, s-maxage=60` → ISR=60 توصیه می‌شود.
- اگر **webhook revalidation** ست شده باشد (env: `SITE_REVALIDATION_URL`)، تغییر بنر فوراً به فرانت اطلاع داده می‌شود و کش فرانت با `revalidateTag('banner:zone:blog_sidebar')` پاک می‌شود → بنر در ≤۵ ثانیه ظاهر می‌شود.

---

## ۳) زون‌های پیش‌فرض

| slug | کاربرد | recommended size |
|---|---|---|
| `home_hero` | صفحه‌ی اصلی Hero | 1920×700 |
| `home_secondary` | صفحه‌ی اصلی بنر دوم | 1200×300 |
| `blog_hero` | بلاگ Hero | 1200×300 |
| `blog_sidebar` | بلاگ Sidebar | 300×250 |
| `forum_sidebar` | انجمن Sidebar | 300×250 |
| `forum_top` | انجمن بالا | 1200×200 |
| `services_promo` | خدمات تبلیغی | 1200×300 |
| `global_top` | سراسری بالای همه صفحات | 1200×60 |

ادمین می‌تواند زون جدید بسازد یا موجود را ویرایش کند (در آینده، فعلاً seeded در migration).

---

## ۴) نمونه‌ی Next.js — رندر بنر در sidebar

```tsx
// app/blog/[slug]/sidebar.tsx
import Image from 'next/image';

async function loadBanners(zone: string) {
  const res = await fetch(`${process.env.API_BASE_URL}/v1/banners/${zone}`, {
    next: { revalidate: 60, tags: [`banner:zone:${zone}`] },
  });
  if (!res.ok) return null;
  return res.json();
}

export async function BannerSlot({ zone }: { zone: string }) {
  const data = await loadBanners(zone);
  if (!data?.banners?.length) return null;

  return (
    <>
      {data.banners.map((b: any) => (
        <a key={b.id} href={b.link.url} onClick={() => fetch(`/api/banner-click/${b.id}`)}>
          <picture>
            {b.image.mobile && <source media="(max-width: 767px)" srcSet={b.image.mobile.url} />}
            <Image
              src={b.image.desktop?.variants?.medium?.url ?? b.image.desktop.url}
              alt={b.image.desktop.alt ?? b.title}
              width={b.image.desktop.width ?? 300}
              height={b.image.desktop.height ?? 250}
              loading="lazy"
            />
          </picture>
          {b.title && <h3>{b.title}</h3>}
          {b.subtitle && <p>{b.subtitle}</p>}
        </a>
      ))}
    </>
  );
}
```

**Impression tracking** را روی `IntersectionObserver` در client component بزنید — وقتی بنر ۵۰٪+ visible شد، `POST /v1/banners/{id}/impression`.

---

## ۵) Webhook revalidation (سرعت ≤۵ ثانیه)

برای فعال‌سازی webhook، در `.env` لاراول:

```env
SITE_REVALIDATION_URL=https://your-frontend.com/api/revalidate
SITE_REVALIDATION_SECRET=long-random-token
```

سپس در Next.js endpoint بسازید:

```ts
// app/api/revalidate/route.ts
import { revalidateTag } from 'next/cache';

export async function POST(req: Request) {
  const secret = req.headers.get('X-Revalidation-Secret');
  if (secret !== process.env.SITE_REVALIDATION_SECRET) {
    return Response.json({ ok: false }, { status: 401 });
  }
  const { tag } = await req.json();
  if (tag) revalidateTag(tag);
  return Response.json({ ok: true });
}
```

تگ‌های ارسال‌شده:
- `banner:zone:{slug}` — یک زون خاص
- `banner:all` — همه‌ی زون‌ها

این endpoint رمز مشترک را با hash_equals چک می‌کند و فوراً ISR cache آن tag را invalidate می‌کند.

---

## ۶) Media Library

برای ادمین در `/admin/site/media`:
- آپلود drag/drop با progress
- جستجو، فیلتر type/tag
- 4 variant خودکار (thumb/small/medium/large) با GD
- dedup با hash → فایل تکراری → reuse رکورد قبلی
- reverse-lookup polymorphic: «این فایل کجاها استفاده شده»

**ساختار disk:**
```
storage/app/public/
  site/media/
    ab/cd/{hash}.webp                  ← اصلی
    ab/cd/variants/{hash}-thumb.webp   ← thumbnail 150px
    ab/cd/variants/{hash}-small.webp   ← 400px
    ab/cd/variants/{hash}-medium.webp  ← 800px
    ab/cd/variants/{hash}-large.webp   ← 1600px
```

**نام فایل = hash → URL ها immutable**. می‌توانید header بزنید:
```
Cache-Control: public, max-age=31536000, immutable
```
(در nginx یا CDN). تغییر فایل = hash جدید = URL جدید → بدون cache busting لازم.

---

## ۷) Migrationهای لازم برای deploy

```
2026_05_23_140_create_media_library_tables
2026_05_23_141_add_media_permissions
2026_05_23_150_create_banner_zones_and_link
```

سپس `php artisan storage:link` (یک‌بار) برای symlink کردن `storage/app/public` به `public/storage`.
