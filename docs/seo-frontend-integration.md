# راهنمای پیاده‌سازی سئو در فرانت‌اند (Next.js)

این داکیومنت برای تیم فرانت است. سیستم سئو **Headless** است: تمام داده‌ها
(عنوان، توضیحات، canonical، robots، Open Graph، Twitter، JSON-LD، sitemap،
robots.txt، ریدایرکت‌ها) از بک‌اند Laravel می‌آید و فرانت Next.js فقط
**مصرف‌کننده/رندرکننده** است. هیچ منطق سئویی نباید در فرانت hard-code شود.

> پنل ادمین فقط محلِ **مدیریتِ** این داده‌هاست. خروجی برای سایت از طریق
> APIهای زیر در دسترس است.

---

## ۰) پیش‌نیازها

- متغیر محیطی `BACKEND_URL` (سمت سرور) و `NEXT_PUBLIC_BACKEND_URL` (سمت کلاینت)
  را برابر آدرس پنل قرار دهید. مثال:
  ```
  BACKEND_URL=https://panel.tamironline.com
  NEXT_PUBLIC_BACKEND_URL=https://panel.tamironline.com
  ```
- در پنل → **سئو → تنظیمات سئو** فیلد «آدرس پایه (canonical base)» را برابر
  دامنهٔ سایت Next.js بگذارید (مثل `https://tamironline.com`). همهٔ
  canonical/sitemap از همین ساخته می‌شوند.

### نکات عمومی APIها
- همهٔ endpointها **عمومی** هستند (نیاز به توکن ندارند) و throttle دارند.
- پاسخ‌ها `Cache-Control` و `ETag` دارند؛ از `fetch(..., { next: { revalidate } })`
  استفاده کنید تا کش لبه/ISR فعال شود.
- مسیر همگی با پیشوند `‎/v1/seo/` است (بدون `‎/api`).

---

## ۱) متای صفحه — `GET /v1/seo/meta`

پارامترها: `type` و `slug`.

```
GET {BACKEND_URL}/v1/seo/meta?type=device&slug=refrigerator
```

### نوع‌های پشتیبانی‌شده (`type`)
| type | موجودیت | نمونه URL سایت |
|------|---------|----------------|
| `page` | صفحهٔ ثابت | `/{slug}` |
| `article` | مقالهٔ بلاگ | `/blog/{slug}` |
| `blog_topic` | دستهٔ بلاگ | `/blog/topic/{slug}` |
| `brand` | برند | `/services/brands/{slug}` |
| `device` | دستگاه | `/services/{slug}` |
| `taxonomy` | دستهٔ سؤالات متداول | `/faq/{slug}` |
| `forum_question` | پرسش فروم | `/forum/{slug}` |

> صفحهٔ ترکیبی دستگاه×برند (`/services/{device}/{brand}`) فعلاً endpoint متای
> اختصاصی ندارد؛ تا افزوده‌شدنش از متای `device` یا `brand` استفاده کنید.

### نمونهٔ پاسخ
```json
{
  "data": {
    "type": "device",
    "title": "تعمیر یخچال – تعمیرآنلاین",
    "description": "تعمیر تخصصی یخچال در محل با گارانتی — رزرو آنلاین.",
    "canonical": "https://tamironline.com/services/refrigerator",
    "robots": "index, follow, max-image-preview:large",
    "robots_directives": ["index", "follow", "max-image-preview:large"],
    "og": {
      "title": "تعمیر یخچال – تعمیرآنلاین",
      "description": "...",
      "image": "https://.../og.jpg",
      "type": "website",
      "url": "https://tamironline.com/services/refrigerator",
      "site_name": "تعمیرآنلاین"
    },
    "twitter": {
      "card": "summary_large_image",
      "title": "...", "description": "...", "image": "...",
      "site": null, "creator": null
    },
    "breadcrumb_title": "تعمیر یخچال",
    "jsonld": [ { "@context": "https://schema.org", "@type": "WebPage", "...": "..." } ]
  }
}
```

- اگر `type` نامعتبر باشد → `422`. اگر آیتم پیدا نشود → `404` (در این حالت
  صفحه را با `notFound()` نمایش دهید).
- `robots` را عیناً به فیلد `robots` در Metadata بدهید.
- `jsonld` یک **آرایه از نودها**ست؛ هر کدام را در یک تگ
  `<script type="application/ld+json">` جداگانه رندر کنید.

### پیاده‌سازی
```ts
// lib/seo.ts
const BACKEND = process.env.BACKEND_URL!;

export type SeoMeta = {
  type: string;
  title: string;
  description: string;
  canonical: string;
  robots: string;
  og: { title: string; description: string; image: string | null; type: string; url: string; site_name: string };
  twitter: { card: string; title: string; description: string; image: string | null; site: string | null; creator: string | null };
  breadcrumb_title: string | null;
  jsonld: Record<string, unknown>[];
};

export async function getSeoMeta(type: string, slug: string): Promise<SeoMeta | null> {
  const res = await fetch(
    `${BACKEND}/v1/seo/meta?type=${type}&slug=${encodeURIComponent(slug)}`,
    { next: { revalidate: 300 } }
  );
  if (!res.ok) return null;
  return (await res.json()).data as SeoMeta;
}

export function toMetadata(m: SeoMeta | null) {
  if (!m) return {};
  return {
    title: m.title,
    description: m.description,
    alternates: { canonical: m.canonical },
    robots: m.robots,
    openGraph: {
      title: m.og.title, description: m.og.description,
      url: m.og.url, siteName: m.og.site_name, type: m.og.type as any,
      images: m.og.image ? [m.og.image] : [],
    },
    twitter: {
      card: m.twitter.card as any, title: m.twitter.title,
      description: m.twitter.description, images: m.twitter.image ? [m.twitter.image] : [],
    },
  };
}
```
```tsx
// components/JsonLd.tsx
export function JsonLd({ nodes }: { nodes: Record<string, unknown>[] }) {
  if (!nodes?.length) return null;
  return (
    <>
      {nodes.map((node, i) => (
        <script key={i} type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(node) }} />
      ))}
    </>
  );
}
```
```tsx
// app/services/[device]/page.tsx
import { getSeoMeta, toMetadata } from "@/lib/seo";
import { JsonLd } from "@/components/JsonLd";
import { notFound } from "next/navigation";

export async function generateMetadata({ params }: { params: { device: string } }) {
  return toMetadata(await getSeoMeta("device", params.device));
}

export default async function Page({ params }: { params: { device: string } }) {
  const meta = await getSeoMeta("device", params.device);
  if (!meta) notFound();
  return (
    <>
      <JsonLd nodes={meta.jsonld} />
      {/* محتوای صفحه */}
    </>
  );
}
```

---

## ۲) تنظیمات سراسری — `GET /v1/seo/settings`

برای مقادیر سراسری مثل verification، پیش‌فرض‌های اجتماعی، GA4 و Knowledge Graph.

```json
{
  "data": {
    "separator": "–",
    "site_name": "تعمیرآنلاین",
    "site_description": "...",
    "canonical_base_url": "https://tamironline.com",
    "social": { "og_default_image": "...", "twitter_card": "summary_large_image",
                "twitter_site": null, "twitter_creator": null, "facebook_app_id": null },
    "verification": { "verify_google": "...", "verify_bing": "..." },
    "knowledge_graph": { "type": "Organization", "name": "تعمیرآنلاین", "logo": "...", "same_as": ["https://instagram.com/..."] }
  }
}
```

کاربردها در فرانت:
- تگ‌های verification را در `<head>` رِندر کنید (مثلاً
  `<meta name="google-site-verification" content="...">`).
- `knowledge_graph` را در صفحهٔ اصلی به‌صورت JSON-LD `Organization` رِندر کنید
  (اگر در jsonld صفحات تکرار نشده).

```ts
// در layout ریشه (revalidate طولانی، مثلاً ۱ ساعت)
export async function getSeoSettings() {
  const res = await fetch(`${process.env.BACKEND_URL}/v1/seo/settings`, { next: { revalidate: 3600 } });
  return res.ok ? (await res.json()).data : null;
}
```

---

## ۳) robots.txt و Sitemap — Rewrite به بک‌اند

ساده‌ترین و بدون‌خطاترین راه: موتورها مستقیم نسخهٔ بک‌اند را بگیرند.

```js
// next.config.js
module.exports = {
  async rewrites() {
    const B = process.env.BACKEND_URL;
    return [
      { source: "/robots.txt",        destination: `${B}/v1/seo/robots.txt` },
      { source: "/sitemap.xml",       destination: `${B}/v1/seo/sitemap-index.xml` },
      { source: "/sitemap/:type.xml", destination: `${B}/v1/seo/sitemap/:type.xml` },
    ];
  },
};
```

- `‎/v1/seo/sitemap-index.xml` یک **sitemap index** است که به sitemap هر نوع
  لینک می‌دهد (`‎/v1/seo/sitemap/{type}.xml`).
- آیتم‌های `noindex` یا منتشرنشده خودکار از sitemap حذف می‌شوند.
- robots.txt از پنل قابل ویرایش است و دایرکتیو `Sitemap:` را خودکار دارد.

> اگر `app/robots.ts` یا `app/sitemap.ts` ساخته‌اید، آن‌ها را حذف کنید تا با
> rewrite تداخل نکنند. (Next به فایل‌های اختصاصی اولویت می‌دهد.)

---

## ۴) ریدایرکت‌ها — `GET /v1/seo/redirects` + `middleware.ts`

```json
{ "data": [ { "source": "/old-path", "target": "/services/refrigerator", "status_code": 301, "match_type": "exact" } ] }
```

`match_type` یکی از: `exact | contains | start | end | regex`.
`status_code` یکی از: `301 | 302 | 307 | 410 | 451` (برای `410/451` مقدار
`target` خالی است).

```ts
// middleware.ts
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const B = process.env.BACKEND_URL!;

type Rule = { source: string; target: string | null; status_code: number; match_type: string };

function matches(rule: Rule, path: string): boolean {
  const norm = (p: string) => (p.replace(/\/+$/, "") || "/");
  switch (rule.match_type) {
    case "exact":    return norm(path) === norm(rule.source);
    case "contains": return path.includes(rule.source);
    case "start":    return path.startsWith(rule.source);
    case "end":      return norm(path).endsWith(norm(rule.source));
    case "regex":    try { return new RegExp(rule.source).test(path); } catch { return false; }
    default:         return false;
  }
}

export async function middleware(req: NextRequest) {
  const path = req.nextUrl.pathname;

  const rules: Rule[] = await fetch(`${B}/v1/seo/redirects`, { next: { revalidate: 120 } })
    .then(r => r.json()).then(j => j.data).catch(() => []);

  for (const r of rules) {
    if (!matches(r, path)) continue;
    if (r.status_code === 410 || r.status_code === 451) {
      return new NextResponse(null, { status: r.status_code });
    }
    if (r.target) {
      return NextResponse.redirect(new URL(r.target, req.url), r.status_code);
    }
  }
  return NextResponse.next();
}

export const config = {
  // از مسیرهای استاتیک/داخلی صرف‌نظر کن
  matcher: ["/((?!_next|api|favicon.ico|robots.txt|sitemap).*)"],
};
```

**ترتیب قواعد مهم است:** اولین قاعدهٔ منطبق اعمال می‌شود (همان رفتارِ بک‌اند).

---

## ۵) گزارش ۴۰۴ — `POST /v1/seo/404`

هر بازدید ۴۰۴ را به بک‌اند گزارش دهید تا در پنل تجمیع و قابل تبدیل به ریدایرکت شود.

```tsx
// app/not-found.tsx  (Client Component)
"use client";
import { useEffect } from "react";

export default function NotFound() {
  useEffect(() => {
    fetch(`${process.env.NEXT_PUBLIC_BACKEND_URL}/v1/seo/404`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ uri: location.pathname, referrer: document.referrer || null }),
      keepalive: true,
    }).catch(() => {});
  }, []);
  return <div>صفحه پیدا نشد</div>;
}
```
بدنه: `{ uri: string (الزامی), referrer?: string }` — پاسخ `201`.

---

## ۶) Google Analytics (GA4)

`ga4_measurement_id` از `‎/v1/seo/settings` را بخوانید و تگ GA4 را در layout
ریشه تزریق کنید (یا از `@next/third-parties` استفاده کنید):
```tsx
import { GoogleAnalytics } from "@next/third-parties/google";
// ...
{gaId && <GoogleAnalytics gaId={gaId} />}
```

---

## ۷) چک‌لیست پیاده‌سازی فرانت

- [ ] `BACKEND_URL` و `NEXT_PUBLIC_BACKEND_URL` تنظیم شد.
- [ ] `lib/seo.ts` + `generateMetadata` روی همهٔ صفحات داینامیک
      (`page`, `article`, `blog_topic`, `brand`, `device`, `taxonomy`, `forum_question`).
- [ ] کامپوننت `<JsonLd>` و رندر `meta.jsonld` در هر صفحه.
- [ ] `next.config.js` rewrites برای robots.txt و sitemap.
- [ ] `middleware.ts` برای ریدایرکت‌ها.
- [ ] گزارش ۴۰۴ در `app/not-found.tsx`.
- [ ] تگ‌های verification و GA4 از `‎/v1/seo/settings`.
- [ ] تست: خروجی Rich Results گوگل برای چند صفحه (Schema)، اعتبارسنجی XML برای sitemap.

## مرجع سریع endpointها
| متد | مسیر | توضیح |
|-----|------|-------|
| GET | `/v1/seo/meta?type=&slug=` | متای کامل یک آیتم |
| GET | `/v1/seo/settings` | تنظیمات سراسری |
| GET | `/v1/seo/sitemap-index.xml` | ایندکس sitemap |
| GET | `/v1/seo/sitemap/{type}.xml` | sitemap هر نوع |
| GET | `/v1/seo/robots.txt` | محتوای robots.txt |
| GET | `/v1/seo/redirects` | فهرست ریدایرکت‌ها |
| POST | `/v1/seo/404` | ثبت بازدید ۴۰۴ |
