# پایهٔ سئو — Canonical / Redirects / Sitemap / Robots / llms.txt

> وضعیتِ اجراییِ «پایهٔ رشدِ سئو»: قبل از تولیدِ انبوهِ محتوا و صفحاتِ جدید،
> گوگل باید نسخهٔ اصلیِ (canonical) هر صفحه را دقیق بفهمد. این سند نشان می‌دهد
> چه چیزی از **پنل (این ریپو)** انجام شده و چه چیزی در **فرانتِ Next.js** یا
> **Search Console** باید انجام شود.

سیستمِ سئو Headless است: پنل داده را می‌سازد، فرانت مصرف می‌کند. آدرس‌ها:

| منبع | Endpoint (روی دامنهٔ فرانت، پراکسی‌شده) |
|------|------------------------------------------|
| Sitemap index | `/v1/seo/sitemap-index.xml` |
| Sitemap هر نوع | `/v1/seo/sitemap/{type}.xml` (یا `{type}-{page}.xml`) |
| robots.txt | `/v1/seo/robots.txt` |
| llms.txt | `/v1/seo/llms.txt` |
| ریدایرکت‌ها | `/v1/seo/redirects` (برای `middleware.ts`) |
| متای هر صفحه | `/v1/seo/meta?type=..&slug=..` (شاملِ `canonical`) |

---

## بخش ۱ — ریدایرکتِ URLهای قدیمی `/repair/` → `/services/`

**اولویتِ اصلی.** ریدایرکت‌ها در جدولِ `seo_redirects` نگه‌داری و از
`/v1/seo/redirects` به `middleware.ts`ِ فرانت داده می‌شوند.

### ⚠️ نکتهٔ مهم دربارهٔ نوعِ تطبیق

`RedirectMatcher` (مرجعِ رفتار، `Modules/Seo/Services/RedirectMatcher.php`)
**جایگذاریِ گروهِ regex (`$1`) را انجام نمی‌دهد** — `target` عیناً استفاده
می‌شود. پس یک قاعدهٔ فراگیرِ `^/repair/(.*)$ → /services/$1` **کار نمی‌کند**
و همهٔ آدرس‌ها را به یک صفحهٔ ثابت می‌فرستد (از دست رفتنِ دستگاه).

### راهِ درست: ریدایرکتِ `exact` به‌ازای هر URL (۳۰۱ مستقیم)

برای هر URLِ قدیمی یک قاعدهٔ **exact** بسازید تا ۳۰۱ مستقیم و بدونِ زنجیره
باشد:

| source | target | status | match_type |
|--------|--------|--------|------------|
| `/repair/washing-machine` | `/services/washing-machine` | 301 | exact |
| `/repair/refrigerator` | `/services/refrigerator` | 301 | exact |
| … | … | 301 | exact |

- در پنل: **سئو → ریدایرکت‌ها** (یا مستقیماً در جدولِ `seo_redirects`).
- اگر slugِ قدیمیِ وردپرس با slugِ فعلیِ دستگاه یکی است، فهرست را می‌توان از
  `crm_devices.slug` تولید کرد؛ اما **پیش از seed، صحتِ نگاشتِ قدیمی→جدید را
  تأیید کنید** (ریدایرکتِ اشتباه بدتر از نبودِ ریدایرکت است).

> اگر `middleware.ts`ِ فرانت جایگذاریِ `$1` را پشتیبانی می‌کند، می‌توان یک
> قاعدهٔ `regex` با `target = /services/$1` گذاشت؛ ولی چون بک‌اند این را
> پشتیبانی نمی‌کند، پیش‌فرضِ امن همان قواعدِ `exact` است.

### چک‌لیستِ تأیید

- [ ] هر `/repair/*` با ۳۰۱ به `/services/*`ِ متناظر می‌رود (نه ۳۰۲، نه زنجیره).
- [ ] مقصدها خودشان ۲۰۰ برمی‌گردانند (نه ۴۰۴/ریدایرکتِ دوباره).

---

## بخش ۲ — صفحاتِ جدیدِ `/services/` (۲۰۰ + indexable + canonical)

- الگوی URLِ دستگاه: `/services/{slug}` (منبع: `config('seo.types.device.url')`).
- `canonical` از `/v1/seo/meta?type=device&slug=..` می‌آید؛ فرانت باید همان را
  در `<link rel="canonical">` بگذارد و هیچ canonicalِ hard-code نگذارد.
- صفحاتِ noindex در پنل (فیلدِ `robots_noindex` روی `seo_meta`) از sitemap هم
  **حذف** می‌شوند تا sitemap با ایندکس‌پذیریِ واقعی هم‌خوان بماند.

چک‌لیست: هر `/services/{slug}` باید `200` + `index,follow` + canonicalِ خودش
به خودش باشد.

---

## بخش ۳ — Sitemap (فقط URLهای نهایی)

Sitemap مستقیم از بک‌اند ساخته می‌شود (`SitemapBuilder`) و شاملِ:

| نوع | الگوی URL | شرطِ ورود |
|-----|-----------|-----------|
| page | `/{slug}` | `published_at` گذشته |
| article | `/blog/{slug}` | `published_at` گذشته |
| blog_topic | `/blog/topic/{slug}` | — |
| brand | `/brands/{slug}` | `is_active` |
| device | `/services/{slug}` | `is_active` |
| **brand_device** | **`/services/{device}/{brand}`** | **`DeviceBrandPage.is_active` و دستگاه/برندِ فعال** |
| taxonomy | `/faq/{slug}` | — |
| forum_question | `/forum/{slug}` | `published_at` گذشته |

- **آدرس‌های دستگاه از قبل `/services/` هستند، نه `/repair/`** → sitemap فقط
  URLهای نهایی دارد.
- **صفحاتِ ترکیبی** (این نسخه اضافه شد): فقط ترکیب‌هایی که ادمین در
  combo-manager فعال کرده وارد sitemap می‌شوند — نه همهٔ ضرب‌درهم‌های ممکن.
- آیتم‌های `noindex`/منتشرنشده حذف می‌شوند؛ فایل‌های بزرگ به chunkهای ۵۰هزارتایی
  تقسیم می‌شوند.

چک‌لیست: در sitemap هیچ `/repair/`، هیچ URLِ noindex، و هیچ ریدایرکتی نباشد.

---

## بخش ۴ — robots.txt

پیش‌فرض (وقتی فیلدِ `robots_txt` در پنل خالی باشد):

```
User-agent: *
Allow: /

Sitemap: https://tamironline.com/v1/seo/sitemap-index.xml
```

- خطِ `Sitemap:` حتی اگر ادمین فراموش کند خودکار تضمین می‌شود.
- مسیرهای مهم بسته نشده‌اند (`Allow: /`).
- برای بستنِ مسیرهای بی‌ارزشِ خزش (اختیاری) از **سئو → robots.txt** در پنل
  `Disallow` اضافه کنید؛ مراقب باشید صفحاتِ مهم (`/services`, `/brands`,
  `/blog`, `/forum`) را نبندید.

---

## بخش ۵ — llms.txt

خروجیِ پیش‌فرض مطابقِ [llmstxt.org](https://llmstxt.org): تیترِ `#` + خلاصهٔ
`blockquote` + بخش‌های لینکِ Markdownِ **مطلق**. این نسخه علاوه بر صفحاتِ اصلی،
فهرستِ **دستگاه‌ها** (`/services/{slug}`) و **برندهای فعال** (`/brands/{slug}`)
را هم به‌صورتِ لینکِ واقعی درج می‌کند تا LLMها ساختارِ خدمات را بشناسند.

اگر ادمین مقدارِ سفارشی ثبت کند، همان بدونِ تغییر سرو می‌شود (فرمت را رعایت کنید:
لینک‌ها `[متن](https://...)`).

---

## بخش ۶ — Search Console (خارج از این ریپو)

این مرحله‌ها در GSC/فرانت انجام می‌شوند و کدِ پنل دخالتی ندارد:

- [ ] `sitemap-index.xml` در Search Console ثبت شود.
- [ ] چند URLِ مهمِ `/services/*` با URL Inspection بررسی و **Request Indexing** شوند.
- [ ] گزارشِ Coverage برای خطاهای باقی‌ماندهٔ ایندکس پایش شود.
- [ ] پس از اعمالِ ریدایرکت‌ها، نمونهٔ `/repair/*` تست شود که ۳۰۱ می‌دهد.

---

## خلاصهٔ «انجام‌شده در پنل» در این تغییر

- llms.txt: افزودنِ لینک‌های واقعیِ دستگاه‌ها و برندهای فعال.
- sitemap: افزودنِ صفحاتِ ترکیبیِ **فعالِ** `/services/{device}/{brand}`.
- (پیش‌تر) هیرو صفحهٔ ترکیبی → fallback به تصویرِ صفحهٔ دستگاه — نگاه کنید به
  `docs/BACKEND_COMBO_HERO_FALLBACK.md`.

آنچه می‌ماند: seedِ ریدایرکت‌های `/repair/→/services/` (با تأییدِ نگاشت) و
مرحله‌های Search Console.
