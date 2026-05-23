# داکیومنت اتصال فرانت — Laravel v1 API

این سند برای تیم فرانت‌اند (Next.js) است. لیست تمام endpoint‌های موجود
نسخه‌ی `/v1/*` به همراه قراردادهای auth، validation، response و کش.

> این سند با کد همگام نگه داشته می‌شود. اگر فیلدی در پاسخ نمی‌بینید،
> سند را به‌روز کنید — حدس نزنید.

---

## ۰) شروع سریع

برای راه‌اندازی فرانت، حداقل به این موارد نیاز دارید:

```env
# frontend/.env.local
API_BASE_URL=https://panel.tamironline.com
INTERNAL_API_TOKEN=<از تیم بک‌اند بگیرید>
```

**حداقل fetchهای لازم برای رندر سایت:**

| صفحه | درخواست |
|---|---|
| همه‌ی صفحات (root layout) | `GET /v1/pages/layout` |
| صفحه اصلی | `GET /v1/pages/home` |
| درباره ما | `GET /v1/pages/about` |
| تماس با ما | `GET /v1/pages/contact` |
| صفحه دستگاه `/devices/{slug}` | `GET /v1/devices/{slug}` |

این پنج fetch تقریباً همه‌ی محتوای داینامیک سایت را پوشش می‌دهند. سایر endpointها برای کاربردهای خاص (catalog، activity carousel، فرم تماس) هستند.

**ارسال فرم تماس (تنها روت POST):**
```
POST /v1/contact-messages
Authorization: Bearer <INTERNAL_API_TOKEN>
```

برای کامپوننت‌های آماده (Header، Footer، FeatureMarquee، SeoFooter) به §۱۲ مراجعه کنید.

---

## ۱) اطلاعات کلی

| مورد | مقدار |
|---|---|
| Base URL (Local) | `http://localhost:8000` |
| Base URL (Staging) | `https://staging.tamironline.com` |
| Base URL (Production) | `https://panel.tamironline.com` |
| Format | JSON (UTF-8) |
| Time | ISO 8601 UTC — مثل `2026-05-19T10:34:00Z` |
| Locale پیام خطا | فارسی |
| Content-Type | `application/json; charset=utf-8` |

---

## ۲) احراز هویت

**دو نوع روت:**

| نوع | استفاده | احراز هویت |
|---|---|---|
| Public read | `GET /v1/*` | بدون توکن |
| Internal write | `POST /v1/*` | `Authorization: Bearer <INTERNAL_API_TOKEN>` |

**توکن internal:**
- فقط در `frontend/.env.local` ذخیره شود
- **هرگز** در `NEXT_PUBLIC_*` نیاید — افشای آن یعنی هر کسی می‌تواند فرم ثبت کند
- فقط Next BFF (سمت سرور Next) آن را ست می‌کند. مرورگر هرگز مستقیماً به Laravel وصل نمی‌شود

**نمونه `.env.local` فرانت:**
```env
API_BASE_URL=https://panel.tamironline.com
INTERNAL_API_TOKEN=<از تیم بک‌اند گرفته شود>
```

---

## ۳) ماتریس endpointها

| # | Method | Path | Auth | Throttle | Cache | اولویت | وضعیت |
|---|---|---|---|---|---|---|---|
| 1 | POST | `/v1/contact-messages` | internal.token | 10/min | — | P0 | ✅ |
| 2 | GET | `/v1/pages/{slug}` | — | 60/min | 300s | P0 | ✅ |
| 3 | GET | `/v1/devices/{slug}` | — | 60/min | 300s | P0 | ✅ |
| 4 | GET | `/v1/catalog/brands/{slug}` | internal.token | 60/min | 600s | P0 | ✅ |
| 5 | GET | `/v1/catalog/devices/{slug}` | internal.token | 60/min | 600s | P0 | ✅ |
| 6 | GET | `/v1/catalog/devices/{slug}/reviews` | — | 60/min | 60s | P0 | ✅ |
| 7 | POST | `/v1/catalog/devices/{slug}/reviews` | — | 5/min | — | P0 | ✅ |
| 8 | POST | `/v1/catalog/reviews/{id}/like` | — | 30/min | — | P0 | ✅ |
| 9 | GET | `/v1/activity/recent` | — | 60/min | 60s | P1 | ✅ |
| 10 | GET | `/v1/testimonials` | — | 60/min | 300s | P1 | ✅ |
| 11 | GET | `/v1/catalog/brands` | — | 60/min | 600s | P2 | ✅ |
| 12 | GET | `/v1/catalog/devices` | — | 60/min | 600s | P2 | ✅ |
| 13 | GET | `/v1/site/about-stats` | — | 60/min | 600s | P2 | ✅ |
| 14 | GET | `/v1/health` | — | 120/min | — | — | ✅ |

> **توجه:**
> - `/v1/catalog/devices/{slug}` و `/v1/catalog/brands/{slug}` از **الگوی Template + Override** استفاده می‌کنند — ادمین یک‌بار template را با placeholderها در `/admin/site/page-content/{device,brand}` تنظیم می‌کند، و هر دستگاه/برند می‌تواند فیلدهای خاصی را override کند. جزئیات کامل در `docs/BACKEND_DEVICE_PAGE_API.md` §۱۳.
> - Reviews public هستند (بدون internal.token) چون مرورگر کاربر مستقیماً POST می‌کند.
> - `/v1/activity/recent` پارامتر `?device={slug}` و `?brand={slug}` را می‌پذیرد (و alias های `device_slug` / `brand_slug` همچنان کار می‌کنند).

> **توجه:** detail endpoints (`/v1/catalog/brands/{slug}` و `/v1/catalog/devices/{slug}`) پشت `internal.token` هستن — فرانت BFF باید Bearer token بفرسته. جزئیات schema در `BACKEND_CATALOG_API.md`.

---

## ۴) جزئیات endpointها

### 4.1 `POST /v1/contact-messages`

ثبت فرم تماس صفحه‌ی Contact (سکشن C2).

**Headers:**
```http
Authorization: Bearer <INTERNAL_API_TOKEN>
Content-Type: application/json
```

**Request body:**
```json
{
  "fullName": "علی محمدی",
  "mobile": "09121234567",
  "email": "ali@example.com",
  "topic": "درخواست تعمیر",
  "message": "متن پیام ..."
}
```

**Validation rules:**

| فیلد | نوع | الزامی | قاعده |
|---|---|---|---|
| `fullName` | string | بله | 2..80 |
| `mobile` | string | بله | regex `^(?:\+98|0098|0)?9\d{9}$` |
| `email` | string | خیر | email معتبر، ≤120 |
| `topic` | enum | بله | یکی از مقادیر زیر |
| `message` | string | بله | 10..2000 |

**مقادیر مجاز `topic`:**
- `درخواست تعمیر`
- `پیگیری سفارش`
- `شکایت یا پیشنهاد`
- `همکاری تجاری`
- `سایر موارد`

**Response 201:**
```json
{
  "id": "01HXYZABCDEFGHJKMN",
  "created_at": "2026-05-19T10:34:00Z"
}
```

**Response 422 (validation):**
```json
{
  "message": "...",
  "errors": {
    "mobile": ["شماره موبایل معتبر نیست."],
    "topic": ["موضوع انتخاب‌شده معتبر نیست."]
  }
}
```

**Response 401:** اگر توکن نامعتبر/غایب: `{ "message": "Unauthorized" }`
**Response 429:** اگر rate-limit؛ هدر `Retry-After` بررسی شود.

---

### 4.2 `GET /v1/pages/{slug}`

محتوای ساختاریافته‌ی یک صفحه شامل تمام سکشن‌ها — قلب اتصال محتوای پویا.

**Path params:**

| پارامتر | نوع | قاعده |
|---|---|---|
| `slug` | string | فقط حروف انگلیسی — `home` \| `about` \| `contact` \| `layout` |

**Request example:**
```
GET /v1/pages/home
```

**Response 200:**
```json
{
  "slug": "home",
  "sections": {
    "hero": {
      "title": "تعمیرکار درست در درست‌ترین زمان",
      "subtitle": "...",
      "cta_label": "ثبت سفارش",
      "cta_url": "/order",
      "services": [1, 2, 3],
      "services_items": [
        { "id": 1, "label": "تعمیر لباسشویی", "slug": "washing-machine", "href": "/devices/washing-machine", "icon": "washing-machine", "thumbnail": "https://panel.tamironline.com/storage/site/devices/wm.png", "tone": "tone-blue" },
        { "id": 2, "label": "تعمیر ظرفشویی", "slug": "dishwasher",      "href": "/devices/dishwasher",      "icon": "droplets",         "thumbnail": null, "tone": "tone-green" },
        { "id": 3, "label": "تعمیر یخچال",   "slug": "refrigerator",    "href": "/devices/refrigerator",    "icon": "refrigerator",     "thumbnail": null, "tone": "tone-cyan" }
      ]
    },
    "why_us": {
      "title": "چرا تعمیرآنلاین؟",
      "items": [
        { "icon": "check", "title": "تضمین کیفیت", "description": "..." }
      ]
    },
    "steps": {
      "title": "...",
      "image": { "desktop": "https://cdn/.../steps.png", "mobile": "https://cdn/.../steps-mobile.png" },
      "alt": "..."
    },
    "promo": {
      "title": "...",
      "image": { "desktop": "https://...", "mobile": "https://..." },
      "link_url": "...",
      "link_label": "..."
    },
    "faq": {
      "title": "پرسش‌های متداول",
      "faq_ids": ["01HX...", "01HY..."],
      "faq_ids_items": [
        { "id": "01HX...", "question": "...", "answer": "..." },
        { "id": "01HY...", "question": "...", "answer": "..." }
      ]
    }
  }
}
```

**نکات مهم برای فرانت:**
- فقط سکشن‌های `is_published=true` در پاسخ هستند. سکشن غایب = خالی نمایش بده.
- **فیلدهای reference خودکار hydrate می‌شوند.** برای هر فیلد reference، یک آرایه‌ی IDها در `<field>` و یک آرایه‌ی کامل آیتم‌ها در `<field>_items` می‌بینید. فرانت از `<field>_items` استفاده کند.
- **Hero.services فال‌بک خودکار:** اگر در `/v1/pages/home` ادمین هیچ device انتخاب نکرده باشد، `services_items` به‌صورت خودکار با همه‌ی دستگاه‌های فعال CRM (مرتب‌شده با is_featured و sort_order) پر می‌شود.
- **تصاویر responsive:** فیلدهایی با کلیدهای `image`, `poster`, `logo` و... به‌صورت `{ desktop, mobile }` برمی‌گردند. اگر `mobile` خالی باشد، فرانت می‌تواند `desktop` را در همه‌ی viewportها استفاده کند.
- ساختار payload هر سکشن دقیقاً مطابق schema در ادمین است — اگر فیلدی در ادمین خالی بماند، در پاسخ `null` یا غایب است.
- `repeater` فیلدها به‌صورت آرایه‌ی JSON برمی‌گردند.

**Response 404:**
```json
{ "message": "Page not found" }
```
وقتی slug در schema تعریف نشده باشد.

**Headers:**
```http
Cache-Control: public, max-age=300, s-maxage=300
```

#### سکشن‌های موجود

##### Home (`/v1/pages/home`)
| section_key | فیلدها |
|---|---|
| `hero` | title, subtitle, cta_label, cta_url, services[int], **services_items**[id, label, slug, href, icon, tone] |
| `why_us` | title, subtitle, items[icon, title, description] |
| `steps` | title, image{desktop, mobile}, alt |
| `promo` | title, subtitle, image{desktop, mobile}, link_url, link_label |
| `faq` | title, subtitle, faq_ids[], **faq_ids_items[]** |

##### About (`/v1/pages/about`)
| section_key | فیلدها |
|---|---|
| `hero` | title, subtitle, aparat_id, poster{desktop, mobile}, description, **highlights[icon, text]** |
| `stats` | title, subtitle, items[key, value, label, tone] |
| `values` | title, subtitle, items[icon, title, description] |
| `steps` | title, image{desktop, mobile}, alt |
| `timeline` | title, items[year, title, description] |
| `faq` | title, subtitle, faq_ids[], **faq_ids_items[]** |
| `promo` | title, subtitle, image{desktop, mobile}, link_url, link_label |

> **stats** اکنون داخل `/v1/pages/about` است (پیشنهاد B). endpoint جداگانه `/v1/site/about-stats` فعلاً برای backward compat نگه‌داری می‌شود ولی deprecated است.

##### Layout (`/v1/pages/layout`) — هدر، فوتر و المان‌های مشترک
| section_key | فیلدها |
|---|---|
| `header` | logo{desktop, mobile}, logo_alt, cta_label, cta_url, phone_label, phone_number, nav_items[label, href], **services_dropdown**{trigger_label, title, subtitle, view_all_label, view_all_url, device_ids[], **device_ids_items**[]} |
| `footer` | logo{desktop, mobile}, description, groups[title, links], **contact_info**{title, address, phone, phone_display, email}, **app_download**{title, subtitle, image{desktop, mobile}, stores[name, icon, url, image]}, social[platform, icon, url], copyright_text, enamad_code |
| `service_features` | aria_label, speed, items[icon_key, label, bg, fg, border] |
| `seo_footer` | title, **expand_label**, **collapse_label**, paragraphs[text] |
| `mobile_cta` | is_active, **primary**{label, icon, type, value}, **secondary**{label, icon, type, value} |

> **services_dropdown**, **contact_info**, **app_download**, **primary**, **secondary** زیرگروه (nested group) هستند و در پاسخ JSON به‌صورت object تو در تو ظاهر می‌شوند.
> **service_features** نوار افقی ویژگی‌ها (FeatureMarquee) که فرانت روی همه‌ی صفحات تکرار می‌کند.
> **seo_footer** بلوک متن سئوی پایین صفحه (با expand/collapse) — جزئیات در §۱۲.۲.
> **mobile_cta** نوار چسبیده به پایین موبایل با ۲ دکمه (تماس / سفارش).

##### Contact (`/v1/pages/contact`)
| section_key | فیلدها |
|---|---|
| `channels` | title, items[icon, title, value, link_url, description] |
| `info` | phone, support_phone, email, address |
| `hours` | note, items[day, hours] |
| `map` | lat, lng, neshan_url, zoom |
| `social` | items[platform, label, url, icon] |
| `faq` | title, subtitle, faq_ids[], **faq_ids_items[]** |

---

### 4.3 `GET /v1/devices/{slug}`

محتوای صفحه‌ی یک دستگاه — `/devices/{slug}` در فرانت Next.js.

**Path params:**

| پارامتر | نوع | قاعده |
|---|---|---|
| `slug` | string | kebab-case، مثل `washing-machine`، `dishwasher` |

**Request example:**
```
GET /v1/devices/washing-machine
```

**Response 200:**
```json
{
  "device": {
    "id": 1,
    "label": "تعمیر لباس‌شویی",
    "slug": "washing-machine",
    "href": "/devices/washing-machine",
    "icon": "washing-machine",
    "thumbnail": "https://panel.tamironline.com/storage/site/devices/wm.png",
    "tone": "tone-blue"
  },
  "sections": {}
}
```

**Response 404:** `{ "message": "Device not found" }` اگر slug در CRM موجود نباشد یا غیرفعال باشد.

**فیلد `sections`:**
- در حال حاضر خالی (`{}`) برمی‌گردد چون صفحه‌ی الگوی `device` در schema تعریف نشده.
- اگر در آینده schema با page `device` گسترش یابد (سکشن‌های مشترک برای همه‌ی دستگاه‌ها)، آن سکشن‌ها اینجا برمی‌گردند و **Placeholderها بر اساس همین دستگاه جایگزین می‌شوند**.

**Placeholderها در FAQ و Testimonial:**
- اگر متن سوال در ادمین `تعمیر {device} چقدر زمان می‌برد؟` باشد
- در پاسخ `/v1/devices/washing-machine` تبدیل می‌شود به: `تعمیر تعمیر لباس‌شویی چقدر زمان می‌برد؟`
- لیست placeholderهای پشتیبانی‌شده در §۱۱

**Headers:**
```http
Cache-Control: public, max-age=300, s-maxage=300
```

---

### 4.4 `GET /v1/activity/recent`

فعالیت‌های زنده‌ی سایت — برای ایجاد حس فعال‌بودن. **دیتا تولیدی (فیک) است**، نه از سفارشات واقعی. منبع: ترکیب دستگاه‌های فعال CRM × لیست مناطق `config('site.activity-areas')` × زمان تصادفی.

**Query params:**

| پارامتر | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `limit` | int | 10 | 1..50 |
| `device_slug` | string \| — | — | محدود به یک دستگاه (برای صفحه‌ی دستگاه) |
| `brand_slug` | string \| — | — | محدود به یک برند (برای صفحه‌ی برند) |

**کاربردهای فیلتر:**

| صفحه فرانت | درخواست |
|---|---|
| Home (`/`) | `GET /v1/activity/recent?limit=10` |
| Device page (`/devices/washing-machine`) | `GET /v1/activity/recent?device_slug=washing-machine&limit=10` |
| Brand page (`/brands/lg`) | `GET /v1/activity/recent?brand_slug=lg&limit=10` |
| Device + Brand combo | `GET /v1/activity/recent?device_slug=washing-machine&brand_slug=lg` |

**Response 200:**
```json
{
  "data": [
    {
      "device_slug": "washing-machine",
      "device_label": "تعمیر لباس‌شویی ال‌جی",
      "brand_slug": "lg",
      "brand_label": "ال‌جی",
      "area": "تهران، سعادت‌آباد",
      "status": "completed",
      "minutes_ago": 4
    }
  ]
}
```

**فیلدها:**

| فیلد | نوع | توضیح |
|---|---|---|
| `device_slug` | string | برای لینک به صفحه‌ی دستگاه |
| `device_label` | string | متن نمایش — اگر `brand_slug` داده شده، `"تعمیر {device} {brand}"`، در غیر این صورت `"تعمیر {device}"` |
| `brand_slug` | string \| null | فقط در پاسخ‌های فیلترشده با `brand_slug` پر است |
| `brand_label` | string \| null | همانند بالا |
| `area` | string | یکی از مناطق تهران/کرج |
| `status` | enum | `completed` (۷۵٪) \| `in_progress` (۲۵٪) |
| `minutes_ago` | int | توزیع: ۶۰٪ < ۳۰ دقیقه، ۳۰٪ < ۶ ساعت، ۱۰٪ < ۴۸ ساعت |

**Headers:**
```http
Cache-Control: public, max-age=60, s-maxage=60
```

**نکات پیاده‌سازی سرور:**
- داده تصادفی ولی **با seed مبتنی بر دقیقه** — در پنجره‌ی ۶۰ ثانیه‌ای کش، dataset ثابت می‌ماند سپس rotate می‌شود
- اگر `device_slug` یا `brand_slug` معتبر نباشد، `data: []` برمی‌گردد
- منطقه‌ها فقط شهر/محله هستند، آدرس کامل نیست
- نام مشتری، شماره تلفن یا اطلاعات شناسایی هیچ‌گاه در پاسخ نیست (دیتا فیک)

---

### 4.5 `GET /v1/testimonials`

نظرات مشتریان — سکشن H5 (Home) و A6 (About) از یک endpoint مشترک.

**Query params:**

| پارامتر | نوع | پیش‌فرض | قاعده |
|---|---|---|---|
| `limit` | int | 12 | 1..50 |

**Request example:**
```
GET /v1/testimonials?limit=12
```

**Response 200:**
```json
{
  "data": [
    {
      "id": "01HXYZABCDEFGHJKMN",
      "customer_name": "امیرحسین",
      "topic": "تعمیر ماشین لباسشویی",
      "rating": 5,
      "audio_url": "https://cdn.example.com/voice/01HX.mp3",
      "duration_seconds": 18,
      "published_at": "2026-04-01T10:00:00Z"
    }
  ]
}
```

**فیلدها:**

| فیلد | نوع | الزامی | توضیح |
|---|---|---|---|
| `id` | ULID (string) | بله | برای key در React |
| `customer_name` | string ≤80 | بله | نام مشتری |
| `topic` | string ≤120 | بله | موضوع سفارش |
| `rating` | int 1..5 | بله | تعداد ستاره |
| `audio_url` | string | خیر | URL صوتی روی CDN |
| `duration_seconds` | int | خیر | مدت زمان صوتی برای نمایش `00:18` |
| `published_at` | ISO 8601 UTC | بله | مرتب‌سازی |

**فیلتر سرور:** فقط `is_published=true`، مرتب با `sort_order ASC, published_at DESC`.

**Headers:**
```http
Cache-Control: public, max-age=300, s-maxage=300
```

---

### 4.6 `GET /v1/catalog/brands`

برندهای تحت پوشش — سکشن H6 صفحه‌ی Home. منبع: جدول `crm_brands` در ماژول CRM.

**Query params:**

| پارامتر | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `featured` | bool | false | فقط برندهای ویژه (`is_featured=true`) |
| `limit` | int | 50 | 1..100 |

**Request example:**
```
GET /v1/catalog/brands?featured=true&limit=20
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "ال‌جی",
      "slug": "lg",
      "logo": "https://cdn.example.com/brands/lg.png"
    }
  ]
}
```

**فیلدها:**

| فیلد | نوع | الزامی | توضیح |
|---|---|---|---|
| `id` | int | بله | شناسه‌ی برند |
| `name` | string | بله | نام فارسی |
| `slug` | string | بله | کلید پایدار (kebab-case) |
| `logo` | string \| null | خیر | URL لوگو |

**فیلتر سرور:**
- فقط `is_active=true`
- اگر `featured=true`، اضافه‌تر فقط `is_featured=true`
- مرتب: `sort_order ASC, name ASC`

**Headers:**
```http
Cache-Control: public, max-age=600, s-maxage=600
```

**مدیریت ادمین:**
- مسیر: `/admin/crm/brands`
- ادمین می‌تواند پرچم «برند ویژه» را روی هر برند فعال/غیرفعال کند
- ترتیب نمایش با فیلد `sort_order` تنظیم می‌شود

**کاربرد در فرانت:**
- سکشن H6 صفحه‌ی Home → `?featured=true` (مثلاً ۱۲ برند ویژه با لوگو)
- صفحه‌ی «همه برندها» → بدون پارامتر (همه‌ی برندهای فعال)
- آرشیو/جستجو بر اساس `slug` (URLهای پایدار)

---

### 4.7 `GET /v1/catalog/devices`

دستگاه‌های قابل تعمیر — منبع: جدول `crm_devices` در ماژول CRM.

این endpoint منبع حقیقت برای فهرست دستگاه‌هاست. در صفحه‌ی Home از طریق
`/v1/pages/home` به‌صورت embed (در `sections.hero.services_items`) هم در دسترس
است، اما فرانت برای صفحات `/devices`، منوی هدر یا صفحه‌ی دستگاه می‌تواند
مستقیماً این endpoint را صدا بزند.

**Query params:**

| پارامتر | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `featured` | bool | false | فقط دستگاه‌های ویژه (`is_featured=true`) |
| `limit` | int | 50 | 1..100 |

**Request example:**
```
GET /v1/catalog/devices?limit=20
GET /v1/catalog/devices?featured=true
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "label": "تعمیر لباسشویی",
      "slug": "washing-machine",
      "href": "/devices/washing-machine",
      "icon": "washing-machine",
      "thumbnail": "https://panel.tamironline.com/storage/site/devices/abc.png",
      "tone": "tone-blue"
    },
    {
      "id": 2,
      "label": "تعمیر ظرفشویی",
      "slug": "dishwasher",
      "href": "/devices/dishwasher",
      "icon": "droplets",
      "thumbnail": null,
      "tone": "tone-green"
    }
  ]
}
```

**فیلدها:**

| فیلد | نوع | الزامی | توضیح |
|---|---|---|---|
| `id` | int | بله | شناسه‌ی دستگاه |
| `label` | string | بله | متن نمایش فارسی (همان `name` در CRM) |
| `slug` | string | بله | کلید پایدار (kebab-case) — برای URL |
| `href` | string | بله | لینک از پیش ساخته‌شده: `/devices/{slug}` |
| `icon` | string \| null | خیر | کلید آیکن Lucide به‌صورت kebab-case (مثل `washing-machine`, `droplets`, `refrigerator`) |
| `thumbnail` | string \| null | خیر | URL کامل تصویر بندانگشتی (در صورت آپلود توسط ادمین). فرانت می‌تواند آن را در کنار/به‌جای آیکن نمایش دهد |
| `tone` | string \| null | خیر | کلاس CSS رنگ کارت — مقادیر مجاز: `tone-blue`, `tone-green`, `tone-cyan`, `tone-sky`, `tone-orange`, `tone-amber`, `tone-rose`, `tone-violet`, `tone-emerald` |

**فیلتر سرور:**
- فقط `is_active=true`
- اگر `featured=true`، اضافه‌تر فقط `is_featured=true`
- مرتب: `is_featured DESC, sort_order ASC, name ASC`
  (ابتدا ویژه‌ها، سپس بقیه با ترتیب ادمین)

**Headers:**
```http
Cache-Control: public, max-age=600, s-maxage=600
```

**مدیریت ادمین:**
- مسیر: `/admin/crm/devices`
- فیلدهای قابل‌ویرایش: `name`, `slug`, `icon`, `tone`, `sort_order`, `is_active`, `is_featured`
- پرچم `is_featured` تعیین می‌کند کدام دستگاه‌ها در Hero صفحه‌ی Home (سکشن H1) به‌عنوان پیش‌فرض نمایش داده شوند

**کاربرد در فرانت:**

**۱. صفحه‌ی Home — سکشن Hero (H1):**
دو راه:
- **توصیه:** از `/v1/pages/home` بگیر و از `sections.hero.services_items` استفاده کن. این روش به ادمین اجازه می‌دهد دستگاه‌های دلخواه و ترتیب خاصی برای Hero مشخص کند. اگر ادمین چیزی انتخاب نکرد، خودکار به همه‌ی دستگاه‌های فعال (با `is_featured` اول) فال‌بک می‌شود.
- **جایگزین:** `/v1/catalog/devices?featured=true` — اگر فقط دستگاه‌های ویژه را می‌خواهی بدون امکان override توسط ادمین.

**۲. صفحه‌ی فهرست دستگاه‌ها (`/devices`):**
```
GET /v1/catalog/devices
```
همه‌ی دستگاه‌های فعال را برمی‌گرداند.

**۳. مپ آیکن در فرانت:**
چون `icon` یک string identifier است، فرانت باید یک مپ از kebab-case به کامپوننت Lucide داشته باشد:
```typescript
import { WashingMachine, Droplets, Refrigerator, Snowflake, Flame, Microwave, Package } from 'lucide-react';

const iconMap: Record<string, LucideIcon> = {
  'washing-machine': WashingMachine,
  'droplets':        Droplets,
  'refrigerator':    Refrigerator,
  'snowflake':       Snowflake,
  'flame':           Flame,
  'microwave':       Microwave,
  'package':         Package,
  // ...
};

function ServiceIcon({ name }: { name: string | null }) {
  const Icon = name ? iconMap[name] : null;
  return Icon ? <Icon className="h-7 w-7" strokeWidth={1.6} /> : null;
}
```

**۴. نام آیکن‌های مرسوم:**
فرانت با ادمین هماهنگ کند که چه آیکن‌هایی پشتیبانی می‌شوند. مقادیر `icon` که در ادمین CRM ست می‌شوند باید در `iconMap` فرانت وجود داشته باشند، در غیر این صورت آیکن خالی برگشت داده می‌شود.

---

### 4.8 `GET /v1/site/about-stats`

آمار صفحه‌ی About — سکشن A2.

**Request example:**
```
GET /v1/site/about-stats
```

**Response 200:**
```json
{
  "data": [
    { "key": "experience_years", "value": "۱۵+",     "label": "سال تجربه",     "tone": "blue"   },
    { "key": "repairs_count",    "value": "۵۰,۰۰۰+", "label": "تعمیر موفق",   "tone": "green"  },
    { "key": "technicians",      "value": "۲۰۰+",    "label": "تکنسین متخصص", "tone": "amber"  },
    { "key": "satisfaction",     "value": "۹۸٪",     "label": "رضایت مشتری",   "tone": "rose"   }
  ]
}
```

**فیلدها:**

| فیلد | نوع | توضیح |
|---|---|---|
| `key` | string | شناسه پایدار (snake_case، انگلیسی) |
| `value` | string | عدد به‌صورت فارسی فرمت‌شده (سرور فرمت می‌کند) |
| `label` | string | برچسب فارسی |
| `tone` | enum | `blue` \| `green` \| `amber` \| `rose` \| `violet` |

**فیلتر سرور:** فقط `is_published=true`، مرتب با `sort_order ASC`.

**Headers:**
```http
Cache-Control: public, max-age=600, s-maxage=600
```

---

### 4.9 `GET /v1/health`

سلامت سرویس برای health-check (CI/CD، Uptime monitoring).

**Response 200 (سالم):**
```json
{ "status": "ok", "db": "ok" }
```

**Response 503 (مشکل DB):**
```json
{ "status": "degraded", "db": "error" }
```

---

## ۵) قرارداد خطا

تمام خطاها پاسخ JSON می‌دهند:

| Status | معنی | بدنه |
|---|---|---|
| 200 | موفق | داده |
| 201 | ایجاد شد | داده |
| 401 | توکن internal نامعتبر/غایب | `{ "message": "Unauthorized" }` |
| 404 | route یافت نشد | `{ "message": "Not Found" }` |
| 422 | validation error | `{ "message": "...", "errors": {} }` |
| 429 | rate limit | header `Retry-After: <sec>` |
| 500 | خطای سرور (در production بدون stack-trace) | `{ "message": "Server error" }` |
| 503 | سرویس در دسترس نیست | `{ "message": "..." }` |

---

## ۶) نمونه‌ی Next.js BFF

### POST /v1/contact-messages

```typescript
// app/api/contact/route.ts
import { NextResponse } from 'next/server';

export async function POST(req: Request) {
  const body = await req.json();

  const res = await fetch(`${process.env.API_BASE_URL}/v1/contact-messages`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.INTERNAL_API_TOKEN!}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(body),
    cache: 'no-store',
  });

  const data = await res.json();
  return NextResponse.json(data, { status: res.status });
}
```

### GET با ISR — testimonials

```typescript
// app/api/testimonials/route.ts
import { NextResponse } from 'next/server';

export const revalidate = 300; // 5 minutes

export async function GET() {
  const res = await fetch(`${process.env.API_BASE_URL}/v1/testimonials`, {
    next: { revalidate: 300, tags: ['testimonials'] },
  });
  const data = await res.json();
  return NextResponse.json(data, { status: res.status });
}
```

### GET فعالیت زنده با کش کوتاه

```typescript
// app/api/activity/route.ts
import { NextResponse } from 'next/server';

export const revalidate = 60;

export async function GET() {
  const res = await fetch(`${process.env.API_BASE_URL}/v1/activity/recent?limit=10`, {
    next: { revalidate: 60, tags: ['activity'] },
  });
  const data = await res.json();
  return NextResponse.json(data, { status: res.status });
}
```

---

## ۷) Server Component pattern (RSC)

برای سکشن‌های static-ish، می‌توان مستقیم در Server Component fetch کرد و
از revalidation سر Next استفاده کرد:

```typescript
// app/(site)/page.tsx
async function getTestimonials() {
  const res = await fetch(`${process.env.API_BASE_URL}/v1/testimonials`, {
    next: { revalidate: 300 },
  });
  if (!res.ok) return { data: [] };
  return res.json();
}

export default async function HomePage() {
  const { data: testimonials } = await getTestimonials();
  return <TestimonialsSection items={testimonials} />;
}
```

---

## ۸) وضعیت سکشن‌های صفحات

### Home (`/`)

| سکشن | API | روش |
|---|---|---|
| H1. Hero (services list) | ✅ | `GET /v1/pages/home` → `sections.hero` |
| H2. Live Activity | ✅ | `GET /v1/activity/recent` |
| H3. Why Us | ✅ | `GET /v1/pages/home` → `sections.why_us` |
| H4. Steps Image | ✅ | `GET /v1/pages/home` → `sections.steps` |
| H5. Testimonials | ✅ | `GET /v1/testimonials` |
| H6. Brands | ✅ | `GET /v1/catalog/brands?featured=true` |
| H7. Promo Banner | ✅ | `GET /v1/pages/home` → `sections.promo` |
| H8. FAQ | ✅ | `GET /v1/pages/home` → `sections.faq.faq_ids_items` |
| H9. Booking form | ⏭️ حذف موقت | — |

### About (`/about`)

| سکشن | API | روش |
|---|---|---|
| A1. About Hero (video) | ✅ | `GET /v1/pages/about` → `sections.hero` |
| A2. Stats | ✅ | `GET /v1/site/about-stats` |
| A3. Values | ✅ | `GET /v1/pages/about` → `sections.values` |
| A4. Steps Image | ✅ | `GET /v1/pages/about` → `sections.steps` |
| A5. Timeline | ✅ | `GET /v1/pages/about` → `sections.timeline` |
| A6. Testimonials | ✅ | `GET /v1/testimonials` (همان H5) |
| A7. About FAQ | ✅ | `GET /v1/pages/about` → `sections.faq.faq_ids_items` |
| A8. Promo Banner | ✅ | `GET /v1/pages/about` → `sections.promo` |

### Contact (`/contact`)

| سکشن | API | روش |
|---|---|---|
| C1. Channels | ✅ | `GET /v1/pages/contact` → `sections.channels` |
| C2. Form | ✅ | `POST /v1/contact-messages` |
| C3. Info | ✅ | `GET /v1/pages/contact` → `sections.info` |
| C4. Hours | ✅ | `GET /v1/pages/contact` → `sections.hours` |
| C5. Map | ✅ | `GET /v1/pages/contact` → `sections.map` |
| C6. Social | ✅ | `GET /v1/pages/contact` → `sections.social` |
| C7. FAQ | ✅ | `GET /v1/pages/contact` → `sections.faq.faq_ids_items` |

### Layout (هدر، فوتر، نوار ویژگی‌ها)

| المان | API | روش |
|---|---|---|
| Header (لوگو، منو، CTA) | ✅ | `GET /v1/pages/layout` → `sections.header` |
| Footer (لوگو، توضیح، گروه‌ها، شبکه‌های اجتماعی، کپی‌رایت، انماد) | ✅ | `GET /v1/pages/layout` → `sections.footer` |
| Service Features (نوار ویژگی‌های ثابت) | ✅ | `GET /v1/pages/layout` → `sections.service_features` |

**نکته:** برای صفحات کامل، فرانت می‌تواند یک fetch به `GET /v1/pages/{slug}`
بزند و تمام سکشن‌های آن صفحه را یکجا بگیرد. Layout هم یک‌بار در `app/layout.tsx`
fetch می‌شود و در همه‌ی صفحات استفاده می‌شود — جزئیات کامل در §۱۲.

---

## ۹) راهنمای Featured برای Brands و Devices

دو entity در CRM (و فقط این دو) پرچم `is_featured` دارند:

### Brand
```
crm_brands:
  - is_active: bool        ← آیا برند فعال است؟
  - is_featured: bool      ← آیا در صفحه‌ی اصلی نمایش داده شود؟
  - sort_order: int        ← ترتیب نمایش
```

### Device
```
crm_devices:
  - is_active: bool        ← آیا دستگاه قابل نمایش است؟
  - is_featured: bool      ← آیا در Hero صفحه‌ی Home به‌عنوان پیش‌فرض بیاید؟
  - sort_order: int        ← ترتیب نمایش
  - icon: string|null      ← کلید آیکن Lucide (kebab-case)
  - thumbnail: string|null ← URL کامل تصویر بندانگشتی (بعد از resolve)
  - tone: string|null      ← کلاس CSS رنگ (tone-blue, ...)
```

### مدیریت تصویر در پنل ادمین

برای هر دو entity، صفحه‌ی ویرایش یک **آپلودر دوگانه** دارد:

1. **آپلود فایل**: ادمین می‌تواند فایل را با دکمه‌ی «انتخاب فایل» آپلود کند.
   فایل در `storage/app/public/site/brands/` یا `storage/app/public/site/devices/`
   ذخیره می‌شود و در پاسخ API به‌صورت URL کامل برمی‌گردد.
2. **URL خارجی**: یا می‌تواند URL کامل (مثلاً CDN) را در فیلد URL paste کند.

**برای فرانت تفاوتی ندارد** — API همیشه یک URL کامل برمی‌گرداند.

نکته: اگر فیلد خالی باشد (`null`)، فرانت باید فال‌بک خود را اعمال کند:
- برای device: نمایش `icon` (Lucide) به‌جای thumbnail
- برای brand: نمایش text-only یا placeholder

### قانون اولویت فال‌بک

برای **Hero (H1) صفحه‌ی Home**:

| منبع داده | اولویت |
|---|---|
| ۱. `services_items` در `GET /v1/pages/home` | اول — اگر ادمین در پنل سایت دستگاه انتخاب کرده باشد |
| ۲. همه‌ی `devices` فعال با ترتیب `is_featured DESC, sort_order ASC` | فال‌بک خودکار وقتی ادمین چیزی انتخاب نکرده |

این یعنی فرانت **فقط یک‌بار** `GET /v1/pages/home` صدا می‌زند و `services_items`
**همیشه** پر است (هیچ‌وقت `[]` نیست مگر هیچ device فعالی در CRM نباشد).

### قانون اولویت برای brands

برای **سکشن H6 صفحه‌ی Home** (لیست برندها):

| منبع داده | استفاده |
|---|---|
| `GET /v1/catalog/brands?featured=true` | پیشنهادی — فقط برندهای ویژه |
| `GET /v1/catalog/brands` | همه‌ی برندهای فعال |

برخلاف devices، **برندها در `/v1/pages/home` embed نمی‌شوند** — فرانت مستقیماً
endpoint کاتالوگ را صدا می‌زند.

### مثال کامل ServiceCard

با توجه به فرمت پاسخ `/v1/catalog/devices` که فیلد `tone` و `icon` را شامل می‌شود:

```typescript
type Service = {
  id: number;
  label: string;
  slug: string;
  href: string;
  icon: string | null;        // 'washing-machine' | 'droplets' | ...
  thumbnail: string | null;   // URL کامل (یا null)
  tone: string | null;        // 'tone-blue' | 'tone-green' | ...
};

function ServiceCard({ s, i }: { s: Service; i: number }) {
  const Icon = s.icon ? iconMap[s.icon] : null;
  return (
    <Link
      href={s.href}
      className="group fade-up relative flex flex-col items-center gap-3 rounded-2xl border bg-white px-3 py-5 transition-all duration-300 hover:-translate-y-1 active:scale-[0.97] md:gap-2.5 md:px-2 md:py-5"
      style={{
        borderColor: 'var(--border)',
        boxShadow: 'var(--sh-sm)',
        animationDelay: `${i * 50 + 300}ms`,
      }}
    >
      <span className={`icon-tile icon-tile--xl ${s.tone ?? 'tone-blue'} transition-transform duration-300 group-hover:scale-105`}>
        {/* اولویت: thumbnail آپلودشده، در غیر این صورت آیکن Lucide */}
        {s.thumbnail ? (
          <img src={s.thumbnail} alt={s.label} className="h-10 w-10 object-contain" />
        ) : Icon ? (
          <Icon className="h-7 w-7" strokeWidth={1.6} />
        ) : null}
      </span>
      <span className="text-center text-[12.5px] leading-tight font-bold md:text-[12.5px]" style={{ color: 'var(--text)' }}>
        {s.label}
      </span>
    </Link>
  );
}
```

---

## ۱۰) چک‌لیست راه‌اندازی فرانت

- [ ] `API_BASE_URL` در `.env.local` ست شود
- [ ] `INTERNAL_API_TOKEN` در `.env.local` ست شود (فقط برای POST endpointها)
- [ ] route handlers تحت `app/api/*` ساخته شوند که proxy به Laravel هستند
- [ ] مرورگر هرگز مستقیم به `panel.tamironline.com` نزند
- [ ] برای endpointهای کش‌دار، از `revalidate` در fetch استفاده شود
- [ ] خطاهای 422 به‌صورت فیلد-به-فیلد روی فرم نمایش داده شوند
- [ ] خطای 429 پیام «درخواست‌های مکرر» نشان دهد و دکمه disable شود تا `Retry-After` ثانیه

---

## ۱۱) دسته‌بندی FAQ/Testimonial و Placeholderها

دو قابلیت پیشرفته که در ادمین قابل استفاده هستند و در پاسخ API منعکس می‌شوند.

### ۱۱.۱ دسته‌بندی (Taxonomies)

ادمین می‌تواند برای FAQ و Testimonial **دسته‌بندی** تعریف کند (مثلاً `پشتیبانی`، `گارانتی`، `هزینه`). هر آیتم می‌تواند به چند دسته متعلق باشد.

**در ادمین:**
- مسیر: `/admin/site/taxonomies/faq` و `/admin/site/taxonomies/testimonial`
- در فرم FAQ و Testimonial، چک‌باکس دسته‌ها دیده می‌شود
- در فرم محتوای صفحه (`/admin/site/page-content/{slug}`)، در سکشن `faq` ادمین می‌تواند **چند دسته انتخاب کند**

**در API — `category_ids` در سکشن faq:**

```json
"faq": {
  "title": "سوالات متداول",
  "subtitle": "...",
  "category_ids": [3, 1],
  "category_ids_items": [
    {
      "id": 3,
      "slug": "support",
      "label": "پشتیبانی",
      "items": [
        { "id": "01HX...", "question": "...", "answer": "..." }
      ]
    },
    {
      "id": 1,
      "slug": "warranty",
      "label": "شرایط گارانتی",
      "items": [
        { "id": "01HZ...", "question": "...", "answer": "..." }
      ]
    }
  ],
  "faq_ids": [],
  "faq_ids_items": []
}
```

**فرانت چه کند؟**
- اگر `category_ids_items` طول دارد → آن را به‌صورت **تب** رندر کند (هر تب = یک دسته)
- اگر `faq_ids_items` طول دارد → آن را به‌صورت لیست تخت رندر کند
- اگر هر دو پر هستند → اولویت با `category_ids_items` (تب)؛ `faq_ids_items` می‌تواند یک تب «منتخب» باشد
- اگر هر دو خالی → فال‌بک به محتوای استاتیک فرانت

ترتیب تب‌ها = ترتیب انتخاب ادمین.

### ۱۱.۲ Placeholderها (متن داینامیک بر اساس صفحه)

در متن `question`، `answer` و `topic`، ادمین می‌تواند از placeholder استفاده کند که در پاسخ API بر اساس صفحه‌ی فرانت جایگزین می‌شوند.

**Placeholderها:**

| Placeholder | جایگزین می‌شود با |
|---|---|
| `{device}` | نام فارسی دستگاه |
| `{device_slug}` | اسلاگ دستگاه |
| `{page_title}` | عنوان صفحه |

**نکته:** جایگزینی فقط در `GET /v1/devices/{slug}` فعال است (چون context آنجا دستگاه مشخص است). در `/v1/pages/home`، `/v1/pages/about` و... متن دست‌نخورده می‌ماند.

**مثال:**

ادمین FAQ ثبت می‌کند: `تعمیر {device} چقدر زمان می‌برد؟`

پاسخ `/v1/devices/washing-machine`:
```json
"question": "تعمیر لباس‌شویی چقدر زمان می‌برد؟"
```

پاسخ `/v1/devices/refrigerator`:
```json
"question": "تعمیر یخچال چقدر زمان می‌برد؟"
```

یک رکورد، صدها صفحه.


---

## ۱۲) اتصال هدر و فوتر سایت (Layout)

سایت یک **هدر** و **فوتر** ثابت دارد که در همه‌ی صفحات تکرار می‌شود. به‌علاوه یک
**نوار ویژگی‌های ثابت** (Service Features Marquee) که زیر هدر یا داخل صفحات
نمایش داده می‌شود. همه‌ی این سه از یک endpoint می‌آیند:

```
GET /v1/pages/layout
```

### ۱۲.۱ ساختار پاسخ کامل

**Request:**
```
GET /v1/pages/layout
```

**Response 200:**
```json
{
  "slug": "layout",
  "sections": {
    "header": {
      "logo": { "desktop": "https://panel.tamironline.com/storage/site/layout/logo.png", "mobile": "https://panel.tamironline.com/storage/site/layout/logo-mobile.png" },
      "logo_alt": "تعمیرآنلاین",
      "cta_label": "ثبت سفارش",
      "cta_url": "/order",
      "phone_label": "پشتیبانی",
      "phone_number": "۰۲۱-۴۵۳۹۶",
      "nav_items": [
        { "label": "صفحه اصلی", "href": "/" },
        { "label": "درباره ما",   "href": "/about" },
        { "label": "تماس با ما",  "href": "/contact" },
        { "label": "بلاگ",        "href": "/blog" }
      ],
      "services_dropdown": {
        "trigger_label": "خدمات",
        "title": "خدمات تعمیر در محل",
        "subtitle": "تعمیر لوازم خانگی با گارانتی ۶ ماهه — انتخاب دستگاه:",
        "view_all_label": "مشاهده همه دستگاه‌ها",
        "view_all_url": "/devices",
        "device_ids": [1, 2, 3, 4, 5, 6, 7],
        "device_ids_items": [
          { "id": 1, "label": "تعمیر لباس‌شویی", "slug": "washing-machine", "href": "/devices/washing-machine", "icon": "washing-machine", "thumbnail": null, "tone": "tone-blue" }
        ]
      }
    },
    "footer": {
      "logo": { "desktop": "https://...", "mobile": "https://..." },
      "description": "تعمیرآنلاین، خدمات تعمیر لوازم خانگی در محل با بیش از ۸ سال سابقه.",
      "groups": [
        { "title": "خدمات", "links": "تعمیر لباس‌شویی|/devices/washing-machine, تعمیر ظرفشویی|/devices/dishwasher" },
        { "title": "شرکت",  "links": "درباره ما|/about, تماس با ما|/contact, بلاگ|/blog" }
      ],
      "contact_info": {
        "title": "اطلاعات تماس",
        "address": "تهران، خیابان مطهری، نرسیده به خیابان ترکمنستان، پلاک ۲۰",
        "phone": "02145396",
        "phone_display": "۰۲۱-۴۵۳۹۶",
        "email": "support@tamironline.com"
      },
      "app_download": {
        "title": "اپلیکیشن تعمیرآنلاین",
        "subtitle": "سفارش سریع و پیگیری از موبایل — اندروید و iOS.",
        "image": { "desktop": null, "mobile": null },
        "stores": [
          { "name": "Google Play", "icon": "google-play", "url": "https://play.google.com/store/apps/details?id=com.tamironline", "image": null },
          { "name": "کافه بازار",  "icon": "bazaar",      "url": "https://cafebazaar.ir/app/com.tamironline", "image": null }
        ]
      },
      "social": [
        { "platform": "instagram", "icon": "instagram", "url": "https://instagram.com/tamironlinecom" },
        { "platform": "youtube",   "icon": "youtube",   "url": "https://youtube.com/@tamironlinecom" },
        { "platform": "aparat",    "icon": "video",     "url": "https://aparat.com/tamironline" }
      ],
      "copyright_text": "تمام حقوق مادی و معنوی این وب‌سایت متعلق به تعمیرآنلاین می‌باشد.",
      "enamad_code": "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/...'></a>"
    },
    "service_features": {
      "aria_label": "ویژگی‌های ما",
      "speed": 8,
      "items": [
        { "icon_key": "shield",      "label": "گارانتی ۶ ماهه کتبی", "bg": "#ecfdf5", "fg": "#047857", "border": "#a7f3d0" },
        { "icon_key": "clock",       "label": "اعزام در ۳ ساعت",     "bg": "#eff6ff", "fg": "#1d4ed8", "border": "#bfdbfe" },
        { "icon_key": "user-check",  "label": "تکنسین مجرب",          "bg": "#f5f3ff", "fg": "#6d28d9", "border": "#ddd6fe" },
        { "icon_key": "wrench",      "label": "قطعات اصلی",           "bg": "#fffbeb", "fg": "#a16207", "border": "#fde68a" },
        { "icon_key": "map-pin",     "label": "تعمیر در محل",         "bg": "#fff1f2", "fg": "#be123c", "border": "#fecdd3" },
        { "icon_key": "credit-card", "label": "قیمت شفاف",            "bg": "#ecfeff", "fg": "#0e7490", "border": "#a5f3fc" },
        { "icon_key": "thumbs-up",   "label": "رضایت ۹۸٪ مشتریان",    "bg": "#ffedd5", "fg": "#c2410c", "border": "#fed7aa" },
        { "icon_key": "sparkles",    "label": "خدمات تخصصی",          "bg": "#fdf4ff", "fg": "#a21caf", "border": "#f5d0fe" },
        { "icon_key": "award",       "label": "تجربه ۸+ سال",         "bg": "#f0fdfa", "fg": "#0f766e", "border": "#99f6e4" }
      ]
    }
  }
}
```

### ۱۲.۲ فیلدها و رفتار خاص

#### Header

| فیلد | نوع | توضیح |
|---|---|---|
| `logo.desktop` / `logo.mobile` | string \| null | URL کامل (resolve شده توسط سرور). اگر mobile خالی است، desktop را در همه‌ی viewportها استفاده کنید. |
| `logo_alt` | string \| null | متن جایگزین تصویر |
| `cta_label` | string \| null | متن دکمه CTA (مثلاً «ثبت سفارش») |
| `cta_url` | string \| null | مسیر — می‌تواند `/order` یا URL کامل باشد |
| `phone_label` | string \| null | متن بالای شماره تلفن (مثلاً «پشتیبانی») |
| `phone_number` | string \| null | شماره تلفن — می‌توانید با `tel:` به‌صورت لینک تماس کنید |
| `nav_items[].label` | string | متن لینک منو |
| `nav_items[].href` | string | مسیر داخلی Next.js یا URL کامل |

#### Footer

| فیلد | نوع | توضیح |
|---|---|---|
| `logo.desktop` / `logo.mobile` | string \| null | لوگوی فوتر (در صورت داشتن طرح متفاوت) |
| `description` | string \| null | توضیح کوتاه شرکت |
| `groups[].title` | string | عنوان ستون |
| `groups[].links` | string | لیست لینک‌ها به‌صورت `label|href` جدا با کاما (parse توسط فرانت — مثال در §۱۲.۳) |
| `social[].platform` | string | شناسه (instagram, telegram, ...) |
| `social[].icon` | string \| null | کلید آیکن — برای مپ به Lucide |
| `social[].url` | string | URL کامل (validation: strict) |
| `copyright_text` | string \| null | متن کپی‌رایت پایین |
| `enamad_code` | string \| null | HTML خام نشان اعتماد الکترونیکی — با `dangerouslySetInnerHTML` رندر کنید |

#### Service Features (نوار ویژگی‌ها)

| فیلد | نوع | توضیح |
|---|---|---|
| `aria_label` | string \| null | متن aria-label برای دسترس‌پذیری |
| `speed` | int \| null | سرعت اسکرول (پیش‌فرض 8) |
| `items[].icon_key` | string | کلید آیکن Lucide به‌صورت kebab-case (`shield`, `clock`, `user-check`, ...) |
| `items[].label` | string | متن نمایش |
| `items[].bg` | string \| null | رنگ پس‌زمینه (hex) |
| `items[].fg` | string \| null | رنگ متن (hex) |
| `items[].border` | string \| null | رنگ حاشیه (hex) |

#### SEO Footer (متن سئوی پایین صفحه)

بلوک متن طولانی سئو که در پایین صفحات (معمولاً کنار/زیر فوتر اصلی) با
امکان expand/collapse نمایش داده می‌شود.

| فیلد | نوع | توضیح |
|---|---|---|
| `title` | string \| null | تیتر بلوک (مثلاً «درباره تعمیرآنلاین») |
| `paragraphs[].text` | string | متن هر پاراگراف — تا ۳۰۰۰ کاراکتر |

**نمونه‌ی پاسخ:**
```json
"seo_footer": {
  "title": "درباره تعمیرآنلاین",
  "paragraphs": [
    { "text": "تعمیرآنلاین یکی از معتبرترین مجموعه‌ها ..." },
    { "text": "تمامی خدمات با گارانتی کتبی ۶ ماهه ..." },
    { "text": "علاوه بر خدمات تعمیر، تعمیرآنلاین خدمات نصب ..." }
  ]
}
```

**رفتار پیشنهادی فرانت:**
- فقط پاراگراف اول را نشان دهید
- دکمه‌ی «ادامه‌ی متن» برای expand کردن
- اگر آرایه‌ی paragraphs خالی یا سکشن منتشر نشده باشد، بلوک را اصلاً نمایش ندهید

```tsx
// frontend/src/components/layout/SeoFooter.tsx
import { useState } from 'react';
import type { LayoutData } from '@/lib/api/layout';

export function SeoFooter({ data }: { data: NonNullable<LayoutData>['seo_footer'] }) {
  const [expanded, setExpanded] = useState(false);
  if (!data?.paragraphs?.length) return null;

  const visible = expanded ? data.paragraphs : data.paragraphs.slice(0, 1);

  return (
    <section aria-label={data.title ?? 'درباره'} className="seo-footer">
      {data.title && <h2>{data.title}</h2>}
      <div>
        {visible.map((p, i) => <p key={i}>{p.text}</p>)}
      </div>
      {data.paragraphs.length > 1 && (
        <button onClick={() => setExpanded(v => !v)}>
          {expanded ? 'بستن' : 'ادامه‌ی متن'}
        </button>
      )}
    </section>
  );
}
```

**Cache-Control:** `public, max-age=300, s-maxage=300`

#### Header › services_dropdown (مگامنوی خدمات)

زیرگروه nested داخل `header`. در فرانت معمولاً به‌صورت hover-menu نمایش داده می‌شود.

| فیلد | نوع | توضیح |
|---|---|---|
| `trigger_label` | string \| null | متن آیتم منو در هدر (مثل «خدمات») |
| `title` | string \| null | تیتر داخل dropdown |
| `subtitle` | string \| null | زیرتیتر |
| `view_all_label` | string \| null | متن لینک «همه دستگاه‌ها» |
| `view_all_url` | string \| null | لینک |
| `device_ids` | int[] | IDهای دستگاه‌ها (خام) |
| `device_ids_items` | array | **آرایه‌ی hydrate شده** — هر آیتم: `{id, label, slug, href, icon, thumbnail, tone}` |

#### Footer › contact_info (اطلاعات تماس داخل فوتر)

| فیلد | نوع | توضیح |
|---|---|---|
| `title` | string \| null | تیتر بلوک (مثل «اطلاعات تماس») |
| `address` | string \| null | آدرس فیزیکی |
| `phone` | string \| null | شماره برای `tel:` (مثلاً `02145396`) |
| `phone_display` | string \| null | شماره نمایشی برای کاربر (مثلاً `۰۲۱-۴۵۳۹۶`) |
| `email` | string \| null | ایمیل تماس |

#### Footer › app_download (دانلود اپ)

| فیلد | نوع | توضیح |
|---|---|---|
| `title` | string \| null | تیتر بلوک |
| `subtitle` | string \| null | زیرتیتر |
| `image.desktop`/`image.mobile` | string \| null | تصویر تبلیغاتی |
| `stores[].name` | string | نام فروشگاه (مثل `Google Play`) |
| `stores[].icon` | string \| null | کلید آیکن |
| `stores[].url` | string | لینک دانلود |
| `stores[].image` | string \| null | URL تصویر badge (در صورت داشتن) |

#### Mobile CTA (نوار چسبیده‌ی پایین موبایل)

نمایش فقط در viewport موبایل (`md:hidden`).

| فیلد | نوع | توضیح |
|---|---|---|
| `is_active` | bool | روشن/خاموش |
| `primary.label` | string \| null | متن دکمه اول |
| `primary.icon` | string \| null | کلید آیکن |
| `primary.type` | `tel` \| `link` \| `mailto` | نوع دکمه — تعیین می‌کند چطور وصل شود |
| `primary.value` | string \| null | مقدار: شماره تلفن (برای `tel:`) یا مسیر (برای `link`) |
| `secondary.*` | همان | دکمه دوم |

**نمونه پاسخ کامل mobile_cta:**
```json
"mobile_cta": {
  "is_active": true,
  "primary":   { "label": "تماس",     "icon": "phone",  "type": "tel",  "value": "02145396" },
  "secondary": { "label": "ثبت سفارش", "icon": "wrench", "type": "link", "value": "/order" }
}
```

**نمونه‌ی رندر در فرانت:**
```tsx
function MobileCta({ data }: { data: LayoutData['mobile_cta'] }) {
  if (!data?.is_active) return null;
  const renderButton = (btn: { label: string|null; icon: string|null; type: string; value: string|null }) => {
    if (!btn?.value || !btn?.label) return null;
    const href = btn.type === 'tel'    ? `tel:${btn.value.replace(/[^\d+]/g, '')}`
              : btn.type === 'mailto' ? `mailto:${btn.value}`
              : btn.value;
    const Icon = btn.icon ? iconMap[btn.icon] : null;
    return <a href={href} className="flex-1 flex items-center justify-center gap-2 py-3 font-bold">
      {Icon && <Icon className="h-5 w-5" />}
      {btn.label}
    </a>;
  };
  return (
    <div className="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t flex">
      {renderButton(data.primary)}
      <div className="w-px bg-gray-200" />
      {renderButton(data.secondary)}
    </div>
  );
}
```

### ۱۲.۳ پیاده‌سازی پیشنهادی در Next.js — `app/layout.tsx`

Layout فقط **یک‌بار** در root layout fetch می‌شود و بین تمام صفحات shared است:

```typescript
// frontend/src/lib/api/layout.ts
export type LayoutData = {
  header: {
    logo: { desktop: string | null; mobile: string | null };
    logo_alt: string | null;
    cta_label: string | null;
    cta_url: string | null;
    phone_label: string | null;
    phone_number: string | null;
    nav_items: { label: string; href: string }[];
    services_dropdown: {
      trigger_label: string | null;
      title: string | null;
      subtitle: string | null;
      view_all_label: string | null;
      view_all_url: string | null;
      device_ids: number[];
      device_ids_items: {
        id: number; label: string; slug: string; href: string;
        icon: string | null; thumbnail: string | null; tone: string | null;
      }[];
    } | null;
  } | null;
  footer: {
    logo: { desktop: string | null; mobile: string | null };
    description: string | null;
    groups: { title: string; links: string }[];
    contact_info: {
      title: string | null;
      address: string | null;
      phone: string | null;
      phone_display: string | null;
      email: string | null;
    } | null;
    app_download: {
      title: string | null;
      subtitle: string | null;
      image: { desktop: string | null; mobile: string | null };
      stores: {
        name: string; icon: string | null; url: string; image: string | null;
      }[];
    } | null;
    social: { platform: string; icon: string | null; url: string }[];
    copyright_text: string | null;
    enamad_code: string | null;
  } | null;
  service_features: {
    aria_label: string | null;
    speed: number | null;
    items: {
      icon_key: string;
      label: string;
      bg: string | null;
      fg: string | null;
      border: string | null;
    }[];
  } | null;
  seo_footer: {
    title: string | null;
    expand_label: string | null;
    collapse_label: string | null;
    paragraphs: { text: string }[];
  } | null;
  mobile_cta: {
    is_active: boolean;
    primary:   { label: string | null; icon: string | null; type: 'tel'|'link'|'mailto'|null; value: string | null } | null;
    secondary: { label: string | null; icon: string | null; type: 'tel'|'link'|'mailto'|null; value: string | null } | null;
  } | null;
};

export async function getLayout(): Promise<LayoutData> {
  const res = await fetch(`${process.env.API_BASE_URL}/v1/pages/layout`, {
    next: { revalidate: 300, tags: ['layout'] },
  });
  const empty = { header: null, footer: null, service_features: null, seo_footer: null, mobile_cta: null };
  if (!res.ok) return empty;
  const json = await res.json();
  return { ...empty, ...(json.sections ?? {}) };
}
```

```typescript
// frontend/src/app/layout.tsx
import { getLayout } from '@/lib/api/layout';
import { SiteHeader } from '@/components/layout/Header';
import { SiteFooter } from '@/components/layout/Footer';
import { FeatureMarquee } from '@/components/layout/FeatureMarquee';

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const { header, footer, service_features, seo_footer, mobile_cta } = await getLayout();

  return (
    <html lang="fa" dir="rtl">
      <body>
        <SiteHeader data={header} />
        {service_features && <FeatureMarquee data={service_features} />}
        {children}
        {seo_footer && <SeoFooter data={seo_footer} />}
        <SiteFooter data={footer} />
        {mobile_cta && <MobileCta data={mobile_cta} />}
      </body>
    </html>
  );
}
```

### ۱۲.۴ نمونه‌ی کامپوننت Header

```tsx
// frontend/src/components/layout/Header.tsx
import Link from 'next/link';
import Image from 'next/image';
import type { LayoutData } from '@/lib/api/layout';

export function SiteHeader({ data }: { data: LayoutData['header'] }) {
  if (!data) return null;
  const logo = data.logo?.desktop || null;

  return (
    <header className="sticky top-0 z-50 bg-white border-b">
      <div className="container-x flex items-center justify-between py-3">
        {logo && (
          <Link href="/">
            <Image src={logo} alt={data.logo_alt ?? 'لوگو'} width={140} height={40} priority />
          </Link>
        )}

        <nav className="hidden md:flex gap-6">
          {data.nav_items?.map((item) => (
            <Link key={item.href} href={item.href} className="text-sm font-medium hover:text-blue-600">
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-3">
          {data.phone_number && (
            <a href={`tel:${data.phone_number.replace(/[^\d+]/g, '')}`} className="text-sm">
              <span className="text-gray-500">{data.phone_label}</span>{' '}
              <span className="font-bold">{data.phone_number}</span>
            </a>
          )}
          {data.cta_url && data.cta_label && (
            <Link href={data.cta_url} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold">
              {data.cta_label}
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}
```

### ۱۲.۵ نمونه‌ی کامپوننت Footer

پارس لینک‌های گروه (`label|href` جدا با کاما):

```tsx
// frontend/src/components/layout/Footer.tsx
import Link from 'next/link';
import type { LayoutData } from '@/lib/api/layout';

function parseLinks(raw: string | undefined | null): { label: string; href: string }[] {
  if (!raw) return [];
  return raw
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => {
      const [label, href] = s.split('|').map((x) => x.trim());
      return label && href ? { label, href } : null;
    })
    .filter((x): x is { label: string; href: string } => x !== null);
}

export function SiteFooter({ data }: { data: LayoutData['footer'] }) {
  if (!data) return null;

  return (
    <footer className="bg-gray-900 text-white mt-16">
      <div className="container-x py-12">
        {data.description && (
          <p className="text-sm text-gray-300 mb-8 max-w-2xl">{data.description}</p>
        )}

        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
          {data.groups?.map((g, i) => (
            <div key={i}>
              <h3 className="font-bold mb-3">{g.title}</h3>
              <ul className="space-y-2 text-sm text-gray-300">
                {parseLinks(g.links).map((link) => (
                  <li key={link.href}>
                    <Link href={link.href} className="hover:text-white">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {data.social && data.social.length > 0 && (
          <div className="flex gap-4 mb-6">
            {data.social.map((s) => (
              <a key={s.platform} href={s.url} target="_blank" rel="noopener" aria-label={s.platform}>
                <SocialIcon name={s.icon ?? s.platform} />
              </a>
            ))}
          </div>
        )}

        {data.enamad_code && (
          <div
            className="mb-6 inline-block"
            dangerouslySetInnerHTML={{ __html: data.enamad_code }}
          />
        )}

        {data.copyright_text && (
          <div className="text-xs text-gray-500 border-t border-gray-800 pt-4">
            {data.copyright_text}
          </div>
        )}
      </div>
    </footer>
  );
}
```

### ۱۲.۶ نمونه‌ی FeatureMarquee

این کامپوننت در روت layout زیر هدر یا داخل صفحات کاربرد دارد. اگر فرانت
از قبل `FeatureMarquee` تعریف شده، فقط دیتا را مپ کنید:

```tsx
// frontend/src/components/layout/FeatureMarquee.tsx
import type { LayoutData } from '@/lib/api/layout';
import { iconMap } from '@/lib/icons';

export function FeatureMarquee({ data }: { data: LayoutData['service_features'] }) {
  if (!data?.items?.length) return null;

  return (
    <div className="bg-gray-50 border-y" aria-label={data.aria_label ?? 'ویژگی‌ها'}>
      <div
        className="marquee flex gap-3 py-3 overflow-hidden"
        style={{ animationDuration: `${(data.speed ?? 8) * 5}s` }}
      >
        {data.items.map((f, i) => {
          const Icon = iconMap[f.icon_key];
          return (
            <div
              key={i}
              className="shrink-0 flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-bold border"
              style={{
                background: f.bg ?? '#f9fafb',
                color: f.fg ?? '#111827',
                borderColor: f.border ?? '#e5e7eb',
              }}
            >
              {Icon && <Icon className="h-4 w-4" strokeWidth={1.8} />}
              <span>{f.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
```

### ۱۲.۷ Revalidation و کش

Layout نسبتاً ایستا است ولی گاهی ادمین تغییر می‌دهد. توصیه:

```typescript
// نسبت به TTL سرور (s-maxage=300)، فرانت هم 300 ثانیه ISR
export const revalidate = 300;
```

برای revalidation فوری بعد از تغییر در پنل ادمین، فرانت می‌تواند یک webhook
endpoint تعریف کند که Laravel بعد از ذخیره‌ی layout صدا بزند و
`revalidateTag('layout')` اجرا شود. این بخش هنوز پیاده نشده — اگر نیاز شد
خبر بدهید.

### ۱۲.۸ پیکربندی ادمین

ادمین layout را از این مسیر ویرایش می‌کند:

```
/admin/site/page-content/layout
```

سه کارت می‌بیند: **هدر**، **فوتر**، **نوار ویژگی‌ها**. هر کارت یک toggle
«منتشر شود» دارد. اگر toggle خاموش بود، آن سکشن در پاسخ API نمی‌آید
(فرانت با `null` مواجه می‌شود → fallback به استاتیک).

---

## ۱۳) نکات لینک‌ها — `site_url` validator

سرور برای فیلدهای لینک از قاعده‌ی `site_url` استفاده می‌کند که هم مسیر داخلی
و هم URL کامل را قبول می‌کند:

| ورودی | پذیرفته می‌شود؟ |
|---|---|
| `/order` | ✅ مسیر داخلی Next.js |
| `/devices/washing-machine` | ✅ مسیر تو در تو |
| `https://example.com` | ✅ URL کامل |
| `http://example.com` | ✅ URL کامل (http) |
| `mailto:support@tamironline.com` | ✅ پروتکل ایمیل |
| `tel:02145396` | ✅ پروتکل تلفن |
| `//evil.com` | ❌ protocol-relative (امنیتی) |
| `javascript:...` | ❌ ممنوع |
| `order` (بدون اسلش) | ❌ نامعتبر |

**فیلدهایی که از `site_url` استفاده می‌کنند** (مسیر داخلی مجاز):
- `home.hero.cta_url`
- `home.promo.link_url`
- `about.promo.link_url`
- `layout.header.cta_url`
- `layout.header.nav_items[].href`
- `contact.channels.items[].link_url`
- `responsive_image.desktop` / `responsive_image.mobile`

**فیلدهایی که strict URL هستند** (فقط URL کامل):
- `layout.footer.social[].url`
- `contact.social.items[].url`
- `contact.map.neshan_url`
- `testimonials.audio_url`

---

## ۱۴) Endpoints — جریان کامل برای یک سایت کامل

برای رندر کل سایت (Home + About + Contact)، فرانت در حالت ایده‌آل این
درخواست‌ها را می‌زند (همگی به‌جز POST‌ها، با ISR):

```
1.  GET /v1/pages/layout          ← یک‌بار در root layout
2.  GET /v1/pages/home            ← صفحه‌ی اصلی
3.  GET /v1/pages/about           ← درباره ما
4.  GET /v1/pages/contact         ← تماس با ما
5.  GET /v1/devices/{slug}        ← صفحه‌ی هر دستگاه
6.  GET /v1/catalog/devices       ← صفحه‌ی فهرست دستگاه‌ها
7.  GET /v1/catalog/brands?featured=true  ← اگر در Home از endpoint جدا می‌خواهید
8.  GET /v1/testimonials          ← اگر Carousel جداگانه دارید
9.  GET /v1/activity/recent       ← اگر Live Activity جداگانه نیاز است
10. GET /v1/site/about-stats      ← اگر A2 را جداگانه fetch می‌کنید
```

> اکثر اطلاعات از داخل `/v1/pages/{slug}` می‌آیند. endpoints catalog و
> testimonials و activity فقط برای موارد خاص (Carouselهای داینامیک،
> Live updates، صفحه فهرست) لازم می‌شوند.
