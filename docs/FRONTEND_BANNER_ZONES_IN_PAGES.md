# مستند فرانت — بنر‌های inline داخل پاسخ صفحات (Page Banner Zones)

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۰۹
> این مستند تغییر شکل پاسخ سکشن‌های بنری در `/v1/pages/{slug}` و الگوی صحیح
> render آن‌ها در Next.js را شرح می‌دهد. هدف: **یک API call واحد** برای دریافت
> هم محتوای صفحه و هم بنرها — بدون round-trip اضافی به `/v1/banners/{slug}`.

پیش‌نیاز: مطالعه‌ی `FRONTEND_MEDIA_BANNERS.md` و `FRONTEND_PAGES_API.md`.

---

## ۱) چرا این تغییر؟

قبلاً سکشن `promo` صفحه‌ی Home در پنل ادمین، تصویر (دسکتاپ/موبایل)، تیتر،
زیرتیتر و لینک را به‌صورت **مستقیم** نگه می‌داشت. این دو مشکل داشت:

1. بنر هر صفحه جدا از مخزن مرکزی بنرها مدیریت می‌شد → بدون زمان‌بندی،
   بدون چندبنری، بدون شمارش impression/click.
2. ادمین نمی‌توانست بنرها را در یک پنل یکپارچه ببیند.

از این پس، سکشن‌های بنری فقط یک **slug زون بنر** را نگه می‌دارند. تمام
مدیریت تصاویر، زمان‌بندی و چندبنری از پنل بنرها (`/admin/site/banners`) انجام
می‌شود. بک‌اند هنگام پاسخ به `/v1/pages/{slug}`، **داده‌ی کامل زون را
inline قرار می‌دهد** تا فرانت یک fetch اضافه نزند.

---

## ۲) شکل قبلی پاسخ (deprecated)

```jsonc
"promo": {
  "title": "سفارش تعمیر آنلاین",
  "subtitle": "...",
  "image": { "desktop": "https://...", "mobile": "https://..." },
  "link_url": "/order",
  "link_label": "شروع سفارش"
}
```

این فیلدها **حذف شده‌اند**. اگر فرانت همچنان آن‌ها را می‌خواند، `undefined` خواهد بود.

---

## ۳) شکل جدید پاسخ

```jsonc
"promo": {
  "zone_slug": "home_promo",
  "zone_slug_data": {
    "zone": {
      "slug": "home_promo",
      "name": "صفحه‌ی اصلی — بنر تبلیغاتی",
      "recommended": { "width": 1200, "height": 300 }   // یا null
    },
    "banners": [
      {
        "id": "01HX...",
        "title": "سفارش تعمیر آنلاین",
        "subtitle": "فقط کافیست شماره‌ی خود را وارد کنید.",
        "link": { "url": "/order", "label": "شروع سفارش" },
        "image": {
          "desktop": {
            "url": "https://api.example.com/media/ab/cd/abcdef.webp",
            "alt": "بنر تبلیغاتی",
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
          "mobile": { "url": "...", "variants": { ... } }   // یا null
        },
        "sort_order": 0
      }
    ],
    "updated_at": "2026-05-30T08:15:00Z"
  }
}
```

**قراردادهای مهم:**

- `zone_slug` — slug انتخاب‌شده توسط ادمین. اگر ادمین چیزی انتخاب نکرده باشد، `null`.
- `zone_slug_data` — داده‌ی hydrate شده. اگر `zone_slug` خالی **یا** زون
  غیرفعال/پاک‌شده باشد، این فیلد `null` خواهد بود.
- `banners` — می‌تواند آرایه‌ی خالی باشد (زون موجود است ولی هیچ بنر منتشرشده/زنده‌ای ندارد).
- شکل `banners[*]` **دقیقاً** مثل خروجی `GET /v1/banners/{zoneSlug}` است (همان helper پشت پرده).

---

## ۴) منطق render امن

```tsx
const promo = content.promo?.zone_slug_data;
const firstBanner = promo?.banners?.[0];

// اگر زون انتخاب نشده، یا زون خالی است، چیزی render نکن
if (!firstBanner) return null;

const desktopUrl =
  firstBanner.image?.desktop?.variants?.large?.url ??
  firstBanner.image?.desktop?.url;
const mobileUrl =
  firstBanner.image?.mobile?.variants?.medium?.url ??
  firstBanner.image?.mobile?.url ??
  desktopUrl;   // fallback به دسکتاپ اگر موبایل ندارد
```

**هرگز** فرض نکنید فیلدی وجود دارد — هم desktop و هم mobile می‌توانند null باشند.

---

## ۵) بازنویسی `HomePage.tsx`

```tsx
// قبل:
<GlobalPromoBanner
  desktopImage={content.promo.desktopImage ?? DEFAULT_PROMO_DESKTOP}
  mobileImage={content.promo.mobileImage ?? DEFAULT_PROMO_MOBILE}
  imageAlt={content.promo.subtitle ?? "بنر ثبت سفارش سریع"}
  title={promoTitle}
  bannerLink={{ ... }}
/>

// بعد:
import { PagePromoBanner } from "@/features/home/components/PagePromoBanner";

<PagePromoBanner data={content.promo?.zone_slug_data ?? null} />
```

و کامپوننت `PagePromoBanner` چیزی شبیه این (می‌تواند از همان `GlobalPromoBanner` موجود استفاده کند):

```tsx
import { GlobalPromoBanner } from "@/components/layout/GlobalPromoBanner";

type ZoneData = {
  banners: Array<{
    id: string;
    title: string | null;
    subtitle: string | null;
    link: { url: string | null; label: string | null };
    image: {
      desktop: { url: string; alt: string | null; variants: Record<string, { url: string; width: number; height: number }> } | null;
      mobile:  { url: string; variants: Record<string, { url: string; width: number; height: number }> } | null;
    };
  }>;
} | null;

export function PagePromoBanner({ data }: { data: ZoneData }) {
  const banner = data?.banners?.[0];
  if (!banner) return null;

  const desktopUrl = banner.image.desktop?.variants.large?.url ?? banner.image.desktop?.url;
  const mobileUrl  = banner.image.mobile?.variants.medium?.url ?? banner.image.mobile?.url ?? desktopUrl;
  if (!desktopUrl) return null;

  const href = banner.link.url ?? "#";
  const target = href.startsWith("http") ? "_blank" : "_self";

  return (
    <GlobalPromoBanner
      desktopImage={desktopUrl}
      mobileImage={mobileUrl ?? undefined}
      imageAlt={banner.image.desktop?.alt ?? banner.title ?? "بنر تبلیغاتی"}
      title={banner.title ?? ""}
      bannerLink={{
        href,
        target,
        ariaLabel: banner.link.label ?? banner.title ?? "ثبت سفارش",
      }}
    />
  );
}
```

> **حذف کنید**: ثابت‌های `DEFAULT_PROMO_DESKTOP`، `DEFAULT_PROMO_MOBILE`،
> `DEFAULT_PROMO_TITLE`، `DEFAULT_PROMO_LINK` در `HomePage.tsx` — حالا
> ادمین در پنل، بنر default را تنظیم می‌کند، نه فرانت.

---

## ۶) چندبنری در یک زون

اگر ادمین چند بنر را در زون قرار دهد، آن‌ها به ترتیب `sort_order` صعودی
برمی‌گردند. سه استراتژی متداول:

| استراتژی | کاربرد | پیاده‌سازی |
|---|---|---|
| فقط اول | promo ساده‌ی صفحه — بک‌اپ‌های احتیاطی | `banners[0]` |
| همه به‌صورت stack | sidebar یا گالری بنر | `banners.map(...)` |
| random/rotation | تست A/B یا تنوع | client component — `useState` + `setInterval` |

برای صفحات server-rendered، اگر می‌خواهید rotation داشته باشید، حتماً
client component جدا بسازید و در آن `Math.random()` بزنید — وگرنه همه‌ی
بازدیدکننده‌ها بنر یکسانی می‌بینند تا lifetime بعدی ISR.

---

## ۷) Impression و Click tracking

(فرقی با حالت قبل ندارد — همان endpointهای فصل ۶ مستند `FRONTEND_MEDIA_BANNERS.md`)

```ts
// در client component
fetch(`/api/banner-impression/${banner.id}`, { method: "POST" });  // وقتی visible شد
fetch(`/api/banner-click/${banner.id}`, { method: "POST" });        // در onClick
```

این فراخوانی‌ها به `/v1/banners/{id}/impression` و `/v1/banners/{id}/click` بک‌اند پراکسی می‌شوند.

---

## ۸) Cache و revalidation

پاسخ `/v1/pages/{slug}` همچنان `Cache-Control: public, max-age=300, s-maxage=300` دارد.
داده‌ی inline بنر از همان cache key بنر استفاده می‌کند (`banners:zone:{slug}`، TTL=60s).

**هر بار ادمین بنری را در زونی تغییر دهد، بک‌اند دو تگ revalidation
به Next.js می‌فرستد:**

| تگ | کی فعال می‌شود؟ |
|---|---|
| `banner:zone:{slug}` | هر تغییر بنر در آن زون (هم برای کسانی که از `/v1/banners/{slug}` می‌خوانند) |
| `page:{slug}` | هر تغییر بنر در زونی که داخل سکشن‌های آن صفحه inline-hydrate شده |

یعنی اگر بنر زون `home_promo` تغییر کند، بک‌اند می‌فرستد:
- `banner:zone:home_promo`
- `page:home` ← **چون بک‌اند می‌داند صفحه‌ی home از این زون استفاده می‌کند**

### پیاده‌سازی فرانت

در `app/page.tsx` یا هر جایی که `getHomePageContent()` صدا می‌زنید، تگ صفحه را اضافه کنید:

```ts
const res = await fetch(`${API_BASE}/v1/pages/home`, {
  next: {
    revalidate: 300,
    tags: ["page:home"],   // ← این تگ ضروری است
  },
});
```

و در route handler webhook (همان `/api/revalidate` که برای بنرها داشتید)، هیچ
تغییری لازم نیست — `revalidateTag(tag)` به صورت خودکار هم `banner:zone:home_promo`
و هم `page:home` را وقتی برسد invalidate می‌کند.

نتیجه: تغییر بنر در پنل ادمین → ≤۵ ثانیه بعد در فرانت ظاهر می‌شود، **بدون** نیاز
به fetch دوم در `HomePage`.

---

## ۹) جدول صفحات و زون‌های پیش‌فرض

| صفحه | سکشن | فیلد | zone slug پیش‌فرض seed شده |
|---|---|---|---|
| `home` | `promo` | `zone_slug` | `home_promo` (1200×300) |
| `about` | `promo` | `zone_slug` | `about_promo` (1200×300) |

ادمین می‌تواند هر زون فعال دیگری را در این سکشن‌ها انتخاب کند — مثلاً
`global_top` یا `services_promo` — بسته به نیاز کمپین.

---

## ۱۰) Checklist مهاجرت برای فرانت

- [ ] حذف فیلدهای `content.promo.desktopImage`، `mobileImage`، `title` (به عنوان metadata بنر)، `linkUrl`، `linkLabel` از کد مصرف‌کننده.
- [ ] جایگزینی با `content.promo.zone_slug_data.banners[0]`.
- [ ] حذف ثابت‌های `DEFAULT_PROMO_*` در `HomePage.tsx`.
- [ ] افزودن `tags: ["page:home"]` به fetch محتوای صفحه‌ی Home.
- [ ] افزودن `tags: ["page:about"]` به fetch محتوای صفحه‌ی About (اگر آن صفحه هم بنر دارد).
- [ ] به‌روزرسانی schema/type `getHomePageContent()` که `promo` را به شکل جدید برگرداند.
- [ ] تست edge case: زون انتخاب‌نشده (`zone_slug_data === null`) → null render.
- [ ] تست edge case: زون انتخاب‌شده ولی بدون بنر زنده (`banners: []`) → null render.
- [ ] تست edge case: بنر بدون تصویر موبایل → fallback به دسکتاپ.

---

## ۱۱) نکات بهینه‌سازی

- **`variants.large`** برای دسکتاپ و **`variants.medium`** برای موبایل بهترین انتخاب کیفیت/حجم است.
- اگر می‌خواهید LCP بهتر شود، روی بنر `loading="eager"` و `fetchPriority="high"` بگذارید (فقط برای بنر اول visible).
- `aspect_ratio` همراه با `width`/`height` ارسال می‌شود — استفاده از این مقادیر در `next/image` از CLS جلوگیری می‌کند.
- اگر بنر `link.url` با `http` شروع نمی‌شود، آن را داخلی فرض کنید و با `<Link>` Next.js رندر کنید تا client navigation داشته باشد.

---

## ۱۲) سوالات متداول

**سوال:** اگر ادمین زون `home_promo` را غیرفعال کند، چه می‌شود؟
**پاسخ:** بک‌اند `zone_slug_data` را `null` برمی‌گرداند. کامپوننت شما باید null render کند.

**سوال:** اگر بخواهیم در سکشن دیگری (مثلاً sidebar بلاگ) هم بنر inline داشته باشیم چی؟
**پاسخ:** فقط کافی است بک‌اند فیلد `banner_zone` را در schema آن سکشن اضافه کند.
هیچ تغییری در فرانت لازم نیست جز خواندن `*.zone_slug_data` همان سکشن.

**سوال:** آیا کش بنر و کش page روی هم تداخل دارند؟
**پاسخ:** خیر — هر دو از یک Cache key مشترک (`banners:zone:{slug}`) استفاده می‌کنند،
پس همیشه consistent هستند. وقتی observer بنر invalidate می‌کند، هم
کاربر `/v1/banners/{slug}` و هم کاربر `/v1/pages/{...}` که آن زون را
inline می‌بیند، در fetch بعدی نسخه‌ی تازه می‌گیرند.

**سوال:** آیا `link.url` می‌تواند خالی باشد؟
**پاسخ:** بله. در این حالت بنر را به‌صورت تصویر بدون `<a>` رندر کنید.

---

پایان. سوال یا abuse case دیگری بود، در همان issue بپرسید.
