# قرارداد API اپلیکیشن مشتریان — پاسخ به نامه‌ی تیم فرانت

> **خطاب به:** تیم فرانت‌اند (PWA)
> **از طرف:** تیم بک‌اند تعمیر آنلاین
> **نسخه سند:** 1.0
> **تاریخ:** 1405/03/17
> **پاسخ به سند فرانت نسخه:** 1.0

---

## ۰) خلاصه برای عجله

سلام تیم فرانت 👋

نامه‌تان دقیق بررسی شد. این پاسخ کامل با همه‌ی جزئیات قراردادها است.

**سه نکته‌ی فوری که فرانت باید بداند:**

1. **Base URL کاملاً عوض شده** — سیستم WP قبلی بازنشسته شد. سرور جدید Laravel است:
   ```
   قدیمی:  https://crm.tamironline.com/wp-json/tamir/v1
   جدید:   https://panel.tamironline.com/v1/customer
   ```
   تمام مسیرها زیر `/v1/customer/*` می‌آیند (نه `/auth/...` یا `/orders/...` به‌تنهایی).

2. **معماری**: ما یک **ماژول مستقل** به نام `CustomerApp` ساختیم که فقط برای اپ موبایل/PWA است. سایت Next.js مسیرهای جداگانه دارد (`/v1/catalog/*`, `/v1/blog/*`) که شما درگیرشان نیستید.

3. **وضعیت کلی**: از ۳۵ endpoint مورد نیاز شما، **۸ تای core auth/status آماده‌اند**، **۲۷ تا در roadmap** هستند که بلوک به بلوک می‌سازیم. این داک قرارداد دقیق همه را مشخص می‌کند تا تایپ‌ها را همین الان قفل کنید.

برای موارد آماده، شما همین الان می‌توانید کار کنید. برای موارد در حال توسعه، contract را همین الان امضا می‌کنیم تا dual development ممکن باشد.

---

## ۱) قراردادهای عمومی (Conventions)

### ۱.۱ ساختار پاسخ موفق

تمام `/v1/customer/*` در envelope استاندارد wrap می‌شوند:

```jsonc
{
  "success": true,
  "data": { /* payload */ },
  "message": "متن فارسی (اختیاری)",
  "meta": { /* اختیاری — برای pagination */ }
}
```

> ⚠️ این envelope **فقط برای** `/v1/customer/*` است. برای `/v1/catalog/*` و `/v1/blog/*` (که فرانت Next.js مصرف می‌کند) envelope نداریم و raw payload برمی‌گردد. اپ موبایل با آن مسیرها سروکار ندارد.

### ۱.۲ ساختار پاسخ خطا

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

`message` همیشه فارسی و قابل‌نمایش است. `code` کلید ماشین‌خوان برای منطق فرانت. `errors` ساختار Laravel ValidationException است.

### ۱.۳ کدهای خطا (`code`) — لیست استاندارد

| code | HTTP | معنی |
|---|---|---|
| `unauthenticated` | 401 | توکن ندارد یا نامعتبر است |
| `forbidden` | 403 | احراز هست ولی مجوز نیست (مثلاً سفارش کاربر دیگر) |
| `not_found` | 404 | منبع پیدا نشد |
| `conflict` | 409 | تداخل (مثلاً نظر تکراری) |
| `validation_failed` | 422 | ولیدیشن — `errors` پر است |
| `pending_review_required` | 409 | کاربر نظرسنجی معوق دارد |
| `service_unavailable` | 503 | maintenance یا app_disabled |
| `locked` | 423 | سرویس قفل (پروتکل service-down) |
| `upgrade_required` | 426 | نسخه‌ی کلاینت < min_version |
| `rate_limited` | 429 | درخواست زیاد |
| `server_error` | 500 | خطای داخلی |

### ۱.۴ کدهای HTTP — رفتار فرانت

| HTTP | رفتار فرانت | بک‌اند کجا برمی‌گرداند |
|---|---|---|
| `401` | logout کامل + redirect `/auth/login` | فقط توکن نامعتبر/منقضی (نه برای اعتبارسنجی) |
| `403` | پیام «دسترسی غیرمجاز» | اعتبارسنجی مالکیت (مثلاً سفارش کاربر دیگر) |
| `409` با `pending_review_required` | مودال اجباری نظرسنجی | POST سفارش جدید وقتی نظر معوق دارد |
| `422` | فرم validation errors | همه‌ی validation errors |
| `423 / 503` | overlay سراسری «سرور در دسترس نیست» | `app_status.maintenance.active` یا `app_disabled` |
| `426` | overlay «نسخه قدیمی، آپدیت» | اگر header `X-App-Version` < `min_version` (در آینده فعال می‌شود) |
| `429` | پیام rate limit | throttle middleware |

### ۱.۵ هدرهای امنیتی

| هدر | جهت | وضعیت | توضیح |
|---|---|---|---|
| `Authorization: Bearer <token>` | فرانت → بک | ✅ | Sanctum token (همان توکن `/v1/auth/verify-otp`). توکن **OPaque** است (نه JWT) — `iat` ندارد. |
| `X-Device-ID: <uuid>` | فرانت → بک | 🔧 پیشنهادی | فعلاً ذخیره نمی‌شود ولی پذیرفته می‌شود (Laravel هدر را ignore می‌کند). در فاز بعد در `personal_access_tokens.metadata` ذخیره می‌شود. **پاسخ به سؤال**: چند دستگاه همزمان مجاز است؛ هر دستگاه توکن خودش را دارد. `/v1/auth/logout-all` همه را revoke می‌کند. |
| `Idempotency-Key: <uuid>` | فرانت → بک | ✅ | روی همه‌ی POST/PUT/PATCH/DELETE. **پاسخ به سؤال**: نگهداری ۲۴ ساعت در cache. درخواست تکراری همان response قبلی را با header `Idempotent-Replay: true` برمی‌گرداند. کلید UUID v4 یا alpha-num 16-128 کاراکتر. |
| `X-Renewed-Token: <token>` | بک → فرانت | ✅ | rolling renewal. وقتی توکن از نیمه‌ی عمرش (۱۵ روز پیش‌فرض از ۳۰) عبور کرد، در هر پاسخ این header می‌آید. فرانت باید آن را در storage جایگزین کند. توکن قدیمی همچنان تا انقضای کامل کار می‌کند. |
| `Idempotent-Replay: true` | بک → فرانت | ✅ | نشانه‌ی این که این یک replay از cache است (نه اجرای جدید). |

**`force_reauth_at`** (پاسخ به سؤال ۴ شما): یک timestamp سراسری در `/v1/customer/status` است. سیاست: اگر `force_reauth_at > token_created_at_unix` → فرانت **خودش** logout کند. توکن‌های Sanctum ما `iat` ندارند ولی `created_at` در `personal_access_tokens` هست — می‌توانید این timestamp را موقع verify-otp ذخیره کنید (مثلاً در localStorage کنار توکن: `{ token, issued_at }`).

### ۱.۶ فرمت‌های قطعی

| موضوع | تصمیم نهایی |
|---|---|
| **تاریخ/زمان** | همه ISO 8601 UTC (مثلاً `2026-06-07T14:30:00Z`). شمسی **معمولاً فقط در UI سمت فرانت** ساخته می‌شود. استثنا: payload فاکتور (`issued_at_jalali`، `paid_at_jalali`) برای سهولت نمایش — مرجع منطق همچنان ISO است. |
| **شماره موبایل** | `09xxxxxxxxx` (۱۱ رقم با صفر اول). `PhoneNormalizer` در سمت بک هر فرمتی (`+98`, `0098`, `98`, با ارقام فارسی/عربی) را به این شکل نرمالایز می‌کند. |
| **پول** | **تومان (IRT)** در همه‌ی API. integer. در DB ریال است ولی در API boundary تقسیم بر ۱۰ می‌شود (`Modules\CustomerApp\Support\Money`). |
| **id** | همیشه number (نه string). |
| **timezone** | همه UTC بازگشت می‌گیرد. فرانت در UI به Asia/Tehran تبدیل می‌کند. |
| **slug** | string، فقط `[a-z0-9-]`. |

---

## ۲) Audit بخش‌به‌بخش نامه‌ی شما

### ۲.۱ Auth

| نیاز شما | وضعیت | URL واقعی |
|---|---|---|
| `POST /auth/send-otp` | ✅ آماده | `POST /v1/auth/send-otp` |
| `POST /auth/verify-otp` | ✅ آماده (با تفاوت قرارداد — جزئیات پایین) | `POST /v1/auth/verify-otp` |
| `POST /auth/register` | 🔧 ادغام شده | جدا نیست — اولین `verify-otp` کاربر را می‌سازد. `temp_token` flow ندارید. |
| `POST /auth/logout` | ✅ آماده | `POST /v1/auth/logout` |
| `POST /auth/logout-all` | ✅ اضافی | `POST /v1/auth/logout-all` |
| `GET /auth/me` | ✅ آماده | `GET /v1/auth/me` |
| `POST /auth/renew` | 🔧 خودکار | endpoint جدا نداریم — به‌جایش `X-Renewed-Token` rolling است (بخش ۱.۵). |

> ⚠️ **توجه**: مسیرهای auth زیر `/v1/auth/*` هستند (نه `/v1/customer/auth/*`) — چون توکن صادرشده برای هر کلاینتی (موبایل، Next.js، اپ ادمین آینده) کار می‌کند. ولی response shape آن‌ها envelope CustomerApp را ندارد، چون قبل از این ماژول ساخته شدند. **برای سادگی، فرانت موبایل، Auth و باقی endpoint ها را با همان http client بزند ولی response shape متفاوت را برای auth در نظر بگیرد.**

#### POST `/v1/auth/send-otp`

**Request:**
```jsonc
{
  "mobile": "09918911126"
}
```

**Response 200:**
```jsonc
{
  "ok": true,
  "message": "کد تأیید ارسال شد.",
  "expires_in": 120,        // ثانیه — کد تا کِی معتبر است
  "can_resend_in": 60       // ثانیه — کِی می‌توان دوباره درخواست داد
}
```

**Response 422 (نامعتبر یا rate-limit):**
```jsonc
{
  "message": "شماره موبایل نامعتبر است.",
  "errors": {
    "mobile": ["شماره موبایل نامعتبر است."]
  }
}
```

پاسخ به سؤال: انقضای OTP **۲ دقیقه** (قابل تنظیم در env `SMS_OTP_EXPIRE_MINUTES`). Rate-limit: ۱۰ در دقیقه per IP + ۵ در ساعت per phone.

#### POST `/v1/auth/verify-otp`

**Request:**
```jsonc
{
  "mobile": "09918911126",
  "code": "123456"
}
```

**Response 200:**
```jsonc
{
  "ok": true,
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
  "token_type": "Bearer",
  "customer": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": null,           // null = هنوز پروفایل تکمیل نشده
    "last_name": null,
    "full_name": null,
    "email": null,
    "avatar_url": null,
    "is_profile_complete": false,
    "mobile_verified_at": "2026-06-07T14:00:00Z",
    "subscription": 22345,
    "created_at": "2026-06-07T14:00:00Z"
  },
  "is_new": true,                 // اولین بار وارد می‌شود؟
  "needs_profile": true           // فرانت باید فرم نام بپرسد؟
}
```

> 🔄 **mapping برای فرانت**: ما `needs_profile` می‌گوییم، شما `needs_register` خواسته بودید. معنی یکسان است: «اگر true، باید قبل از ادامه نام/نام‌خانوادگی را بگیرید». ما separate `temp_token` نداریم — همان token اصلی برمی‌گردد. در `complete-profile` همان token استفاده می‌شود.

**Response 422 (کد اشتباه):**
```jsonc
{
  "message": "کد تأیید نامعتبر است.",
  "errors": {
    "code": ["کد تأیید نامعتبر است."]
  }
}
```

#### POST `/v1/auth/complete-profile` (پاسخ به نیاز "register")

**Headers:** `Authorization: Bearer <token>`

**Request:**
```jsonc
{
  "first_name": "علی",
  "last_name": "محمدی"     // اختیاری
}
```

**Response 200:**
```jsonc
{
  "ok": true,
  "customer": {
    "id": 12345,
    "mobile": "09918911126",
    "first_name": "علی",
    "last_name": "محمدی",
    "full_name": "علی محمدی",
    "is_profile_complete": true,
    "...": "بقیه فیلدها"
  }
}
```

#### GET `/v1/auth/me`

**Response 200:**
```jsonc
{
  "ok": true,
  "customer": { "...": "همان شکل customer" }
}
```

#### POST `/v1/auth/logout` / `/v1/auth/logout-all`

**Response 200:**
```jsonc
{ "ok": true, "message": "با موفقیت خارج شدید." }
```

پاسخ به سؤال: token در همان لحظه از جدول `personal_access_tokens` پاک می‌شود (server-side hard delete، نه blacklist).

### ۲.۲ Status (وضعیت سراسری)

| نیاز شما | وضعیت | URL |
|---|---|---|
| `GET /status` | ✅ آماده — کامل با همه‌ی فیلدهای درخواستی شما | `GET /v1/customer/status` |

#### GET `/v1/customer/status` (Public — Auth لازم نیست)

**Response 200:**
```jsonc
{
  "success": true,
  "data": {
    "app_disabled": false,
    "min_version": "1.0.0",
    "latest_version": "1.0.0",
    "force_reauth_at": 0,                       // 0 = غیرفعال
    "maintenance": {
      "active": false,
      "message": null
    },
    "server_time": "2026-06-07T14:30:00Z",
    "db": "ok"                                   // "ok" | "down"
  }
}
```

**Response 503 (app_disabled):**
```jsonc
{
  "success": false,
  "message": "سرویس موقتاً در دسترس نیست.",
  "code": "service_unavailable"
}
```

**سیاست توصیه‌شده برای فرانت:**
- در splash و بعد از هر ۵ دقیقه idle این را poll کنید
- اگر `app_disabled=true` یا `maintenance.active=true` → overlay سراسری
- اگر `compare(client_version, min_version) < 0` → overlay اجباری به‌روزرسانی
- اگر `compare(client_version, latest_version) < 0` ولی >= min_version → banner نرم
- اگر `force_reauth_at > token_issued_at` → logout اجباری

### ۲.۳ Catalog / Services — هنوز موبایل‌محور نیست

این بخش از سند شما بیشتر کاتالوگ سایت است. وضعیت فعلی:

| نیاز شما | وضعیت | URL/توضیح |
|---|---|---|
| `GET /services/categories` | 🔧 موجود | `GET /v1/catalog/device-categories` (با envelope قدیمی، برای Next.js). برای موبایل می‌توانیم alias زیر `/v1/customer/services/categories` بسازیم. |
| `GET /services/brands` | 🔧 موجود | `GET /v1/catalog/brands` |
| `GET /services/types` | ❌ نیست | باید بسازیم — منظور دقیقاً چیست؟ (انواع خدمات: تعمیر/سرویس؟ یا چیز دیگر؟ نمی‌دانم — لطفاً نمونه payload فرستید) |
| `GET /services/objections` | ❌ نیست | باید بسازیم. منبع داده‌ها چیست؟ admin-managed یا static config؟ |
| `GET /services/banners` | 🔧 per-zone موجود | `GET /v1/banners/{zone}`. برای موبایل می‌توانیم endpoint تجمیعی بسازیم. |
| `GET /bootstrap` (تجمیعی) | ❌ نیست | پیشنهاد شما عالی است — در Block 8 می‌سازیم. |

#### قرارداد پیشنهادی `GET /v1/customer/bootstrap` (Block 8 آینده)

**Response 200:**
```jsonc
{
  "success": true,
  "data": {
    "categories": [ /* device categories */ ],
    "brands": [ /* active brands */ ],
    "objections": [ /* لیست ایرادات قابل انتخاب */ ],
    "banners": [
      { "zone": "mobile_home", "items": [ /* banners */ ] }
    ],
    "holidays": [ /* تعطیلات ۹۰ روز آینده */ ],
    "time_slots": [
      { "value": "09-12", "label": "۹ تا ۱۲ صبح" },
      { "value": "12-15", "label": "۱۲ تا ۱۵" },
      { "value": "15-18", "label": "۱۵ تا ۱۸" },
      { "value": "18-21", "label": "۱۸ تا ۲۱" }
    ],
    "cancel_reasons": [ /* بخش ۶ */ ],
    "version": "abc123"                          // hash برای ETag/cache invalidation
  }
}
```
با `Cache-Control: public, max-age=3600` تا فرانت در روز فقط یک بار fetch کند.

### ۲.۴ Orders — حیاتی‌ترین بلوک

**هیچ‌کدام از این‌ها هنوز نیست.** در Block 1 (turn بعدی) می‌سازیم. قرارداد دقیق:

| نیاز شما | وضعیت | قرارداد |
|---|---|---|
| `GET /orders` | 🚧 Block 1 | `GET /v1/customer/orders` |
| `POST /orders` | 🚧 Block 1 | `POST /v1/customer/orders` با Idempotency-Key |
| `GET /orders/{id}` | 🚧 Block 1 | `GET /v1/customer/orders/{id}` |
| `POST /orders/{id}/cancel` | 🚧 Block 1 | `POST /v1/customer/orders/{id}/cancel` |
| `GET /orders/{id}/version` | 🚧 Block 1 | `GET /v1/customer/orders/{id}/version` |
| `GET /orders/{id}/invoice` | 🚧 Block 1 | `GET /v1/customer/orders/{id}/invoice` |
| `GET /orders/cancel-reasons` | 🚧 Block 1 | `GET /v1/customer/orders/cancel-reasons` (config آماده است) |
| `GET /orders/pending-reviews` | 🚧 Block 3 | جزئیات در بخش ۳ |
| `POST /orders/{id}/review` | 🚧 Block 3 | جزئیات در بخش ۳ |
| `POST /orders/{id}/attachments` | 🚧 Block 1 | multipart/form-data — جدا از create |

#### قرارداد `GET /v1/customer/orders`

**Headers:** `Authorization: Bearer <token>`

**Query:** `?page=1&per_page=10&status=pending,in_progress`

**Response 200:**
```jsonc
{
  "success": true,
  "data": [
    {
      "id": 1234,
      "tracking_code": "TM-1234",
      "status": "scheduled",                    // string مورد انتظار شما
      "status_int": 2,                          // بخش ۵ — جدول mapping
      "status_label": "هماهنگ شده",
      "order_type": "repair",                   // "repair" | "service"
      "device": { "id": 5, "name": "لباس‌شویی", "slug": "washing-machine", "icon": "..." },
      "brand": { "id": 10, "name": "سامسونگ", "slug": "samsung", "logo": "..." },
      "scheduled_date": "2026-06-10",           // ISO date فقط (YYYY-MM-DD)
      "scheduled_slot": "09-12",                // بخش ۶
      "address_summary": "تهران، خیابان آزادی، پلاک ۱۰",
      "technician": null,                       // یا { id, name, rating } بعد از assign
      "total_amount": null,                     // تا فاکتور صادر نشده null؛ بعد تومان integer
      "review": {                               // بخش ۳
        "required": false,
        "submitted": false,
        "submitted_at": null,
        "rating": null
      },
      "created_at": "2026-06-07T14:00:00Z",
      "updated_at": "2026-06-07T15:30:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 47,
    "last_page": 5
  }
}
```

#### قرارداد `POST /v1/customer/orders`

**Headers:**
```
Authorization: Bearer <token>
Idempotency-Key: 7c9e6679-7425-40de-944b-e07fc1f90ae7
Content-Type: application/json
```

**Request:**
```jsonc
{
  "order_type": "repair",                     // "repair" | "service"
  "device_id": 5,
  "brand_id": 10,
  "objection_ids": [12, 14],                  // ids از /services/objections
  "objection_description": "صدای غیرعادی می‌دهد",
  "scheduled_date": "1405-03-20",             // ⚠️ شمسی؟ تصمیم: ما ISO می‌خواهیم. شما برای ما "2026-06-10" بفرستید. تبدیل شمسی → میلادی سمت فرانت.
  "scheduled_slot": "09-12",                  // enum (بخش ۶)
  "introduction": "از همکار معرفی شدم",       // اختیاری
  "address_id": 7                             // از /addresses
}
```

**⚠️ توجه**: فیلد `address` inline در نامه‌ی شما بود. ما **توصیه می‌کنیم address-id only باشد** (نه inline). دلیل: کاربر نمی‌تواند آدرس بدون قبلاً ساخت بفرستد. flow درست: ابتدا POST /addresses → سپس POST /orders با id.

اگر اصرار دارید inline هم بپذیریم، در turn بعدی اضافه می‌کنم.

**Response 201:**
```jsonc
{
  "success": true,
  "message": "سفارش ثبت شد.",
  "data": { /* همان شکل order detail */ }
}
```

**Response 409 (replay):**
```jsonc
{
  "success": true,
  "message": "سفارش قبلاً ثبت شده.",
  "data": { /* همان نتیجه‌ی قبلی */ }
}
// + Header: Idempotent-Replay: true
```

**Response 409 (pending review):**
```jsonc
{
  "success": false,
  "message": "ابتدا نظرسنجی سفارش قبلی را ثبت کنید.",
  "code": "pending_review_required",
  "data": {
    "error_code": "pending_review_required",
    "pending_order_id": 1200          // برای redirect مودال
  }
}
```

#### قرارداد `POST /v1/customer/orders/{id}/cancel`

**Request:**
```jsonc
{
  "reason_id": 3,                     // از /orders/cancel-reasons
  "reason_other": null                // اگر reason_id=99 (دیگر) لازم است
}
```

**Response 200:**
```jsonc
{
  "success": true,
  "message": "سفارش لغو شد.",
  "data": { /* order detail با status="cancelled" */ }
}
```

**Response 409 (نمی‌توان لغو کرد):**
```jsonc
{
  "success": false,
  "message": "این سفارش در حال انجام است و قابل لغو نیست.",
  "code": "cannot_cancel"
}
```

#### قرارداد `GET /v1/customer/orders/{id}/version` (polling سبک)

**Response 200:**
```jsonc
{
  "success": true,
  "data": {
    "id": 1234,
    "status": "in_progress",
    "status_int": 6,
    "updated_at": "2026-06-07T16:00:00Z",
    "hash": "a3f5b9c"                          // hash از فیلدهای متغیر
  }
}
```
با `Cache-Control: no-store`. فرانت می‌تواند هر ۱۵ ثانیه poll کند.

#### قرارداد `GET /v1/customer/orders/cancel-reasons`

**Response 200:**
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
این لیست **در config پروژه ذخیره است** (نه DB) — IDها ثابت‌اند. در صورت اضافه شدن جدید، ID قبلی reuse نمی‌شود.

#### قرارداد `POST /v1/customer/orders/{id}/attachments`

**Headers:** `Authorization: Bearer <token>`، `Content-Type: multipart/form-data`

**Request (multipart):**
```
images[]: <file1.jpg>
images[]: <file2.jpg>
```
حداکثر ۵ تصویر، هر کدام تا ۵MB، فرمت‌های jpg/jpeg/png/webp.

**Response 200:**
```jsonc
{
  "success": true,
  "data": {
    "attachments": [
      { "id": 22, "url": "https://...", "thumbnail_url": "https://..." },
      { "id": 23, "url": "https://...", "thumbnail_url": "https://..." }
    ]
  }
}
```

### ۲.۵ Addresses & Locations

| نیاز شما | وضعیت | قرارداد |
|---|---|---|
| `GET /addresses` | 🚧 Block 2 | `GET /v1/customer/addresses` |
| `POST /addresses` | 🚧 Block 2 | `POST /v1/customer/addresses` |
| `PUT /addresses/{id}` | 🚧 Block 2 | `PUT /v1/customer/addresses/{id}` |
| `DELETE /addresses/{id}` | 🚧 Block 2 | `DELETE /v1/customer/addresses/{id}` |
| `GET /locations/states` | ✅ | فقط استان‌های فعال (پرچم «نمایش در اپ» / is_active — گیت جداگانهٔ «محدودهٔ سرویس‌دهی» حذف شد) |
| `GET /locations/cities` | ✅ | شهرهای اصلیِ فعالِ استان‌های فعال + `has_districts` |
| `GET /locations/districts?city_id=N` | ✅ 🆕 | مناطق فعالِ شهر (۲۲ منطقه تهران، ۱۳ کرج) |
| `GET /locations/reverse-geocode?lat&lng` | ✅ 🆕 | پروکسی نشان — private + throttle 30/min |

> **UPDATE 2026-06-14 — منبع واحد:** نمایش استان/شهر/منطقه در اپ فقط با پرچم «نمایش در اپ» (is_active) کنترل می‌شود؛ گیتِ قدیمیِ «محدودهٔ سرویس‌دهی» حذف شد. مدیریت همه در پنل: «CRM ← مناطق و محدودهٔ سرویس».
> **UPDATE 2026-06-11 — فلوی location عوض شد:** استان از کاربر پرسیده نمی‌شود.
> فلو: شهر ← منطقه ← (اختیاری) پین نقشه نشان. استان سمت سرور از شهر ست می‌شود.
> جزئیات کامل + نمونه کد نقشه در `docs/FRONTEND_LOCATIONS_NESHAN.md`.
> **رفعِ باگِ «منطقه داخل نشانی»:** راهنمای کامل در `docs/FRONTEND_ADDRESS_DISTRICT_FIX.md`.

#### قرارداد Address (به‌روز)

```jsonc
{
  "id": 7,
  "label": "خانه",                            // اختیاری — مثلاً "خانه" / "محل کار"
  "full_address": "خیابان آزادی، کوچه‌ی ۵، پلاک ۱۰، واحد ۳",
  "city_id": 102,
  "city_name": "تهران",
  "district_id": 110,                          // 🆕 اختیاری — منطقه شهرداری
  "district_name": "منطقه ۵ تهران",            // 🆕
  "state_id": 8,                               // فقط در پاسخ — در request لازم نیست
  "state_name": "تهران",
  "latitude": 35.7219,                         // 🆕 اختیاری — پین نقشه نشان
  "longitude": 51.3347,                        // 🆕
  "postal_code": "1234567890",                // ۱۰ رقم
  "phone": "02144556677",                     // اختیاری — تلفن ثابت محل
  "is_default": true,
  "created_at": "2026-06-07T14:00:00Z"
}
```

#### قرارداد Location (به‌روز)

```jsonc
// GET /v1/customer/locations/cities  ← state_id لازم نیست
{
  "success": true,
  "data": [
    { "id": 102, "state_id": 8, "name": "تهران", "slug": "tehran", "has_districts": true },
    { "id": 103, "state_id": 8, "name": "شهرری", "slug": "shahr-e-ray", "has_districts": false }
  ]
}

// GET /v1/customer/locations/districts?city_id=102
{
  "success": true,
  "data": [
    { "id": 110, "city_id": 102, "name": "منطقه ۱ تهران", "slug": "tehran-district-1" }
  ],
  "meta": { "city_id": 102, "total": 22 }
}

// GET /v1/customer/locations/reverse-geocode?lat=35.7219&lng=51.3347  (Bearer لازم)
{
  "success": true,
  "data": {
    "formatted_address": "تهران، ونک، خیابان ملاصدرا...",
    "province": "تهران", "city": "تهران", "district": "منطقه ۳", "route": "ملاصدرا"
  }
}
```

### ۲.۶ Profile / Notifications / Holidays

| نیاز شما | وضعیت |
|---|---|
| `GET /profile` | 🔧 موجود (همان `/v1/auth/me`) |
| `PUT /profile` | 🚧 Block 5 — `PUT /v1/customer/profile` با همه‌ی فیلدها |
| `GET /notifications` | 🚧 Block 5 — `GET /v1/customer/notifications` |
| `POST /notifications/{id}/read` | 🚧 Block 5 |
| `POST /notifications/read-all` | 🚧 Block 5 |
| `GET /holidays` | 🚧 Block 5 — `GET /v1/customer/holidays?from=&to=` |

#### قرارداد `PUT /v1/customer/profile`

```jsonc
// Request
{
  "first_name": "علی",
  "last_name": "محمدی",
  "email": "ali@example.com",
  "avatar": "<media_id from upload>"      // اختیاری
}
```

#### قرارداد `GET /v1/customer/notifications`

```jsonc
{
  "success": true,
  "data": [
    {
      "id": "uuid-here",
      "type": "order_status_changed",          // enum
      "title": "وضعیت سفارش شما تغییر کرد",
      "body": "سفارش TM-1234 به وضعیت در حال انجام رسید.",
      "data": { "order_id": 1234 },            // payload خاص هر type
      "read_at": null,                          // یا ISO datetime
      "created_at": "2026-06-07T14:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 35, "last_page": 2, "unread_count": 5 }
}
```

### ۲.۷ نظرسنجی اجباری (Block 3)

> **UPDATE 2026-06-08 — جریان نظرسنجی به سبک تگ تغییر کرد.**
> به‌جای ۴ معیار عددی، حالا کاربر بعد از دادن rating یک‌سری **تگ نقطه قوت/ضعف** انتخاب می‌کند.
> فیلد قدیمی `criteria` همچنان قابل ارسال است (backward-compatible) ولی توصیه می‌شود به‌جای آن از `tag_ids` استفاده کنید.

#### جریان جدید (پیشنهادی)

```
۱. کاربر سفارش completed دارد → مودال نظرسنجی نمایش
۲. کاربر rating انتخاب می‌کند (1..5)
۳. فرانت GET /v1/customer/reviews/tags را صدا می‌زند (یک‌بار، با cache)
۴. بر اساس rating تصمیم می‌گیرد کدام گروه نمایش دهد:
   - rating 1-2 → فقط cons (نقاط ضعف)
   - rating 3   → هر دو
   - rating 4-5 → فقط pros (نقاط قوت)
   این تصمیم UX سمت فرانت است؛ سرور هیچ محدودیتی روی ترکیب pro/con با rating اعمال نمی‌کند.
۵. کاربر چند تگ انتخاب می‌کند + متن نظر می‌نویسد
۶. فرانت POST /v1/customer/orders/{id}/review را با rating + tag_ids + comment ارسال می‌کند
```

#### `GET /v1/customer/orders/pending-reviews`

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

#### `GET /v1/customer/reviews/tags`  🆕

**Public** — auth لازم نیست. `Cache-Control: public, max-age=300` ست می‌شود.
تگ‌ها admin-managed هستند (از `/admin/customer-app/review-tags`).
تعداد pros و cons در seed اولیه برابر است (۶ تا هرکدام) و توصیه می‌شود برابر بمانند.

```jsonc
// Response 200
{
  "success": true,
  "data": {
    "pros": [
      { "id": 1, "slug": "pro-punctual",     "label": "وقت‌شناسی",                 "icon": "clock" },
      { "id": 2, "slug": "pro-respectful",   "label": "برخورد محترمانه",            "icon": "smile" },
      { "id": 3, "slug": "pro-expert",       "label": "تخصص بالا",                  "icon": "award" },
      { "id": 4, "slug": "pro-clean",        "label": "تمیزکاری پس از کار",         "icon": "sparkles" },
      { "id": 5, "slug": "pro-fair-price",   "label": "قیمت منصفانه",               "icon": "wallet" },
      { "id": 6, "slug": "pro-clear-explain","label": "توضیح روشن فرآیند تعمیر",    "icon": "message-square" }
    ],
    "cons": [
      { "id": 7,  "slug": "con-late",         "label": "تأخیر در مراجعه",      "icon": "clock-alert" },
      { "id": 8,  "slug": "con-rude",         "label": "برخورد نامناسب",        "icon": "frown" },
      { "id": 9,  "slug": "con-low-quality",  "label": "کیفیت پایین کار",       "icon": "thumbs-down" },
      { "id": 10, "slug": "con-messy",        "label": "شلختگی محل کار",        "icon": "trash-2" },
      { "id": 11, "slug": "con-overpriced",   "label": "قیمت بالا",              "icon": "badge-dollar-sign" },
      { "id": 12, "slug": "con-poor-explain", "label": "توضیح ناکافی",          "icon": "message-square-off" }
    ]
  },
  "meta": { "total_pros": 6, "total_cons": 6 }
}
```

**نکات:**
- `icon` نام یک آیکن از Lucide (lucide-react) است. اگر آیکن خاصی در دسترس نیست، می‌توانید آن را نادیده بگیرید.
- فقط تگ‌های `is_active = true` در پاسخ می‌آیند.
- ادمین می‌تواند تگ‌ها را در آینده اضافه/ویرایش/حذف کند — لیست را در splash یا با cache کوتاه (۵ دقیقه) رفرش کنید.

#### `POST /v1/customer/orders/{id}/review`

**Headers:** `Authorization: Bearer <token>`، `Idempotency-Key: <uuid>`

```jsonc
// Request — جریان جدید (پیشنهادی)
{
  "rating": 5,                                  // 1..5 — اجباری
  "tag_ids": [1, 3, 5],                         // اختیاری، حداکثر ۸ تگ، تکراری نباشد
  "comment": "تکنسین وقت‌شناس و کاربلد بود.",  // اختیاری، حداکثر ۱۰۰۰ کاراکتر
  "would_recommend": true                       // اختیاری
}
```

```jsonc
// Request — جریان قدیمی (هنوز کار می‌کند، backward-compatible)
{
  "rating": 5,
  "criteria": {                                 // به‌جای tag_ids قابل ارسال است
    "punctuality": 5,
    "quality": 4,
    "behavior": 5,
    "pricing": 4
  },
  "comment": "...",
  "would_recommend": true
}
```

```jsonc
// Response 201
{
  "success": true,
  "message": "نظر شما ثبت شد. ممنون از همکاری.",
  "data": {
    "id": 42,
    "order_id": 1200,
    "rating": 5,
    "criteria": null,
    "comment": "...",
    "would_recommend": true,
    "status": "pending",
    "tags": [
      { "id": 1, "slug": "pro-punctual",   "label": "وقت‌شناسی",     "type": "pro", "icon": "clock" },
      { "id": 3, "slug": "pro-expert",     "label": "تخصص بالا",      "type": "pro", "icon": "award" },
      { "id": 5, "slug": "pro-fair-price", "label": "قیمت منصفانه",   "type": "pro", "icon": "wallet" }
    ],
    "submitted_at": "2026-06-08T15:00:00Z"
  }
}
```

**پاسخ به سؤال‌های شما:**
1. **تگ‌ها admin-managed هستند** — لیست از `GET /v1/customer/reviews/tags`.
2. **معیارهای قدیمی** (`criteria.punctuality|quality|behavior|pricing`) هنوز قابل ارسال — برای backward compatibility.
3. **اجباری enforce می‌شود** — اگر کاربر نظر معوق دارد، POST /orders بلاک می‌شود با کد `pending_review_required` (۴۰۹).
4. **محدودیت‌ها**: `tag_ids` حداکثر ۸ عدد، فقط تگ‌های active، بدون تکرار، فقط id های واقعی DB.
5. **محدودیت نوع تگ**: سرور بررسی نمی‌کند که کاربر برای rating بالا فقط pros و برای rating پایین فقط cons انتخاب کرده باشد — این تصمیم UX سمت فرانت است.
6. **حداکثر comment**: ۱۰۰۰ کاراکتر.

**خطاهای ممکن:**
- `403` — سفارش متعلق به کاربر دیگر
- `409 already_reviewed` — نظر تکراری: همان نظر قبلی (با tag‌های انتخاب‌شده) برمی‌گردد (نه خطا)
- `422 order_not_completed` — سفارش هنوز completed نیست
- `422 validation_failed` — مثلاً `tag_ids.*.exists` (id نامعتبر) یا `tag_ids.max` (بیش از ۸ تا)

### ۲.۸ فاکتور حرفه‌ای (Block 4)

مدل پایه Invoice موجود است (Modules\CRM\Models\Invoice). توسعه‌ی فیلدها در Block 4. قرارداد نهایی:

#### `GET /v1/customer/orders/{id}/invoice`

```jsonc
{
  "success": true,
  "data": {
    "invoice_number": "INV-1405-00123",
    "order_id": 1234,
    "tracking_code": "TM-1234",
    "issued_at": "2026-06-07T15:30:00Z",
    "issued_at_jalali": "1405/03/17 19:00",   // ⭐ شمسی برای نمایش — TZ: Tehran
    "status": "issued",                       // "draft" | "issued" | "paid" | "refunded"
    "customer": {
      "name": "علی محمدی",
      "phone": "09918911126",
      "address": "تهران، آزادی، پلاک ۱۰"
    },
    "technician": {
      "id": 5,
      "name": "حسن احمدی"
    },
    "items": [
      {
        "row": 1,
        "type": "labor",                      // "labor" | "part" | "service" | "other"
        "description": "اجرت تعمیر برد",
        "quantity": 1,
        "unit_price": 250000,                 // ⚠️ تومان (integer)
        "unit_price_formatted": "۲۵۰٬۰۰۰ تومان",  // ⭐ آماده برای نمایش
        "amount": 250000,
        "amount_formatted": "۲۵۰٬۰۰۰ تومان",
        "warranty_months": 6
      },
      {
        "row": 2,
        "type": "part",
        "description": "خازن ۲۲۰ میکروفاراد",
        "quantity": 2,
        "unit_price": 15000,
        "unit_price_formatted": "۱۵٬۰۰۰ تومان",
        "amount": 30000,
        "amount_formatted": "۳۰٬۰۰۰ تومان",
        "warranty_months": 3
      }
    ],
    "totals": {
      "subtotal": 280000,
      "subtotal_formatted": "۲۸۰٬۰۰۰ تومان",
      "discount": 20000,
      "discount_formatted": "۲۰٬۰۰۰ تومان",
      "discount_code": "OFF20",
      "tax_rate": 0.09,                       // ۹٪
      "tax": 23400,
      "tax_formatted": "۲۳٬۴۰۰ تومان",
      "total": 283400,
      "total_formatted": "۲۸۳٬۴۰۰ تومان",
      "currency": "IRT"                       // همیشه IRT
    },
    "payment": {
      "method": "online",                     // "online" | "cash" | "card"
      "is_paid": false,
      "paid_at": null,
      "paid_at_jalali": null,                 // ⭐ شمسی همان منطق issued_at_jalali
      "payment_url": "https://gateway.zibal.ir/start/abc123"
    },
    "notes": "گارانتی فقط در صورت رعایت دستورالعمل استفاده معتبر است.",
    "pdf_url": "https://panel.tamironline.com/v1/customer/orders/1234/invoice.pdf"
  }
}
```

**پاسخ به سؤال‌های شما:**
1. **واحد پول IRT (تومان)** قطعی است. integer.
2. **مالیات**: نرخ از env قابل تنظیم (`INVOICE_TAX_RATE`). فعلاً ۰ — وقتی فعال شد در tax_rate برمی‌گردد.
3. **پرداخت آنلاین**: ماژول Zibal از قبل در CRM داریم. در Block 4 به فاکتور وصل می‌شود.
4. **PDF**: mPDF در dependencies است. در Block 4 endpoint `/invoice.pdf` فعال می‌شود.
5. **دسترسی فاکتور**: از وضعیت `coordinated` به بعد قابل دسترس (هرجا تخمین داریم).
6. **invoice_number**: فرمت `INV-{شمسی-سال}-{شماره ۵رقمی پشت‌سرهم}`، یکتا.

---

## ۳) جدول نگاشت وضعیت سفارش (پاسخ به درخواست حیاتی شما)

این نگاشت در `Modules\CustomerApp\Support\OrderStatusMapper` متمرکز شده:

| داخلی (DB) | string (فرانت) | status_int (موبایل) | معنی |
|---|---|---|---|
| `new` | **`pending`** | 0 | تازه ثبت شده، در انتظار هماهنگی |
| `coordinated` | **`assigned`** | 1 | تکنسین تعیین شده |
| `open` | **`scheduled`** | 2 | زمان مراجعه ست شده |
| `suspended` | **`suspended`** | 3 | معلق |
| `cancelled` | **`cancelled`** | 4 | لغو توسط کاربر یا ادمین |
| `completed` | **`completed`** | 5 | انجام شده — نوبت نظرسنجی |
| `transit` | **`in_progress`** | 6 | تکنسین در راه/در حال کار |
| `returned` | **`in_progress`** | 7 | برگشتی (فرانت همان in_progress) |
| `declined` | **`declined`** | 9 | رد شده توسط ادمین |

**سیاست**: فرانت برای **مقایسه** از `status_int` و برای **نمایش** از `status` (string) استفاده کند. در Resource Json ما هر سه می‌آیند: `status`, `status_int`, `status_label`.

---

## ۴) Slot ها و دلایل لغو (constants)

این‌ها در config پروژه ثابت‌اند:

### Time Slots
```jsonc
[
  { "value": "09-12", "label": "۹ تا ۱۲ صبح", "start": "09:00", "end": "12:00" },
  { "value": "12-15", "label": "۱۲ تا ۱۵",  "start": "12:00", "end": "15:00" },
  { "value": "15-18", "label": "۱۵ تا ۱۸",  "start": "15:00", "end": "18:00" },
  { "value": "18-21", "label": "۱۸ تا ۲۱",  "start": "18:00", "end": "21:00" }
]
```

### Cancel Reasons
بخش ۲.۴ بالا.

این‌ها در `/v1/customer/bootstrap` (Block 8) همه با هم برمی‌گردند تا فرانت در splash کش کند.

---

## ۵) چک‌لیست تأیید فرانت

پاسخ‌های ما به سؤالات صریح شما:

### ۱.۴ هدرها
- [x] `Idempotency-Key` ذخیره و چک می‌شود — ۲۴ ساعت — replay همان response قبلی
- [x] `X-Renewed-Token` پیاده شد — توکن جدید در نیمه‌ی دوم عمر
- [x] `X-Device-ID` فعلاً ignore می‌شود (در فاز بعد). چند دستگاه مجاز است.
- [x] `force_reauth_at` در `/v1/customer/status` می‌آید

### ۲.۳ Orders
- [x] جدول `status_int → status` در بخش ۳ کامل
- [x] تأیید: `scheduled_slot` enum `"09-12" | "12-15" | "15-18" | "18-21"`
- [x] تأیید: image upload در endpoint جدا `/orders/{id}/attachments`

### ۳ نظرسنجی
- [x] قرارداد کامل در بخش ۲.۷
- [x] enforcement: 409 با `pending_review_required` روی POST orders
- [x] معیارها ثابت (۴تایی)، نه admin-managed
- [x] حداکثر comment: ۱۰۰۰ کاراکتر

### ۴ فاکتور
- [x] قرارداد کامل در بخش ۲.۸
- [x] واحد پول: تومان (IRT) قطعی
- [x] PDF در Block 4
- [x] پرداخت آنلاین: Zibal در Block 4

---

## ۶) Roadmap اجرایی

| بلوک | محتوا | وضعیت |
|---|---|---|
| Foundation | ماژول CustomerApp، envelope، Idempotency، RollingToken، Status, Money, OrderStatusMapper | ✅ پیاده شد |
| Auth | OTP + verify + complete-profile + me + logout/logout-all | ✅ از قبل (Identity module) |
| Block 1 | Orders (list, create, show, cancel, version, attachments, invoice، cancel-reasons) | 🚧 turn بعدی |
| Block 2 | Addresses + Locations (states/cities) — احتمالاً جدول جدید | 🚧 |
| Block 3 | Order Reviews + enforcement middleware | 🚧 |
| Block 4 | Professional Invoice + PDF + Zibal payment | 🚧 |
| Block 5 | Profile PUT + Notifications + Holidays | 🚧 |
| Block 6 | Services (objections, types) + Catalog aliases | 🚧 |
| Block 7 | بهبود امنیت: X-Device-ID storage، rate limits بیشتر، CORS allow-list | 🚧 |
| Block 8 | `/v1/customer/bootstrap` تجمیعی + ETag/Cache | 🚧 |

---

## ۷) موارد نیازمند پاسخ شما (تیم فرانت)

تا قبل از شروع Block 1، این تصمیمات باید قطعی شوند:

1. **multi-address؟** آیا کاربر باید چند آدرس داشته باشد یا یک آدرس کافی است؟ (الان مدل ما تک‌آدرسی است)
2. **`services/types` چیست؟** نمونه payload بفرستید — هنوز معنای آن را نمی‌دانیم.
3. **`services/objections` منبع داده؟** static config، admin-managed در پنل، یا per-device از crm_devices؟
4. **`address` inline در POST /orders؟** ما توصیه می‌کنیم `address_id only` — اگر مخالف هستید بگویید.
5. **پرداخت آنلاین درون‌اپ**: درگاه = Zibal تأیید می‌شود؟ یا چیز دیگر؟
6. **PDF فاکتور**: شما خودتان از JSON بسازید کافی است یا حتماً PDF رسمی سرور می‌خواهید؟

---

## ۸) برای شروع همین الان

این بخش‌ها همین الان آماده و قابل استفاده‌اند برای قفل کردن typescript types شما:

```ts
// types/api.ts

export interface ApiSuccess<T> {
  success: true;
  data: T;
  message?: string;
  meta?: PaginationMeta;
}

export interface ApiError {
  success: false;
  message: string;
  code: ErrorCode;
  data?: {
    error_code: ErrorCode;
    errors?: Record<string, string[]>;
  };
}

export type ErrorCode =
  | 'unauthenticated' | 'forbidden' | 'not_found' | 'conflict'
  | 'validation_failed' | 'pending_review_required'
  | 'service_unavailable' | 'locked' | 'upgrade_required'
  | 'rate_limited' | 'server_error';

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface StatusPayload {
  app_disabled: boolean;
  min_version: string;
  latest_version: string;
  force_reauth_at: number;
  maintenance: { active: boolean; message: string | null };
  server_time: string;
  db: 'ok' | 'down';
}

export type OrderStatus =
  | 'pending' | 'assigned' | 'scheduled'
  | 'in_progress' | 'suspended'
  | 'completed' | 'cancelled' | 'declined';

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
```

---

با تشکر — تیم بک‌اند تعمیر آنلاین 🙏

برای هر بخش که سؤال یا نگرانی دارید، در همین thread پاسخ بدهید. تا قطعی‌شدن سؤالات بخش ۷، Block 1 شروع می‌شود ولی فقط روی موارد بدون ابهام (cancel-reasons، order list، version polling). موارد سؤال‌دار تا پاسخ شما در صف می‌مانند.
