# ممیزی و بازطراحی Sitemap — تعمیرآنلاین

این سند پاسخِ درخواستِ تیمِ سئو برای بازطراحیِ Sitemap است. اطلاعاتِ حساس
(رمز دیتابیس، دسترسیِ سرور، سورسِ خصوصی) عمداً در آن نیست.

دامنهٔ اصلی و canonical: **https://tamironline.com/**

---

## ۱ و ۲) خروجیِ Excel/CSV صفحات و ریدایرکت‌ها

از پنل قابلِ دانلود است (مستقیم از دیتابیس، UTF-8 سازگار با اکسل):

- **CSV صفحات:** `سئو → ابزارهای سئو → دانلود CSV صفحات`
  (`/admin/seo/tools/audit/pages.csv`)
- **CSV ریدایرکت‌ها:** همان صفحه → `دانلود CSV ریدایرکت‌ها`
  (`/admin/seo/tools/audit/redirects.csv`)

فایلِ صفحات هر ردیف = یک URL، با ستون‌های خواسته‌شده:
`url, page_type, record_id, title, status, http_status, canonical_url,
meta_robots, is_indexable, created_at, updated_at, redirect_to, parent_url,
slug, template, is_active, language, image_url`

**شاملِ همهٔ حالت‌ها:** صفحاتِ فعال، غیرفعال (`is_active=no`)، پیش‌نویس
(`status=draft`)، حذف‌شده (`status=deleted`)، و ریدایرکت‌شده (ستونِ
`redirect_to` و `http_status=301/308`). ستونِ `is_indexable` مشخص می‌کند کدام
باید در sitemap بیایند.

فایلِ ریدایرکت‌ها: `old_url, new_url, status_code, match_type, is_active`.

---

## ۳) فایل‌های Sitemap فعلی

Sitemap **داینامیک** است و مستقیم از دیتابیس ساخته می‌شود (توسطِ
`Modules/Seo/Services/SitemapBuilder.php`). آدرس‌ها:

| فایل | URL |
|---|---|
| Sitemap index | `https://tamironline.com/v1/seo/sitemap-index.xml` |
| Sitemap هر نوع | `https://tamironline.com/v1/seo/sitemap/{type}.xml` |
| chunk (برای نوع‌های >۵۰هزار URL) | `.../sitemap/{type}-{page}.xml` |

`{type}` ∈ `page, article, blog_topic, brand, device, brand_device, taxonomy,
forum_question` (فقط نوع‌هایی که `sitemap=true` دارند).

> نکته: مسیرِ نهاییِ عمومی (مثلاً `/sitemap.xml`) روی دامنهٔ اصلی توسطِ فرانتِ
> Next.js به همین endpointها proxy می‌شود. اگر می‌خواهید `/sitemap.xml` و
> `/sitemap_index.xml` هم مستقیماً کار کنند، تیمِ فرانت باید rewrite اضافه کند
> (به بخشِ «سمتِ فرانت» پایین مراجعه کنید).

**منطقِ گنجاندن در sitemap** (از قبل درست است): فقط آیتم‌هایی که منتشرشده/فعال
و بدونِ `noindex` هستند. آیتمِ noindex یا پیش‌نویس خودکار حذف می‌شود.

---

## ۴) robots.txt فعلی

از `https://tamironline.com/v1/seo/robots.txt` سرو می‌شود (از پنل قابلِ ویرایش:
`سئو → تنظیمات → robots.txt`). نسخهٔ پیش‌فرض:

```
User-agent: *
Allow: /

Sitemap: https://tamironline.com/v1/seo/sitemap-index.xml
```

دایرکتیوِ `Sitemap:` همیشه تضمین می‌شود (حتی اگر ادمین فراموش کند).

> پیشنهادِ افزودن (اختیاری، در همان تنظیماتِ پنل) برای بستنِ مسیرهای بی‌ارزش:
> ```
> Disallow: /search
> Disallow: /*?*        # URLهای پارامتردار
> ```

---

## ۵) لیست Routeها و وضعیتِ ایندکس‌پذیری (صفحاتِ عمومیِ سایت)

الگوهای URL عمومی و ایندکس‌پذیریِ آن‌ها (منبع: `Modules/Seo/Config/config.php`):

| نوع | الگوی URL | Public/Private | Indexable | Dynamic |
|---|---|---|---|---|
| خانه | `/` | Public | ✅ | Static |
| صفحهٔ ثابت | `/{slug}` | Public | ✅ (اگر published) | Dynamic |
| مقاله | `/blog/{slug}` | Public | ✅ | Dynamic |
| موضوعِ بلاگ | `/blog/topic/{slug}` | Public | ✅ | Dynamic |
| خدمات (مادر) | `/services/{device}` | Public | ✅ (اگر is_active) | Dynamic |
| خدمات-برند | `/services/{device}/{brand}` | Public | ✅ (اگر combo فعال) | Dynamic |
| برند | `/brands/{slug}` | Public | ✅ (اگر is_active) | Dynamic |
| FAQ/تاکسونومی | `/faq/{slug}` | Public | ✅ | Dynamic |
| پرسشِ انجمن | `/forum/{slug}` | Public | ✅ (اگر approved) | Dynamic |

هر کدام canonicalِ self-referencing از `https://tamironline.com` (بدونِ www)
دارند و `og:url = canonical`.

---

## ۶) مسیرهای خصوصی/سیستمی که نباید ایندکس شوند

این‌ها یا روی **ساب‌دامینِ پنل** (`panel.tamironline.com`) هستند (کاملاً جدا از
دامنهٔ اصلی و اصلاً در sitemapِ سایت نیستند)، یا باید در فرانت noindex شوند:

- پنلِ ادمین: `panel.tamironline.com/admin/*` (ساب‌دامینِ جدا)
- API: `/api/*`, `/v1/*` (به‌جز sitemap/robots عمومی)
- احراز/حساب: `/login`, `/register`, `/auth/*`, `/account/*`, `/profile/*`
- اپ/داشبورد/سفارش/پرداخت: `/dashboard`, `/orders/*`, `/payment/*`, `/checkout/*`
- جست‌وجو و فیلترها: `/search`, و هر URL دارای **query parameter** (`?...`)
- صفحاتِ توکنی: رسیدِ انتقال/پیش‌فاکتور/فاکتور (`/crm/...` با token) — نباید
  ایندکس شوند و در sitemap نیستند.

هیچ‌کدام از این‌ها در sitemapِ داینامیک قرار نمی‌گیرند (چون فقط نوع‌های
محتوایی با `sitemap=true` اضافه می‌شوند).

---

## ۷) مشخصاتِ فنی سایت

| مورد | فناوری |
|---|---|
| Backend (پنل + API/BFF) | **Laravel 12** (PHP 8.4)، معماریِ ماژولار (nwidart/laravel-modules) |
| Frontend (سایتِ عمومی) | **Next.js** (ریپوی جدا) — SEO/متا را از API این بک‌اند می‌گیرد |
| CMS | ندارد — محتوا در همین پنلِ Laravel مدیریت می‌شود (Devices/Brands/Articles/Pages/Combo) |
| Database | **MySQL** |
| Server | Linux / cPanel (PHP 8.4) — پشتِ Cloudflare |
| SEO engine | ماژولِ `Seo` داخلِ همین بک‌اند (Sitemap/Robots/Canonical/Redirect/Meta داینامیک) |

---

## هدفِ Sitemap جدید (قواعدِ گنجاندن)

فقط URLهایی در sitemap بیایند که همهٔ این شرط‌ها را دارند — که موتورِ فعلی
از قبل رعایت می‌کند:

- HTTP = **200** · منتشرشده و **فعال** · **قابلِ ایندکس** (بدونِ noindex)
- **canonical به خودِ صفحه** · **بدونِ redirect** · **بدونِ query parameter**
- بدونِ صفحاتِ تکراری یا قدیمی

از CSV صفحات، ردیف‌هایی که `is_indexable=yes` و `redirect_to` خالی و
`canonical_url == url` دارند، دقیقاً مجموعهٔ نهاییِ sitemap هستند.

---

## سمتِ فرانت (Next.js) — لازم برای تکمیل

این‌ها در بک‌اند قابلِ انجام نیستند و به تیمِ فرانت مربوط‌اند:
1. rewrite کردنِ `/sitemap.xml` و `/sitemap_index.xml` و `/robots.txt` روی دامنهٔ
   اصلی به endpointهای `/v1/seo/...` (یا proxy از BFF).
2. `noindex` روی مسیرهای `/search`، صفحاتِ پارامتردار، و هر مسیرِ خصوصیِ فرانت.
3. اطمینان از اینکه لینک‌های داخلی مستقیم به نسخهٔ 200/canonical می‌روند (نه
   http/www یا redirect) — ابزارِ «اصلاح لینک‌های داخلی» در پنل بخشِ دیتابیسیِ
   این را انجام می‌دهد.
