# داکیومنت تحویل به تیم فرانت‌اند — اپلیکیشن مشتریان

> **نسخه:** 2.0
> **تاریخ:** 1405/03/18
> **وضعیت:** آماده‌ی توسعه و تست
> **مخاطب:** تیم فرانت‌اند (PWA / React Native / Flutter)
> **منبع راهنما:** docs/MOBILE_API_CONTRACT.md (v1 — بازنشسته شد، این داک جای آن می‌نشیند)

---

## فهرست

1. [خلاصه](#1-خلاصه)
2. [Base URL و معماری](#2-base-url-و-معماری)
3. [💡 حالت تست برای توسعه](#3-حالت-تست-برای-توسعه)
4. [قراردادهای عمومی](#4-قراردادهای-عمومی)
5. [هدرهای امنیتی](#5-هدرهای-امنیتی)
6. [Auth — ثبت‌نام و ورود](#6-auth--ثبت‌نام-و-ورود)
7. [Status و Bootstrap](#7-status-و-bootstrap)
8. [Catalog و Picker ها](#8-catalog-و-picker-ها)
9. [Addresses](#9-addresses)
10. [Orders — قلب اپ](#10-orders--قلب-اپ)
11. [Invoice و پرداخت](#11-invoice-و-پرداخت)
12. [Reviews — نظرسنجی اجباری](#12-reviews--نظرسنجی-اجباری)
13. [Profile و Notifications](#13-profile-و-notifications)
14. [Device Sessions](#14-device-sessions)
15. [نگاشت OrderStatus](#15-نگاشت-orderstatus)
16. [TypeScript Types](#16-typescript-types)
17. [کدهای خطا](#17-کدهای-خطا)
18. [Checklist تست](#18-checklist-تست)

---

## 1) خلاصه

سلام تیم فرانت 👋

این داک قرارداد نهایی و کامل API اپلیکیشن مشتریان است. تمام endpoint های مورد نیاز شما **پیاده‌سازی شده و آماده هستند** — می‌توانید همین الان شروع به ساخت اپ کنید.

**خلاصه‌ی مهم:**
- ۳۵ endpoint زیر `/v1/customer/*` آماده
- Auth زیر `/v1/auth/*` آماده (Sanctum Bearer Token، ۳۰ روز)
- **حالت تست بدون OTP** برای QA — جزئیات در بخش ۳
- Response envelope استاندارد روی همه‌ی `/v1/customer/*`
- پشتیبانی کامل از Idempotency-Key، X-Renewed-Token، X-Device-ID

---

## 2) Base URL و معماری

### URL ها

| محیط | Base URL |
|---|---|
| Production | `https://panel.tamironline.com` |
| Staging | `https://stg-panel.tamironline.com` (در صورت وجود) |
| Local | `http://127.0.0.1:8000` |

**تمام مسیرها زیر `/v1/customer/*` می‌آیند**، به‌جز Auth که زیر `/v1/auth/*` است (چون توکن صادرشده برای کل platform یکسان است).

### معماری ماژول‌ها (پشت‌صحنه — جهت اطلاع)

- `/v1/auth/*` → ماژول Identity (OTP، profile، logout)
- `/v1/customer/*` → ماژول CustomerApp (همه چیز دیگر)
- `/v1/catalog/*` و `/v1/blog/*` → ماژول Site (سایت Next.js — شما درگیر نیستید)

شما فقط با دو namespace اول کار دارید.

---

## 3) 💡 حالت تست برای توسعه

برای اینکه تیم QA و توسعه‌دهنده‌ها بتوانند **بدون نیاز به دریافت پیامک واقعی** flow login را تست کنند، یک حالت تست داریم.

### روشن کردن حالت تست (وظیفه‌ی DevOps/Backend)

در `.env` سرور تست:

```env
CUSTOMER_APP_TEST_MODE=true
CUSTOMER_APP_TEST_OTP=487213                   # کد ۶ رقمی master
CUSTOMER_APP_TEST_MOBILES=09000000000,09000000001  # خالی = همه شماره‌ها
```

پس از تغییر env، یک `php artisan config:clear` کافی است — restart لازم نیست.

### نحوه‌ی استفاده‌ی فرانت

وقتی حالت تست فعال است:

1. **send-otp**: درخواست را به طور عادی بفرستید. `POST /v1/auth/send-otp` با body `{"mobile":"09000000000"}` → پاسخ `200 OK` می‌دهد ولی **هیچ پیامکی ارسال نمی‌شود**.

2. **verify-otp**: درخواست را با کد **`487213`** (همان `CUSTOMER_APP_TEST_OTP`) بفرستید — توکن واقعی برمی‌گردد و کاربر login می‌شود.

```jsonc
POST /v1/auth/verify-otp
{ "mobile": "09000000000", "code": "487213" }
// → 200, token + customer
```

### چطور بفهمم حالت تست فعال است؟

در پاسخ `GET /v1/customer/status`:

```jsonc
{
  "success": true,
  "data": {
    "test_mode_active": true,   // ← این فیلد را چک کنید
    "app_disabled": false,
    "min_version": "1.0.0",
    "...": "..."
  }
}
```

**توصیه‌ی UX:** اگر `test_mode_active=true` دیدید، یک bar زرد بالای اپ نمایش دهید: "🧪 حالت تست — OTP واقعی نیست".

### ⚠️ تذکر امنیتی

- در production این حالت **حتماً خاموش** خواهد بود (`CUSTOMER_APP_TEST_MODE=false`)
- اگر فرانت شما داخلش جایی hard-code شده که "اگر طولانی منتظر OTP بودی، 487213 را امتحان کن" — این را به منوی developer mode محدود کنید
- کد `487213` فقط در سرور تست کار می‌کند

---

## 4) قراردادهای عمومی

### 4.1 ساختار پاسخ موفق

تمام مسیرهای `/v1/customer/*` در envelope استاندارد wrap می‌شوند:

```jsonc
{
  "success": true,
  "data": { /* payload */ },
  "message": "متن فارسی (اختیاری)",
  "meta": { /* اختیاری — برای pagination یا meta data */ }
}
```

> **استثنا:** مسیرهای `/v1/auth/*` envelope ندارند — مستقیماً `{ ok, token, customer, ... }` برمی‌گرداند. این به دلیل سازگاری با web client است. در فرانت یک adapter بنویسید که این تفاوت را normalize کند.

### 4.2 ساختار پاسخ خطا

```jsonc
{
  "success": false,
  "message": "پیام فارسی کاربرپسند",
  "code": "machine_readable_code",
  "data": {
    "error_code": "machine_readable_code",
    "errors": {
      "field_name": ["خطای فیلد ۱", "خطای فیلد ۲"]
    }
  }
}
```

### 4.3 Pagination

```jsonc
{
  "success": true,
  "data": [ /* array */ ],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 47,
    "last_page": 5
  }
}
```

### 4.4 فرمت‌های قطعی

| موضوع | تصمیم |
|---|---|
| تاریخ/زمان | ISO 8601 UTC — مثلاً `2026-06-07T14:30:00Z`. تبدیل به شمسی در سمت فرانت |
| موبایل | همیشه `09xxxxxxxxx` (۱۱ رقم با صفر) |
| پول | **تومان (IRT)** — integer |
| id | همیشه number (نه string) |
| timezone | همه UTC، فرانت به Asia/Tehran تبدیل می‌کند |

---

## 5) هدرهای امنیتی

### 5.1 هدرهای request

| هدر | الزامی؟ | کاربرد |
|---|---|---|
| `Authorization: Bearer <token>` | ✅ روی auth-required | Sanctum token از verify-otp |
| `Content-Type: application/json` | ✅ روی POST/PUT/PATCH | Laravel نیاز دارد |
| `Accept: application/json` | ✅ | تا exception ها JSON برگردند، نه HTML |
| `Idempotency-Key: <uuid>` | روی نوشتارها قویاً توصیه‌شده | جلوگیری از سفارش/درخواست تکراری در شبکه‌ی ناپایدار |
| `X-Device-ID: <uuid>` | اختیاری ولی توصیه‌شده | شناسه‌ی پایدار دستگاه برای device management |
| `X-App-Version: <semver>` | توصیه‌شده | برای enforcement min_version در آینده |
| `If-None-Match: <etag>` | روی `/bootstrap` | تا 304 بگیرید و body دانلود نکنید |

### 5.2 هدرهای response

| هدر | معنی | اقدام فرانت |
|---|---|---|
| `X-Renewed-Token: <token>` | توکن جدید — توکن قبلی به نیمه‌ی عمر رسیده | در storage جایگزین کنید |
| `Idempotent-Replay: true` | این پاسخ از cache آمده، نه اجرای جدید | UX را تنظیم کنید (مثلاً «سفارش از قبل ثبت شده») |
| `ETag: "<hash>"` | روی `/bootstrap` | ذخیره کنید برای If-None-Match بعدی |

### 5.3 نمونه‌ی Idempotency-Key

```ts
const idempotencyKey = crypto.randomUUID(); // یا nanoid

fetch(`${API}/v1/customer/orders`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Idempotency-Key': idempotencyKey,
    'X-Device-ID': deviceId,
  },
  body: JSON.stringify(payload),
});
```

اگر همین درخواست با همان key دوباره ارسال شود (مثلاً به دلیل قطع شبکه و retry فرانت)، **همان پاسخ قبلی** برمی‌گردد و سفارش دومی ایجاد نمی‌شود. TTL: ۲۴ ساعت.

### 5.4 نمونه‌ی استفاده از X-Renewed-Token

```ts
const response = await fetch(url, { headers });
const renewed = response.headers.get('X-Renewed-Token');
if (renewed) {
  await storage.setItem('auth_token', renewed);
}
```

---

## 6) Auth — ثبت‌نام و ورود

### 6.1 جریان کلی

```
کاربر شماره موبایل وارد می‌کند
  └─→ POST /v1/auth/send-otp { mobile }
کد ۶ رقمی از پیامک دریافت می‌کند (یا در test mode = 487213)
  └─→ POST /v1/auth/verify-otp { mobile, code }
        ← { token, customer, is_new, needs_profile }
        💾 توکن را در storage ذخیره کنید
اگر needs_profile === true:
  └─→ POST /v1/auth/complete-profile { first_name, last_name? }
        ← { customer }
```

### 6.2 POST `/v1/auth/send-otp`

**Request:**
```jsonc
{ "mobile": "09918911126" }
```
فرمت‌های قبول‌شده: `09xxxxxxxxx`، `+989xxxxxxxxx`، `00989xxxxxxxxx`، `989xxxxxxxxx`، با ارقام فارسی/عربی هم کار می‌کند.

**Response 200:**
```jsonc
{
  "ok": true,
  "message": "کد تأیید ارسال شد.",
  "expires_in": 120,        // کد چند ثانیه معتبر است
  "can_resend_in": 60       // بعد از چند ثانیه می‌توان دوباره فرستاد
}
```

در حالت تست، پاسخ شامل `"test_mode": true` نیز هست.

**Response 422 (نامعتبر یا rate-limit):**
```jsonc
{
  "message": "شماره موبایل نامعتبر است.",
  "errors": { "mobile": ["شماره موبایل نامعتبر است."] }
}
```

**Rate limits:** ۱۰ در دقیقه per IP + ۵ در ساعت per phone.

### 6.3 POST `/v1/auth/verify-otp`

**Headers اضافی توصیه‌شده:**
- `X-Device-ID: <uuid-stable-per-install>` — شناسه‌ی پایدار دستگاه برای device management

**Request:**
```jsonc
{ "mobile": "09918911126", "code": "123456" }
```

**Response 200:**
```jsonc
{
  "ok": true,
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123...",
  "token_type": "Bearer",
  "customer": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": null,           // null = پروفایل تکمیل نشده
    "last_name": null,
    "full_name": null,
    "email": null,
    "avatar_url": null,
    "is_profile_complete": false,
    "mobile_verified_at": "2026-06-07T14:00:00Z",
    "subscription": 22345,        // شماره اشتراک (wp_id + 10000)
    "created_at": "2026-06-07T14:00:00Z"
  },
  "is_new": true,
  "needs_profile": true           // اگر true، فرم نام بپرسید
}
```

**Response 422 (کد اشتباه):**
```jsonc
{
  "message": "کد تایید اشتباه است (4 تلاش باقی‌مانده)",
  "errors": { "code": ["..."] }
}
```

### 6.4 POST `/v1/auth/complete-profile`

پس از verify-otp اگر `needs_profile=true` بود.

```jsonc
// Request — Headers: Authorization: Bearer <token>
{ "first_name": "علی", "last_name": "محمدی" }

// Response 200
{
  "ok": true,
  "customer": { /* full customer object با is_profile_complete: true */ }
}
```

### 6.5 GET `/v1/auth/me`
دریافت اطلاعات کاربر فعلی. ساده، با Bearer token.

```jsonc
{ "ok": true, "customer": { /* ... */ } }
```

### 6.6 POST `/v1/auth/logout`
فقط توکن فعلی revoke می‌شود. → `{ ok: true, message: "..." }`

### 6.7 POST `/v1/auth/logout-all`
همه‌ی توکن‌های این مشتری revoke می‌شوند → کاربر از همه دستگاه‌ها خارج می‌شود.

---

## 7) Status و Bootstrap

### 7.1 GET `/v1/customer/status` (public — هر چند وقت یک‌بار poll کنید)

```jsonc
{
  "success": true,
  "data": {
    "app_disabled": false,
    "min_version": "1.0.0",
    "latest_version": "1.0.0",
    "force_reauth_at": 0,
    "maintenance": { "active": false, "message": null },
    "test_mode_active": false,
    "server_time": "2026-06-07T14:30:00Z",
    "db": "ok"
  }
}
```

**کاربرد:**
- `app_disabled=true` → overlay سراسری «سرویس در دسترس نیست»
- `maintenance.active=true` → modal با `maintenance.message`
- `compare(client_version, min_version) < 0` → overlay اجباری به‌روزرسانی
- `force_reauth_at > token_issued_at` → logout اجباری
- `test_mode_active=true` → bar زرد «حالت تست»

اگر `app_disabled=true`، endpoint با HTTP 503 پاسخ می‌دهد.

### 7.2 GET `/v1/customer/bootstrap` (public + ETag)

تجمیعی برای splash — همه‌ی lookupهای لازم در یک پاسخ.

```jsonc
{
  "success": true,
  "data": {
    "status": { /* همان شکل status */ },
    "time_slots": [
      { "value": "09-12", "label": "۹ تا ۱۲ صبح" },
      { "value": "12-15", "label": "۱۲ تا ۱۵" },
      { "value": "15-18", "label": "۱۵ تا ۱۸" },
      { "value": "18-21", "label": "۱۸ تا ۲۱" }
    ],
    "cancel_reasons": [
      { "id": 1, "label": "پشیمان شدم" },
      { "id": 3, "label": "قیمت تخمینی برای من زیاد بود" },
      { "id": 99, "label": "دلیل دیگر" }
    ],
    "service_types": [
      { "id": 1, "slug": "repair",  "name": "تعمیر در محل", "icon": "wrench" },
      { "id": 2, "slug": "service", "name": "سرویس و نگهداری", "icon": "settings-2" },
      { "id": 3, "slug": "install", "name": "نصب", "icon": "plug" }
    ],
    "objections_count": 47,
    "upcoming_holidays": [
      { "date": "2026-06-15", "label": "نوروز", "type": "national" }
    ],
    "version": "a3f5b9c1"   // تغییر = invalidate cache local
  }
}
```

**Headers:** `ETag: "abc123"`. اگر `If-None-Match: "abc123"` بفرستید و چیزی عوض نشده → 304 بدون body.

**استفاده:** یک بار در splash، بعد cache (با ETag) — هر چند ساعت یک‌بار re-poll.

---

## 8) Catalog و Picker ها

### 8.1 Locations

```jsonc
// GET /v1/customer/locations/states
{
  "success": true,
  "data": [
    { "id": 1, "name": "تهران", "slug": "tehran" },
    { "id": 2, "name": "البرز", "slug": "alborz" }
  ]
}

// GET /v1/customer/locations/cities?state_id=1
{
  "success": true,
  "data": [
    { "id": 10, "state_id": 1, "name": "تهران", "slug": "tehran" },
    { "id": 11, "state_id": 1, "name": "منطقه ۱ تهران", "slug": "tehran-district-1" },
    { "id": 12, "state_id": 1, "name": "منطقه ۲ تهران", "slug": "tehran-district-2" }
    // ... ۲۲ منطقه‌ی شهرداری تهران + شهرهای حومه
  ]
}
```

Cache: 1h.

### 8.2 Service Types

```jsonc
// GET /v1/customer/services/types
{
  "success": true,
  "data": [
    { "id": 1, "slug": "repair",  "name": "تعمیر در محل", "icon": "wrench", "description": null },
    { "id": 2, "slug": "service", "name": "سرویس و نگهداری", "icon": "settings-2", "description": null },
    { "id": 3, "slug": "install", "name": "نصب", "icon": "plug", "description": null }
  ]
}
```

**slug این مقادیر را در POST /orders به‌عنوان `order_type` بفرستید.**

### 8.3 Objections (ایرادات)

```jsonc
// GET /v1/customer/services/objections?device_id=5
{
  "success": true,
  "data": [
    { "id": 12, "slug": "noise", "name": "صدای غیرعادی", "description": null, "icon": null },
    { "id": 14, "slug": "leak", "name": "نشتی آب", "description": null, "icon": "droplets" }
  ],
  "meta": { "device_id": 5, "total": 2 }
}
```

بدون `device_id` همه‌ی ایرادات فعال برمی‌گردند.

### 8.4 Holidays

```jsonc
// GET /v1/customer/holidays?from=2026-06-01&to=2026-08-31
{
  "success": true,
  "data": [
    { "id": 1, "date": "2026-06-15", "label": "نوروز", "type": "national" },
    { "id": 2, "date": "2026-07-21", "label": "عید فطر", "type": "religious" }
  ],
  "meta": { "from": "2026-06-01", "to": "2026-08-31", "total": 2 }
}
```

`type`: `official | religious | national | custom`.

پیش‌فرض: امروز تا ۹۰ روز بعد. Cache: 1h.

---

## 9) Addresses

### 9.1 GET `/v1/customer/addresses`

```jsonc
{
  "success": true,
  "data": [
    {
      "id": 7,
      "label": "خانه",
      "full_address": "تهران، خیابان آزادی، کوچه ۵، پلاک ۱۰",
      "state_id": 1,
      "state_name": "تهران",
      "city_id": 11,
      "city_name": "منطقه ۱ تهران",
      "postal_code": "1234567890",
      "phone": "02144556677",
      "is_default": true,
      "created_at": "2026-06-07T14:00:00Z"
    }
  ]
}
```

### 9.2 POST `/v1/customer/addresses`

```jsonc
// Headers: Authorization, Idempotency-Key
{
  "label": "خانه",
  "province_id": 1,
  "city_id": 11,
  "full_address": "تهران، آزادی، پلاک ۱۰",
  "postal_code": "1234567890",
  "phone": "02144556677",
  "is_default": true
}
// → 201, data: { /* address */ }
```

### 9.3 GET/PUT/DELETE `/v1/customer/addresses/{id}`

PUT همان شکل POST. DELETE → اگر آدرس پیش‌فرض حذف شد، اولین آدرس باقی‌مانده خودکار پیش‌فرض می‌شود.

---

## 10) Orders — قلب اپ

### 10.1 GET `/v1/customer/orders` (لیست)

**Query:** `?page=1&per_page=10&status=pending,scheduled`

```jsonc
{
  "success": true,
  "data": [
    {
      "id": 1234,
      "tracking_code": "TM-1234",
      "status": "scheduled",          // mobile string
      "status_int": 2,
      "status_label": "هماهنگ شده",
      "is_terminal": false,
      "order_type": "repair",
      "device": { "id": 5, "name": "لباس‌شویی", "slug": "washing-machine", "icon": "..." },
      "brand": { "id": 10, "name": "سامسونگ", "slug": "samsung" },
      "scheduled_date": "2026-06-10",
      "scheduled_slot": "09-12",
      "total_amount": 250000,          // تومان (یا null اگر فاکتور نیست)
      "created_at": "2026-06-07T14:00:00Z",
      "updated_at": "2026-06-07T15:30:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 10, "total": 47, "last_page": 5 }
}
```

### 10.2 POST `/v1/customer/orders` (ثبت)

**Headers:** `Authorization`, `Idempotency-Key`, `Content-Type: application/json`

```jsonc
{
  "order_type": "repair",                   // slug از /services/types
  "device_id": 5,
  "brand_id": 10,                           // اختیاری
  "objection_ids": [12, 14],                // اختیاری، حداکثر ۱۰
  "problem_description": "صدای غیرعادی می‌دهد",
  "problem_title": "صدای غیرعادی",          // اختیاری
  "scheduled_date": "2026-06-10",           // ISO date (YYYY-MM-DD) میلادی
  "scheduled_slot": "09-12",                // یکی از 4 مقدار
  "address_id": 7,                          // باید متعلق به همین کاربر باشد
  "introduction": "از همکار معرفی شدم"      // اختیاری
}
```

**Response 201:**
```jsonc
{
  "success": true,
  "message": "سفارش با موفقیت ثبت شد.",
  "data": { /* همان شکل GET /orders/{id} */ }
}
```

**Response 409 `pending_review_required`:**
```jsonc
{
  "success": false,
  "message": "ابتدا نظرسنجی سفارش قبلی را ثبت کنید.",
  "code": "pending_review_required",
  "data": {
    "error_code": "pending_review_required",
    "pending_order_id": 1200,
    "pending_tracking_code": "TM-1200"
  }
}
```
→ فرانت modal اجباری نظرسنجی نمایش دهد.

**Rate limit:** ۳۰ در دقیقه per user.

### 10.3 GET `/v1/customer/orders/{id}`

```jsonc
{
  "success": true,
  "data": {
    "id": 1234,
    "tracking_code": "TM-1234",
    "status": "in_progress",
    "status_int": 6,
    "status_label": "ایاب و ذهاب",
    "is_terminal": false,
    "order_type": "repair",
    "device": { /* ... */ },
    "brand": { /* ... */ },
    "problem_title": "صدای غیرعادی",
    "problem_description": "...",
    "objections": [
      { "id": 12, "name": "صدای غیرعادی", "slug": "noise" }
    ],
    "scheduled_date": "2026-06-10",
    "scheduled_slot": "09-12",
    "scheduled_at": "2026-06-10T09:00:00Z",
    "address": {
      "id": 7,
      "label": "خانه",
      "full_address": "...",
      "state_name": "تهران",
      "city_name": "منطقه ۱ تهران",
      "postal_code": "1234567890",
      "phone": "02144556677"
    },
    "technician": {
      "id": 5,
      "name": "حسن احمدی",
      "mobile": "09121234567",
      "rating": 4.7
    },
    "pricing": {
      "estimated": null,
      "final": null,
      "deposit": null,
      "total": 250000,
      "currency": "IRT"
    },
    "cancel": null,                  // اگر cancelled است: { reason_id, reason }
    "review": {
      "required": false,             // true اگر completed و هنوز ثبت نشده
      "submitted": false,
      "submitted_at": null,
      "rating": null,
      "status": null                 // pending|approved|rejected
    },
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### 10.4 POST `/v1/customer/orders/{id}/cancel`

```jsonc
// Request
{
  "reason_id": 3,                 // از /orders/cancel-reasons
  "reason_other": null            // اگر reason_id=99 (دیگر) الزامی
}

// Response 200
{
  "success": true,
  "message": "سفارش لغو شد.",
  "data": { /* order با status="cancelled" */ }
}

// Response 409 cannot_cancel
{
  "success": false,
  "message": "این سفارش در وضعیت فعلی قابل لغو نیست.",
  "code": "cannot_cancel"
}
```

فقط در وضعیت‌های `pending` / `assigned` / `scheduled` قابل لغو. Rate limit: ۱۰/min per user.

### 10.5 GET `/v1/customer/orders/{id}/version` (polling سبک)

```jsonc
{
  "success": true,
  "data": {
    "id": 1234,
    "status": "in_progress",
    "status_int": 6,
    "updated_at": "2026-06-07T16:00:00Z",
    "hash": "a3f5b9c12d4e"
  }
}
```

**استفاده:** هر ۱۵-۳۰ ثانیه poll. اگر `hash` فرق کرد، GET /orders/{id} کامل را بزنید. `Cache-Control: no-store`.

### 10.6 GET `/v1/customer/orders/cancel-reasons`

```jsonc
{
  "success": true,
  "data": [
    { "id": 1, "label": "پشیمان شدم" },
    { "id": 2, "label": "تعمیرکار دیگری انتخاب کردم" },
    { "id": 3, "label": "قیمت تخمینی برای من زیاد بود" },
    { "id": 4, "label": "زمان مناسب برای حضور تکنسین در دسترس نیست" },
    { "id": 5, "label": "دستگاه دیگر نیاز به تعمیر ندارد" },
    { "id": 6, "label": "به اشتباه ثبت کردم" },
    { "id": 99, "label": "دلیل دیگر" }
  ]
}
```

این لیست در `/bootstrap` هم می‌آید — بهتر است از آنجا کش کنید.

---

## 11) Invoice و پرداخت

### 11.1 GET `/v1/customer/orders/{id}/invoice`

```jsonc
{
  "success": true,
  "data": {
    "invoice_number": "INV-1405-00123",
    "order_id": 1234,
    "tracking_code": "TM-1234",
    "issued_at": "2026-06-07T15:30:00Z",
    "status": "issued",                 // draft | issued | paid | cancelled
    "customer": {
      "name": "علی محمدی",
      "phone": "09918911126",
      "address": "تهران، آزادی، پلاک ۱۰"
    },
    "technician": { "id": 5, "name": "حسن احمدی" },
    "items": [
      {
        "row": 1,
        "type": "labor",                // labor | part | service | other
        "description": "اجرت تعمیر برد",
        "quantity": 1,
        "unit_price": 250000,           // تومان
        "amount": 250000,
        "warranty_months": null
      },
      {
        "row": 2,
        "type": "part",
        "description": "خازن ۲۲۰ میکروفاراد",
        "quantity": 2,
        "unit_price": 15000,
        "amount": 30000,
        "warranty_months": 6
      }
    ],
    "totals": {
      "subtotal": 280000,
      "discount": 0,
      "discount_code": null,
      "tax_rate": 0,                    // float، مثلاً 0.09 = ۹٪
      "tax": 0,
      "total": 280000,
      "currency": "IRT"
    },
    "payment": {
      "method": null,                   // "online" بعد از پرداخت
      "is_paid": false,
      "paid_at": null,
      "payment_url": "https://panel.tamironline.com/crm/pay/INV-1405-00123"
    },
    "notes": "گارانتی فقط در صورت رعایت دستورالعمل معتبر است.",
    "pdf_url": "https://panel.tamironline.com/v1/customer/orders/1234/invoice.pdf"
  }
}
```

### 11.2 GET `/v1/customer/orders/{id}/invoice.pdf`

HTML قابل چاپ (نه binary PDF). فرانت در iframe یا webview بارگذاری کند، یا با کتابخانه‌ی کلاینت‌ساید به PDF تبدیل کند.

```html
Content-Type: text/html; charset=utf-8
```

### 11.3 پرداخت آنلاین

`payment.payment_url` یک URL عمومی است که فرانت می‌تواند در webview باز کند. درگاه‌های Zibal و Mellat هر دو پشتیبانی می‌شوند (انتخاب توسط ادمین). بعد از پرداخت موفق، callback خودکار وضعیت `invoice.status` را به `paid` تغییر می‌دهد و `paid_at` ست می‌شود.

**نحوه‌ی verify در فرانت:** بعد از بستن webview، GET /invoice را دوباره صدا بزنید و `is_paid` را چک کنید.

---

## 12) Reviews — نظرسنجی اجباری

### 12.1 GET `/v1/customer/orders/pending-reviews`

```jsonc
{
  "success": true,
  "data": [
    {
      "order_id": 1200,
      "tracking_code": "TM-1200",
      "completed_at": "2026-06-05T18:30:00Z",
      "device_name": "لباس‌شویی",
      "brand_name": "سامسونگ",
      "technician_name": "حسن احمدی"
    }
  ],
  "meta": { "total": 1 }
}
```

در splash اپ این را poll کنید — اگر چیزی بود modal اجباری بزنید.

### 12.2 POST `/v1/customer/orders/{id}/review`

```jsonc
// Headers: Authorization, Idempotency-Key
{
  "rating": 5,                    // 1..5 اجباری
  "criteria": {                   // اختیاری ولی توصیه‌شده
    "punctuality": 5,             // خوش‌قولی
    "quality": 4,                 // کیفیت کار
    "behavior": 5,                // برخورد تکنسین
    "pricing": 4                  // منصفانه بودن قیمت
  },
  "comment": "تکنسین وقت‌شناس و کاربلد بود.",   // اختیاری، حداکثر ۱۰۰۰
  "would_recommend": true                       // اختیاری
}

// Response 201
{
  "success": true,
  "message": "نظر شما ثبت شد. ممنون از همکاری.",
  "data": {
    "review": {
      "id": 99,
      "order_id": 1200,
      "rating": 5,
      "criteria": { "punctuality": 5, "quality": 4, "behavior": 5, "pricing": 4 },
      "comment": "...",
      "would_recommend": true,
      "status": "pending",
      "submitted_at": "2026-06-07T15:00:00Z"
    }
  }
}
```

**خطاهای ممکن:**
- `403` — سفارش کاربر دیگر
- `422 order_not_completed` — سفارش هنوز انجام نشده
- `409 already_reviewed` — نظر قبلی برمی‌گردد در `data.data`

Rate limit: ۱۰/min per user.

### 12.3 فلوی توصیه‌شده برای فرانت

```
splash app
  ├─ GET /v1/customer/status (همیشه)
  ├─ GET /v1/customer/bootstrap (با ETag cache)
  └─ if (logged in) GET /v1/customer/orders/pending-reviews
       └─ if length > 0 → modal اجباری بزن، تا ثبت نشد بستن نده
```

---

## 13) Profile و Notifications

### 13.1 GET/PUT `/v1/customer/profile`

```jsonc
// GET
{
  "success": true,
  "data": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": "علی",
    "last_name": "محمدی",
    "full_name": "علی محمدی",
    "email": "ali@example.com",
    "is_profile_complete": true,
    "mobile_verified_at": "2026-06-07T14:00:00Z",
    "subscription": 22345,
    "created_at": "..."
  }
}

// PUT
{ "first_name": "علی", "last_name": "محمدی", "email": "ali@example.com" }
```

### 13.2 Notifications

```jsonc
// GET /v1/customer/notifications?page=1&per_page=20
{
  "success": true,
  "data": [
    {
      "id": "uuid-here",
      "type": "order_status_changed",
      "title": "وضعیت سفارش شما تغییر کرد",
      "body": "سفارش TM-1234 به وضعیت در حال انجام رسید.",
      "data": { "order_id": 1234 },
      "read_at": null,
      "created_at": "2026-06-07T14:00:00Z"
    }
  ],
  "meta": {
    "page": 1, "per_page": 20, "total": 35, "last_page": 2,
    "unread_count": 5
  }
}

// POST /v1/customer/notifications/{id}/read
{ "success": true, "message": "علامت‌گذاری شد.", "data": { "unread_count": 4 } }

// POST /v1/customer/notifications/read-all
{ "success": true, "message": "همه‌ی پیام‌ها خوانده‌شده شدند.", "data": { "unread_count": 0 } }
```

---

## 14) Device Sessions

### 14.1 GET `/v1/customer/auth/devices`

```jsonc
{
  "success": true,
  "data": [
    {
      "id": 42,
      "device_id": "ios-uuid-here",
      "is_current": true,                         // این دستگاه فعلی است
      "last_used_at": "2026-06-07T15:00:00Z",
      "last_used_ip": "5.232.x.x",
      "created_at": "2026-06-01T10:00:00Z",
      "expires_at": "2026-07-01T10:00:00Z"
    },
    {
      "id": 41,
      "device_id": "android-uuid-old",
      "is_current": false,
      "last_used_at": "2026-05-20T12:00:00Z",
      "...": "..."
    }
  ],
  "meta": { "total": 2 }
}
```

### 14.2 DELETE `/v1/customer/auth/devices/{id}`

revoke یک دستگاه خاص. اگر `was_current=true` در پاسخ، فرانت باید redirect به login بدهد.

```jsonc
{
  "success": true,
  "message": "دستگاه revoke شد.",
  "data": { "revoked_id": 41, "was_current": false }
}
```

---

## 15) نگاشت OrderStatus

این جدول بسیار مهم است:

| داخلی | string (mobile) | status_int | معنی |
|---|---|---|---|
| `new` | **`pending`** | 0 | تازه ثبت شده، در انتظار هماهنگی |
| `coordinated` | **`assigned`** | 1 | تکنسین تعیین شده |
| `open` | **`scheduled`** | 2 | زمان مراجعه ست شده |
| `suspended` | **`suspended`** | 3 | معلق |
| `cancelled` | **`cancelled`** | 4 | لغو |
| `completed` | **`completed`** | 5 | انجام شده → نوبت نظرسنجی |
| `transit` | **`in_progress`** | 6 | تکنسین در راه/در حال کار |
| `returned` | **`in_progress`** | 7 | برگشتی |
| `declined` | **`declined`** | 9 | رد شده توسط ادمین |

**سیاست:**
- برای مقایسه از `status_int` استفاده کنید
- برای نمایش از `status_label` استفاده کنید (متن فارسی آماده)
- `is_terminal=true` → سفارش بسته شد، نباید action گرفت

---

## 16) TypeScript Types

```ts
// types/api.ts

export interface ApiSuccess<T> {
  success: true;
  data: T;
  message?: string;
  meta?: PaginationMeta | Record<string, unknown>;
}

export interface ApiError {
  success: false;
  message: string;
  code: ErrorCode;
  data?: {
    error_code: ErrorCode;
    errors?: Record<string, string[]>;
    [key: string]: unknown;
  };
}

export type ErrorCode =
  | 'unauthenticated' | 'forbidden' | 'not_found' | 'conflict'
  | 'validation_failed' | 'pending_review_required'
  | 'service_unavailable' | 'locked' | 'upgrade_required'
  | 'rate_limited' | 'server_error' | 'cannot_cancel'
  | 'order_not_completed' | 'already_reviewed';

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  unread_count?: number;
}

export type OrderStatus =
  | 'pending' | 'assigned' | 'scheduled'
  | 'in_progress' | 'suspended'
  | 'completed' | 'cancelled' | 'declined';

export type OrderType = 'repair' | 'service' | 'install';

export type ScheduledSlot = '09-12' | '12-15' | '15-18' | '18-21';

export interface Customer {
  id: number;
  mobile: string;
  first_name: string | null;
  last_name: string | null;
  full_name: string | null;
  email: string | null;
  avatar_url: string | null;
  is_profile_complete: boolean;
  mobile_verified_at: string;
  subscription: number;
  created_at: string;
}

export interface StatusPayload {
  app_disabled: boolean;
  min_version: string;
  latest_version: string;
  force_reauth_at: number;
  maintenance: { active: boolean; message: string | null };
  test_mode_active: boolean;
  server_time: string;
  db: 'ok' | 'down';
}

export interface Address {
  id: number;
  label: string | null;
  full_address: string;
  state_id: number | null;
  state_name: string | null;
  city_id: number | null;
  city_name: string | null;
  postal_code: string | null;
  phone: string | null;
  is_default: boolean;
  created_at: string;
}

export interface OrderListItem {
  id: number;
  tracking_code: string;
  status: OrderStatus;
  status_int: number;
  status_label: string;
  is_terminal: boolean;
  order_type: OrderType;
  device: { id: number; name: string; slug: string; icon: string | null } | null;
  brand: { id: number; name: string; slug: string } | null;
  scheduled_date: string | null;   // YYYY-MM-DD
  scheduled_slot: ScheduledSlot | null;
  total_amount: number | null;     // تومان
  created_at: string;
  updated_at: string;
}

export interface OrderDetail extends OrderListItem {
  problem_title: string | null;
  problem_description: string | null;
  objections: Array<{ id: number; name: string; slug: string }>;
  scheduled_at: string | null;
  address: AddressSnapshot | null;
  technician: TechnicianSnapshot | null;
  pricing: {
    estimated: number | null;
    final: number | null;
    deposit: number | null;
    total: number | null;
    currency: 'IRT';
  };
  cancel: { reason_id: number | null; reason: string } | null;
  review: {
    required: boolean;
    submitted: boolean;
    submitted_at: string | null;
    rating: number | null;
    status: 'pending' | 'approved' | 'rejected' | null;
  };
}

export interface CreateOrderPayload {
  order_type: OrderType;
  device_id: number;
  brand_id?: number;
  objection_ids?: number[];
  problem_description?: string;
  problem_title?: string;
  scheduled_date: string;          // YYYY-MM-DD
  scheduled_slot: ScheduledSlot;
  address_id: number;
  introduction?: string;
}

export interface Invoice {
  invoice_number: string | null;
  order_id: number;
  tracking_code: string;
  issued_at: string | null;
  status: 'draft' | 'issued' | 'paid' | 'cancelled';
  customer: { name: string; phone: string; address: string };
  technician: { id: number; name: string } | null;
  items: Array<{
    row: number;
    type: 'labor' | 'part' | 'service' | 'other';
    description: string;
    quantity: number;
    unit_price: number;            // تومان
    amount: number;
    warranty_months: number | null;
  }>;
  totals: {
    subtotal: number;
    discount: number;
    discount_code: string | null;
    tax_rate: number;
    tax: number;
    total: number;
    currency: 'IRT';
  };
  payment: {
    method: 'online' | 'cash' | 'card' | null;
    is_paid: boolean;
    paid_at: string | null;
    payment_url: string | null;
  };
  notes: string | null;
  pdf_url: string | null;
}

export interface DeviceSession {
  id: number;
  device_id: string | null;
  is_current: boolean;
  last_used_at: string | null;
  last_used_ip: string | null;
  created_at: string;
  expires_at: string | null;
}

export interface Notification {
  id: string;
  type: string;
  title: string | null;
  body: string | null;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
}
```

---

## 17) کدهای خطا

| HTTP | code | معنی | اقدام فرانت |
|---|---|---|---|
| 401 | `unauthenticated` | توکن ندارد یا منقضی | logout + redirect login |
| 403 | `forbidden` | منبع کاربر دیگر یا اجازه ندارد | پیام «دسترسی غیرمجاز» |
| 404 | `not_found` | منبع پیدا نشد | پیام «یافت نشد» یا back |
| 409 | `conflict` | تداخل عمومی | پیام context-dependent |
| 409 | `cannot_cancel` | سفارش در وضعیت قابل لغو نیست | دکمه‌ی لغو را غیرفعال کن |
| 409 | `pending_review_required` | کاربر نظرسنجی معوق دارد | **modal اجباری نظرسنجی** |
| 409 | `already_reviewed` | نظر قبلاً ثبت شده | نظر قبلی را نمایش بده (در `data.data`) |
| 422 | `validation_failed` | ورودی نامعتبر | به `data.errors` گوش کن، روی فیلدها قرار بده |
| 422 | `order_not_completed` | نظر روی سفارش غیر-completed | نباید رخ دهد در flow عادی |
| 423/503 | `locked` / `service_unavailable` | maintenance یا app_disabled | overlay سراسری |
| 426 | `upgrade_required` | نسخه < min_version | overlay اجباری به‌روزرسانی |
| 429 | `rate_limited` | درخواست زیاد | پیام + backoff |
| 5xx | `server_error` | خطای داخلی | toast + retry |

---

## 18) Checklist تست

### تست کلی API (با curl)

```bash
export BASE=http://127.0.0.1:8000
export MOBILE=09000000000
export TEST_OTP=487213           # CUSTOMER_APP_TEST_OTP

# 1) Status
curl -s "$BASE/v1/customer/status" | jq

# 2) Send OTP (در test mode هیچ پیامک واقعی نمی‌رود)
curl -s -X POST "$BASE/v1/auth/send-otp" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"mobile\":\"$MOBILE\"}" | jq

# 3) Verify با master OTP
RESP=$(curl -s -X POST "$BASE/v1/auth/verify-otp" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H "X-Device-ID: $(uuidgen)" \
  -d "{\"mobile\":\"$MOBILE\",\"code\":\"$TEST_OTP\"}")
echo "$RESP" | jq
export TOKEN=$(echo "$RESP" | jq -r '.token')

# 4) Profile
curl -s "$BASE/v1/customer/profile" -H "Authorization: Bearer $TOKEN" | jq

# 5) Bootstrap
curl -s "$BASE/v1/customer/bootstrap" | jq

# 6) Locations
curl -s "$BASE/v1/customer/locations/states" | jq
curl -s "$BASE/v1/customer/locations/cities?state_id=1" | jq | head -30

# 7) Address ایجاد
curl -s -X POST "$BASE/v1/customer/addresses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H 'Content-Type: application/json' \
  -d '{
    "label": "خانه",
    "province_id": 1,
    "city_id": 11,
    "full_address": "تهران، آزادی، پلاک ۱۰",
    "postal_code": "1234567890",
    "is_default": true
  }' | jq

# 8) Devices
curl -s "$BASE/v1/customer/auth/devices" -H "Authorization: Bearer $TOKEN" | jq
```

### Checklist فرانت

#### Splash / Bootstrap
- [ ] GET /status اول هر launch — overlay sec_unavailable / maintenance / upgrade_required
- [ ] GET /bootstrap با ETag cache — refetch هر چند ساعت
- [ ] GET /pending-reviews اگر logged in — modal اجباری

#### Auth
- [ ] X-Device-ID پایدار per-install (UUIDv4 ذخیره در keychain/secure storage)
- [ ] flow OTP با handle کردن `needs_profile`
- [ ] ذخیره‌ی توکن در secure storage (نه localStorage در PWA)
- [ ] گوش به X-Renewed-Token در هر response
- [ ] رفتار 401 → logout کامل + redirect login

#### Orders
- [ ] فرم با picker از locations/services/objections/holidays
- [ ] Idempotency-Key روی POST orders
- [ ] handle کردن 409 pending_review_required → modal نظرسنجی
- [ ] polling /version هر ۱۵-۳۰ ثانیه روی سفارش فعال
- [ ] لغو فقط در وضعیت‌های pending/assigned/scheduled (UI با isCancellable)

#### Reviews
- [ ] modal اجباری اگر pending-reviews پر بود — close فقط بعد از submit
- [ ] فرم با rating + 4 criteria (punctuality/quality/behavior/pricing)

#### Invoice / Payment
- [ ] GET /invoice وقتی status=completed یا paid
- [ ] webview با payment_url
- [ ] بعد از بستن webview، GET /invoice مجدد برای verify

#### Devices Management
- [ ] صفحه‌ی «دستگاه‌های فعال» در settings
- [ ] دکمه‌ی revoke per device
- [ ] اگر revoke دستگاه فعلی → logout

#### Error handling
- [ ] interceptor مرکزی برای envelope
- [ ] toast برای 5xx با retry
- [ ] modal/banner برای 401/403/426
- [ ] form errors روی فیلدها برای 422

---

## 19) سؤالات؟

برای هر سؤال دیگر:
- **داک قبلی:** `docs/MOBILE_API_CONTRACT.md` (v1 — می‌تواند به‌عنوان background خوانده شود)
- **تست curl:** `docs/CURL_TEST_AUTH.md` (تست auth flow)
- این داک (`docs/FRONTEND_HANDOFF.md`) **منبع حقیقت** است.

با تشکر — تیم بک‌اند 🙏
