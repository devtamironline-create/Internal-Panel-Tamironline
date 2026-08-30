# نامهٔ نهایی به تیم توسعهٔ سایت (Next.js) — صفحات سئوی شهری (SEO-024)

این سند مرجعِ کاملِ پیاده‌سازیِ صفحاتِ شهری روی `tamironline.com` است.
پنل منبعِ داده و وضعیت است؛ **رندر، ۴۰۴ و منو سمتِ شماست.**

---

## ۰) دو اصلِ طلایی

1. **هر صفحه مستقل است.** لازم نیست صفحاتِ والد لایو باشند. اگر فقط
   `/mashhad/services/washing-machine` را منتشر کنید، باید **بدونِ هیچ
   مشکلی** کار کند — بردکرامب، متا و اسکیمایش کامل است، حتی اگر `/mashhad`
   یا `/mashhad/services` هنوز منتشر نشده باشند.
2. **فقط صفحاتِ «منتشرشده» وجود دارند.** هر مسیرِ دیگر زیرِ این الگوها باید
   **۴۰۴ واقعی** بدهد (`notFound()` در Next.js) — نه ریدایرکت، نه صفحهٔ خالی.

---

## ۱) درختِ صفحات و مسیرها

| نوع (`type`) | مسیر (`path`) | نمونه عنوان |
|---|---|---|
| `city` | `/{city}` | تعمیرات لوازم خانگی در مشهد |
| `services` | `/{city}/services` | خدمات تعمیرآنلاین در مشهد |
| `device` | `/{city}/services/{device}` | تعمیر لباسشویی در مشهد |
| `brands` | `/{city}/brands` | برندهای تحت پوشش در مشهد |
| `brand` | `/{city}/brands/{brand}` | تعمیرات لوازم خانگی بوش در مشهد |
| `combo` | `/{city}/services/{device}/{brand}` | تعمیر لباسشویی بوش در مشهد |

`{city}`/`{device}`/`{brand}` همان slugهای کاتالوگ‌اند (`mashhad`،
`washing-machine`، `bosch`). واژهٔ «نمایندگی» عمداً در عنوان‌ها نمی‌آید مگر
مجوزِ واقعی ثبت شده باشد.

---

## ۲) API محتوا

`GET /v1/customer/seo/city-pages` — عمومی، فقط‌خواندنی، کشِ ۱ ساعت.
پاسخ در پوششِ `{ "success": true, "data": … }` است.

### حالت فهرست (ساختِ مسیرها / `generateStaticParams` / sitemap)
```
GET /v1/customer/seo/city-pages            # همهٔ صفحاتِ منتشرشده
GET /v1/customer/seo/city-pages?city=mashhad   # فقط یک شهر
```

### حالت واکشیِ یک صفحه (رندرِ صفحه)
```
GET /v1/customer/seo/city-pages?path=/mashhad/services/washing-machine/bosch
```
- منتشرشده → `200` با آبجکتِ زیر.
- منتشرنشده یا ناموجود → **`404`** (همین را به `notFound()` وصل کنید).

### شکلِ هر آیتم
```json
{
  "path": "/mashhad/services/washing-machine/bosch",
  "type": "combo",
  "city":   { "name": "مشهد", "slug": "mashhad" },
  "device": { "name": "لباسشویی", "slug": "washing-machine" },
  "brand":  { "name": "بوش", "slug": "bosch" },

  "title": "…", "h1": "…",
  "eyebrow": "…|null", "subtitle": "…|null", "caption": "…|null",
  "content": "<p>…</p>|null",

  "hero_image": { "mobile": { "url": "…", "alt": "…" } } | null,
  "cta_primary":   { "label", "url", "icon" } | null,
  "cta_secondary": { "label", "url", "icon" } | null,
  "steps_image": { "desktop": "…|null", "mobile": "…|null" },
  "sections_enabled": { "hero": true, "faq": false, … } | null,

  "breadcrumbs": [
    { "label": "خانه",     "path": "/",                                  "current": false },
    { "label": "مشهد",     "path": "/mashhad",                           "current": false },
    { "label": "خدمات",    "path": "/mashhad/services",                  "current": false },
    { "label": "لباسشویی", "path": "/mashhad/services/washing-machine",  "current": false },
    { "label": "لباسشویی بوش", "path": "/mashhad/services/washing-machine/bosch", "current": true }
  ],

  "meta_title": "…|null", "meta_description": "…|null",
  "published_at": "2026-08-29T10:39:28+03:30"
}
```

### نکتهٔ تصویرِ Hero
`hero_image` حالا **یک تصویر** است (کلیدِ `mobile`). همان یک تصویر را در
دسکتاپ و موبایل استفاده کنید. اگر `null` بود از تصویرِ پیش‌فرضِ خودتان.

### نکتهٔ سکشن‌ها
`sections_enabled = null` یعنی «همه روشن». محتوای FAQ/نظرات در payloadِ صفحه
تکرار نمی‌شود؛ از همان endpointهای موجودِ FAQ/نظرات بخوانید (سکشن‌هایی که
`false` هستند را نسازید).

---

## ۳) بردکرامب — خودکار و مستقل

آرایهٔ `breadcrumbs` را پنل از روی **دادهٔ خودِ صفحه** می‌سازد و به وجودِ
صفحاتِ والد وابسته نیست. پس:
- مستقیماً همین آرایه را رِندر کنید (کوچک‌ترین منطق سمتِ شما).
- برای `BreadcrumbList` در JSON-LD هم از همین `path`/`label`ها استفاده کنید.
- آیتمِ `current:true` صفحهٔ فعلی است (بدونِ لینک نمایش دهید).
- اگر یک صفحهٔ میانی منتشر نشده، لینکش ممکن است ۴۰۴ بدهد؛ در آن حالت
  می‌توانید آن آیتم را به‌صورتِ متنِ ساده (بدون لینک) نشان دهید. توصیه:
  چون پنل کلِ درخت را می‌سازد، معمولاً صفحاتِ والد را هم منتشر کنید.

---

## ۴) متای سئوی حرفه‌ای (canonical / robots / OG / Schema)

مدیریتِ سئو در پنل **یکپارچه** است (یک پنلِ واحد برای همهٔ انواع صفحه —
دستگاه/برند/شهر). متایِ رزولوشن‌شده را از **همان endpointِ عمومیِ متا** که
برای بقیهٔ صفحات استفاده می‌کنید بگیرید:

```
GET /v1/seo/meta?type=city_page&slug={path}
```
- `type` همیشه ثابتِ `city_page` است (نه نوعِ صفحه).
- `{path}` دقیقاً همان `path` (با اسلشِ ابتدایی).
- خروجی: `title, description, canonical, robots, og, twitter, jsonld` —
  دقیقاً مثلِ بقیهٔ انواع. اگر صفحه `noindex` خورده باشد در `robots` می‌آید و
  از sitemap هم حذف است.

فیلدهای سادهٔ `title`/`meta_description` داخلِ payloadِ محتوا صرفاً fallbackِ
سریع‌اند؛ **مرجعِ نهاییِ متا، خروجیِ `/v1/seo/meta` است.**

---

## ۵) Sitemap

- صفحاتِ **منتشرشده** در `sitemap-local.xml` می‌آیند و در `sitemap.xml`
  معرفی شده‌اند.
- پیش‌نویس/بایگانی و صفحاتِ `noindex` خودکار حذف می‌شوند.
- اگر خودتان sitemap می‌سازید، از حالتِ فهرستِ API استفاده کنید و فقط
  `path`های برگشتی را بگذارید.

---

## ۶) چرخهٔ عمر (برای هماهنگی)

1. ساختِ شهرِ اصلی در پنل → کلِ درخت به‌صورت **پیش‌نویس** ساخته می‌شود.
2. مدیر بررسی و **منتشر** می‌کند → همان لحظه در API و sitemap ظاهر می‌شوند.
3. «بازگردانی به پیش‌نویس»/«بایگانی» → از API خارج و روی سایت باید ۴۰۴ شوند.

> توسعهٔ آینده: بردنِ شهرها زیرِ صفحهٔ استان (`/{province}/…`). فیلدِ
> `province_id` از حالا در داده هست تا این کار بدونِ تغییرِ ساختار ممکن باشد.

---

## چک‌لیستِ سمتِ سایت

- [ ] مسیرهای پویا فقط از `path`های API ساخته شوند؛ بقیه `notFound()`.
- [ ] هر صفحه مستقل رندر شود (بدون وابستگی به والد).
- [ ] بردکرامب از آرایهٔ `breadcrumbs` + JSON-LD `BreadcrumbList`.
- [ ] متا از `GET /v1/seo/meta?type=city_page&slug={path}`.
- [ ] یک تصویرِ Hero (`hero_image.mobile`) برای دسکتاپ و موبایل.
- [ ] سکشن‌های `sections_enabled=false` رندر نشوند.
- [ ] صفحاتِ پیش‌نویس/حذف‌شده در منو و لینک‌های داخلی نیایند.
