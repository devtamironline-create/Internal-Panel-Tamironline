# راهنمای پیاده‌سازی سئو در فرانت‌اند (Next.js)

این داکیومنت برای تیم فرانت است. سیستم سئو **Headless** است: تمام داده‌ها
(عنوان، توضیحات، canonical، robots، Open Graph، Twitter، JSON-LD، sitemap،
robots.txt، ریدایرکت‌ها) از بک‌اند Laravel می‌آید و فرانت Next.js فقط
**مصرف‌کننده/رندرکننده** است. هیچ منطق سئویی نباید در فرانت hard-code شود.

> پنل ادمین فقط محلِ **مدیریتِ** این داده‌هاست. خروجی برای سایت از طریق
> APIهای زیر در دسترس است.

---

## 🆕 تغییرات این نسخه (برای تیم فرانت)
آیتم‌های زیر در قرارداد API **اضافه/تغییر** کرده‌اند — لطفاً مطابقت دهید:

1. **نوعِ `brand_device`** برای `/services/{device}/{brand}` → `?type=brand_device&slug={device}/{brand}` (بخش ۱).
2. فیلدِ **`faq`** در پاسخِ `‎/meta` + نودهای جدیدِ JSON-LD (`Service`, `FAQPage`, `DiscussionForumPosting`) (بخش ۱).
3. بلاکِ **`integrations`** در `‎/settings` (GA4 + GTM + disable-for-admins) و **Facebook verification** (بخش ۲ و ۶).
4. **`/llms.txt`** جدید (بخش ۳).
5. **chunkingِ sitemap** برای نوع‌های بزرگ: `{type}-{page}.xml` (بخش ۳).
6. **`status_code=308`** در ریدایرکت‌ها + **۳۰۱های خودکار** هنگام تغییر slug (بخش ۴).
7. `‎/v1/seo/404` حالا URLهای بلند را هم می‌پذیرد (بخش ۵).

هیچ تغییرِ شکستنده‌ای (breaking) نیست؛ فقط افزوده‌ها. صفحاتِ فعلی بدونِ تغییر کار می‌کنند.

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
| `brand_device` | **دستگاه × برند** | `/services/{device}/{brand}` |
| `taxonomy` | دستهٔ سؤالات متداول | `/faq/{slug}` |
| `forum_question` | پرسش فروم | `/forum/{slug}` |

> **🆕 صفحهٔ ترکیبی دستگاه×برند** حالا endpoint اختصاصی دارد. `slug` را به‌صورت
> `«device-slug/brand-slug»` بفرستید (شاملِ `/`). مثال:
> ```
> GET {BACKEND_URL}/v1/seo/meta?type=brand_device&slug=washing-machine/lg
> ```
> خروجی شامل عنوان/توضیح/canonicalِ خودِ صفحه + JSON-LD از نوع **Service**
> (با `brand` و `serviceType=دستگاه` و `areaServed=شهر`) است. اگر دستگاه یا
> برند پیدا نشود → `404`.

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
    "faq": [
      { "question": "هزینهٔ تعمیر چقدر است؟", "answer": "پس از بررسی توسط تکنسین اعلام می‌شود." }
    ],
    "jsonld": [ { "@context": "https://schema.org", "@type": "WebPage", "...": "..." } ]
  }
}
```

- اگر `type` نامعتبر باشد → `422`. اگر آیتم پیدا نشود → `404` (در این حالت
  صفحه را با `notFound()` نمایش دهید).
- `robots` را عیناً به فیلد `robots` در Metadata بدهید.
- `jsonld` یک **آرایه از نودها**ست؛ هر کدام را در یک تگ
  `<script type="application/ld+json">` جداگانه رندر کنید. نودهای ممکن:
  `Organization، WebSite، WebPage، BreadcrumbList، LocalBusiness` (پایه) +
  بسته به نوع: `Article/BlogPosting، Service، QAPage، DiscussionForumPosting، FAQPage`.
- **🆕 `faq`** آرایه‌ای از پرسش/پاسخِ همان صفحه است (اگر در پنل برایش FAQ تعریف
  شده باشد، وگرنه `[]`). آن را به‌صورتِ آکاردئونِ «سؤالات متداول» در صفحه رندر
  کنید. نودِ `FAQPage` در `jsonld` فقط وقتی می‌آید که در پنل فعال شده باشد —
  پس برای جلوگیری از دوباره‌کاری، اگر FAQPage در `jsonld` بود، آن را خودتان
  دوباره از `faq` نسازید.

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
  faq: { question: string; answer: string }[];
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

#### 🆕 صفحهٔ ترکیبی برند+دستگاه — `app/services/[device]/[brand]/page.tsx`
`slug` را به‌صورتِ `«device/brand»` بدهید (با `/`؛ نیازی به encode نیست چون
خودِ `/` بخشی از slug است — یا با `encodeURIComponent` روی هر بخش جدا):
```tsx
export async function generateMetadata({ params }: { params: { device: string; brand: string } }) {
  return toMetadata(await getSeoMeta("brand_device", `${params.device}/${params.brand}`));
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
    "verification": { "verify_google": "...", "verify_bing": "...", "verification_facebook": "..." },
    "integrations": { "ga4_measurement_id": "G-XXXX", "gtm_container_id": "GTM-XXXX", "analytics_disable_for_admins": false },
    "knowledge_graph": { "type": "Organization", "name": "تعمیرآنلاین", "logo": "...", "same_as": ["https://instagram.com/..."] }
  }
}
```

کاربردها در فرانت:
- تگ‌های verification را در `<head>` رِندر کنید (مثلاً
  `<meta name="google-site-verification" content="...">`،
  `<meta name="facebook-domain-verification" content="...">` و …).
- **🆕 `integrations`**: `ga4_measurement_id` و `gtm_container_id` را برای
  تزریقِ GA4/GTM بخوانید (بخش ۶). اگر `analytics_disable_for_admins=true` بود،
  برای کاربرِ ادمین/لاگین‌شده اسکریپتِ آنالیتیکس را لود نکنید.
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
      { source: "/llms.txt",          destination: `${B}/v1/seo/llms.txt` },
      { source: "/sitemap.xml",       destination: `${B}/v1/seo/sitemap-index.xml` },
      { source: "/sitemap/:type.xml", destination: `${B}/v1/seo/sitemap/:type.xml` },
    ];
  },
};
```

- `‎/v1/seo/sitemap-index.xml` یک **sitemap index** است که به sitemap هر نوع
  لینک می‌دهد (`‎/v1/seo/sitemap/{type}.xml`).
- **🆕 chunking:** اگر یک نوع بیش از ۵۰٬۰۰۰ URL داشته باشد، خودکار به چند فایلِ
  `‎/v1/seo/sitemap/{type}-{page}.xml` تقسیم می‌شود و sitemap index به همه‌شان
  لینک می‌دهد. **هیچ کارِ اضافه‌ای لازم نیست** — rewriteِ `‎/sitemap/:type.xml`
  چون `{type}-{page}` هم در همان الگو می‌افتد (مثلاً `blog-2.xml`) پوشش داده
  می‌شود. (اگر می‌خواهید مطمئن باشید، یک rewriteِ صریح هم اضافه کنید:
  `{ source: "/sitemap/:file.xml", destination: ... }`.)
- آیتم‌های `noindex` یا منتشرنشده خودکار از sitemap حذف می‌شوند.
- robots.txt از پنل قابل ویرایش است و دایرکتیو `Sitemap:` را خودکار دارد.
- **🆕 `‎/llms.txt`** هم از پنل قابل ویرایش است (راهنمای crawlerهای هوش مصنوعی).

> اگر `app/robots.ts` یا `app/sitemap.ts` ساخته‌اید، آن‌ها را حذف کنید تا با
> rewrite تداخل نکنند. (Next به فایل‌های اختصاصی اولویت می‌دهد.)

---

## ۴) ریدایرکت‌ها — `GET /v1/seo/redirects` + `middleware.ts`

```json
{ "data": [ { "source": "/old-path", "target": "/services/refrigerator", "status_code": 301, "match_type": "exact" } ] }
```

`match_type` یکی از: `exact | contains | start | end | regex`.
`status_code` یکی از: `301 | 302 | 307 | 308 | 410 | 451` (برای `410/451` مقدار
`target` خالی است). **🆕 `308`** اضافه شد (انتقالِ دائمیِ حافظِ متد).

> **🆕 ریدایرکتِ خودکار با تغییرِ slug:** وقتی در پنل slugِ یک صفحه (مقاله/برند/
> دستگاه/…) عوض شود، بک‌اند **خودکار** یک ۳۰۱ از مسیرِ قدیمی به جدید می‌سازد و
> در همین فهرست ظاهر می‌شود. پس فرانت کارِ اضافه‌ای ندارد؛ فقط مطمئن شوید
> `middleware.ts` فعال است.

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

## ۶) Google Analytics (GA4) و GTM

🆕 از بلاکِ `integrations` در `‎/v1/seo/settings` بخوانید:
`ga4_measurement_id`، `gtm_container_id`، و `analytics_disable_for_admins`.
سپس در layout ریشه تزریق کنید (یا از `@next/third-parties`):
```tsx
import { GoogleAnalytics, GoogleTagManager } from "@next/third-parties/google";

const { integrations } = await getSeoSettings();
// اگر کاربر ادمین/لاگین‌شده است و disable روشن است، چیزی لود نکن:
const enabled = !(integrations.analytics_disable_for_admins && isAdminUser);
// ...
{enabled && integrations.ga4_measurement_id &&
  <GoogleAnalytics gaId={integrations.ga4_measurement_id} />}
{enabled && integrations.gtm_container_id &&
  <GoogleTagManager gtmId={integrations.gtm_container_id} />}
```
> هیچ اسکریپتی سمتِ بک‌اند تزریق نمی‌شود (سیستم headless است) — کنترلِ کامل با فرانت است.

---

## ۷) چک‌لیست پیاده‌سازی فرانت

- [ ] `BACKEND_URL` و `NEXT_PUBLIC_BACKEND_URL` تنظیم شد.
- [ ] `lib/seo.ts` + `generateMetadata` روی همهٔ صفحات داینامیک
      (`page`, `article`, `blog_topic`, `brand`, `device`, **`brand_device`**, `taxonomy`, `forum_question`).
- [ ] 🆕 روتِ `app/services/[device]/[brand]/page.tsx` با `type=brand_device` و slugِ `«device/brand»`.
- [ ] کامپوننت `<JsonLd>` و رندر `meta.jsonld` در هر صفحه.
- [ ] 🆕 رندرِ بلاکِ «سؤالات متداول» از `meta.faq` (و عدم ساختِ دستیِ FAQPage اگر در jsonld بود).
- [ ] `next.config.js` rewrites برای robots.txt، **llms.txt** و sitemap (شاملِ chunkها).
- [ ] `middleware.ts` برای ریدایرکت‌ها (شاملِ `308`).
- [ ] گزارش ۴۰۴ در `app/not-found.tsx`.
- [ ] تگ‌های verification (شاملِ Facebook) و 🆕 GA4/GTM از بلاکِ `integrations`.
- [ ] تست: خروجی Rich Results گوگل برای چند صفحه (Schema)، اعتبارسنجی XML برای sitemap.

## مرجع سریع endpointها
| متد | مسیر | توضیح |
|-----|------|-------|
| GET | `/v1/seo/meta?type=&slug=` | متای کامل یک آیتم (شاملِ `faq` و `brand_device`) |
| GET | `/v1/seo/settings` | تنظیمات سراسری (شاملِ `integrations`) |
| GET | `/v1/seo/sitemap-index.xml` | ایندکس sitemap |
| GET | `/v1/seo/sitemap/{type}.xml` | sitemap هر نوع |
| GET | `/v1/seo/sitemap/{type}-{page}.xml` | 🆕 chunkِ نوعِ بزرگ (>۵۰هزار URL) |
| GET | `/v1/seo/robots.txt` | محتوای robots.txt |
| GET | `/v1/seo/llms.txt` | 🆕 راهنمای crawlerهای AI |
| GET | `/v1/seo/redirects` | فهرست ریدایرکت‌ها (شاملِ ۳۰۱های خودکارِ slug) |
| POST | `/v1/seo/404` | ثبت بازدید ۴۰۴ (URLهای بلند هم پذیرفته می‌شود) |
