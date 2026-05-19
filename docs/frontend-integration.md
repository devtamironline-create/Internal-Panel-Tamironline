# داکیومنت اتصال فرانت — Laravel v1 API

این سند برای تیم فرانت‌اند (Next.js) است. لیست تمام endpoint‌های موجود
نسخه‌ی `/v1/*` به همراه قراردادهای auth، validation، response و کش.

> این سند با کد همگام نگه داشته می‌شود. اگر فیلدی در پاسخ نمی‌بینید،
> سند را به‌روز کنید — حدس نزنید.

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
| 3 | GET | `/v1/activity/recent` | — | 60/min | 60s | P1 | ✅ |
| 4 | GET | `/v1/testimonials` | — | 60/min | 300s | P1 | ✅ |
| 5 | GET | `/v1/catalog/brands` | — | 60/min | 600s | P2 | ✅ |
| 6 | GET | `/v1/site/about-stats` | — | 60/min | 600s | P2 | ✅ |
| 7 | GET | `/v1/health` | — | 120/min | — | — | ✅ |

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
| `slug` | string | فقط حروف انگلیسی — `home` \| `about` \| `contact` |

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
      "services": [
        { "label": "لباسشویی",   "slug": "lebas-shooyi",   "icon": "washing", "href": "/services/washing" },
        { "label": "ماشین ظرفشویی", "slug": "zarf-shooyi", "icon": "dish",    "href": "/services/dishwasher" }
      ]
    },
    "why_us": {
      "title": "چرا تعمیرآنلاین؟",
      "items": [
        { "icon": "check", "title": "تضمین کیفیت", "description": "..." }
      ]
    },
    "steps": { "title": "...", "image_url": "https://...", "alt": "..." },
    "promo": { "title": "...", "link_url": "...", "link_label": "..." },
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
- **فیلدهای reference خودکار hydrate می‌شوند.** برای `faq_ids` آرایه‌ای از IDها در `faq_ids` می‌بینید + آرایه‌ی کامل آیتم‌ها در `faq_ids_items`. فرانت از `<field>_items` استفاده کند.
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
| `hero` | title, subtitle, cta_label, cta_url, services[label, slug, icon, href] |
| `why_us` | title, subtitle, items[icon, title, description] |
| `steps` | title, image_url, alt |
| `promo` | title, subtitle, image_url, link_url, link_label |
| `faq` | title, subtitle, faq_ids[], **faq_ids_items[]** |

##### About (`/v1/pages/about`)
| section_key | فیلدها |
|---|---|
| `hero` | title, subtitle, aparat_id, poster_url, description |
| `values` | title, subtitle, items[icon, title, description] |
| `steps` | title, image_url, alt |
| `timeline` | title, items[year, title, description] |
| `faq` | title, subtitle, faq_ids[], **faq_ids_items[]** |
| `promo` | title, subtitle, image_url, link_url, link_label |

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

### 4.3 `GET /v1/activity/recent`

فعالیت‌های زنده — سکشن H2 صفحه‌ی Home. منبع داده: سفارشات فعال CRM با وضعیت `completed`، `open`، `coordinated`، `transit` در ۴۸ ساعت اخیر.

**Query params:**

| پارامتر | نوع | پیش‌فرض | قاعده |
|---|---|---|---|
| `limit` | int | 10 | 1..50 |

**Request example:**
```
GET /v1/activity/recent?limit=10
```

**Response 200:**
```json
{
  "data": [
    {
      "device_slug": "lebas-shooyi",
      "device_label": "لباسشویی",
      "area": "تهران",
      "status": "completed",
      "minutes_ago": 12
    }
  ]
}
```

**فیلدها:**

| فیلد | نوع | توضیح |
|---|---|---|
| `device_slug` | string | برای لینک به صفحه‌ی دستگاه |
| `device_label` | string | متن نمایش فارسی (نام دستگاه از CRM) |
| `area` | string | فقط نام شهر — **بدون آدرس کامل** |
| `status` | enum | `completed` \| `in_progress` |
| `minutes_ago` | int | تفاوت دقیقه‌ای — سرور محاسبه می‌کند |

**Headers:**
```http
Cache-Control: public, max-age=60, s-maxage=60
```

**نکات امنیتی پیاده‌شده در سرور:**
- نام/شماره/آدرس مشتری در پاسخ نیست
- `area` فقط شهر است
- وضعیت‌های جزئی CRM (مثلاً معلق، رد، کنسل، برگشتی) فیلتر شده‌اند

---

### 4.4 `GET /v1/testimonials`

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

### 4.5 `GET /v1/catalog/brands`

برندهای تحت پوشش — سکشن H6 صفحه‌ی Home. منبع: جدول CRM brands.

**Query params:**

| پارامتر | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `featured` | bool | false | فقط برندهای ویژه |
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

---

### 4.6 `GET /v1/site/about-stats`

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

### 4.7 `GET /v1/health`

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

**نکته:** برای صفحات کامل، فرانت می‌تواند یک fetch به `GET /v1/pages/{slug}`
بزند و تمام سکشن‌های آن صفحه را یکجا بگیرد.

---

## ۹) چک‌لیست راه‌اندازی فرانت

- [ ] `API_BASE_URL` در `.env.local` ست شود
- [ ] `INTERNAL_API_TOKEN` در `.env.local` ست شود (فقط برای POST endpointها)
- [ ] route handlers تحت `app/api/*` ساخته شوند که proxy به Laravel هستند
- [ ] مرورگر هرگز مستقیم به `panel.tamironline.com` نزند
- [ ] برای endpointهای کش‌دار، از `revalidate` در fetch استفاده شود
- [ ] خطاهای 422 به‌صورت فیلد-به-فیلد روی فرم نمایش داده شوند
- [ ] خطای 429 پیام «درخواست‌های مکرر» نشان دهد و دکمه disable شود تا `Retry-After` ثانیه
