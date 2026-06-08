# مستند API صفحه «تماس با ما» — برای بک‌اند (Laravel)

> نسخه: 1.0 | تاریخ: 1404/03/01

---

## خلاصه کلی

فرانت‌اند Next.js پس از اعتبارسنجی اولیه فرم تماس، درخواست را به یک **API route داخلی** (`POST /api/contact`) می‌فرستد.
آن Route دوباره درخواست را به **بک‌اند Laravel** فوروارد می‌کند.

```
کاربر → Next.js /api/contact → Laravel POST /v1/contact-messages
```

---

## ۱. اندپوینت موردنیاز

| متد  | مسیر                   |
|------|------------------------|
| POST | `/v1/contact-messages` |

### احراز هویت

هدر `Authorization: Bearer <INTERNAL_API_TOKEN>` از سمت Next.js ارسال می‌شود.
این توکن در متغیر محیطی `INTERNAL_API_TOKEN` ست می‌شود.
بک‌اند باید این توکن را بررسی کند؛ درخواست‌های بدون توکن یا توکن نامعتبر باید **401** برگردانند.

---

## ۲. درخواست (Request)

### هدرها

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {INTERNAL_API_TOKEN}
```

### بدنه (JSON Body)

```json
{
  "fullName": "علی محمدی",
  "mobile": "09121234567",
  "email": "ali@example.com",
  "topic": "درخواست تعمیر",
  "message": "لپ‌تاپم روشن نمی‌شود. لطفاً راهنمایی کنید."
}
```

### توضیح فیلدها

| فیلد       | نوع     | الزامی | قوانین اعتبارسنجی                                          |
|------------|---------|--------|-------------------------------------------------------------|
| `fullName` | string  | بله    | حداقل ۲ کاراکتر، حداکثر ۸۰ کاراکتر، trim                 |
| `mobile`   | string  | بله    | ۱۱ رقم، شروع با ۰۹، مثال: `09121234567`                    |
| `email`    | string  | خیر    | فرمت ایمیل معتبر، حداکثر ۱۲۰ کاراکتر. اگر نفرستاده شود یا `null` باشد نادیده بگیر |
| `topic`    | string  | بله    | یکی از مقادیر enum زیر                                     |
| `message`  | string  | بله    | حداقل ۱۰ کاراکتر، حداکثر ۲۰۰۰ کاراکتر، trim              |

### مقادیر مجاز `topic` (Enum)

```
"درخواست تعمیر"
"پیگیری سفارش"
"شکایت یا پیشنهاد"
"همکاری تجاری"
"سایر موارد"
```

---

## ۳. پاسخ موفق (Success Response)

**HTTP Status: `201 Created`**

```json
{
  "id": "msg_abc123",
  "created_at": "2025-06-01T10:30:00.000000Z"
}
```

### توضیح فیلدهای پاسخ

| فیلد         | نوع    | توضیح                                           |
|--------------|--------|-------------------------------------------------|
| `id`         | string | شناسه یکتا پیام ذخیره‌شده (UUID یا هر فرمت دیگر) |
| `created_at` | string | زمان ثبت به فرمت ISO 8601 UTC                   |

---

## ۴. پاسخ‌های خطا (Error Responses)

### ۴.۱ خطای اعتبارسنجی (Validation Error)

**HTTP Status: `422 Unprocessable Entity`**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "fullName": ["نام باید حداقل ۲ کاراکتر باشد."],
    "mobile": ["شماره موبایل معتبر نیست."],
    "topic": ["موضوع انتخاب‌شده معتبر نیست."]
  }
}
```

> **مهم:** کلیدهای `errors` باید دقیقاً همان نام فیلدهای ورودی باشند (`fullName`, `mobile`, `email`, `topic`, `message`).
> فرانت‌اند از این کلیدها برای نمایش خطا زیر هر فیلد استفاده می‌کند.

### ۴.۲ عدم احراز هویت

**HTTP Status: `401 Unauthorized`**

```json
{
  "message": "Unauthorized"
}
```

### ۴.۳ Rate Limit بک‌اند

**HTTP Status: `429 Too Many Requests`**

```json
{
  "message": "Too many requests"
}
```

### ۴.۴ خطای داخلی سرور

**HTTP Status: `500 Internal Server Error`**

```json
{
  "message": "Server error"
}
```

---

## ۵. Rate Limiting

- فرانت‌اند خودش هم **۱۰ درخواست در ۶۰ ثانیه** به ازای هر IP محدود می‌کند.
- توصیه می‌شود بک‌اند هم یک لایه Rate Limit مستقل داشته باشد (مثلاً ۲۰ درخواست در دقیقه به ازای هر IP یا توکن).

---

## ۶. ذخیره‌سازی داده

پیشنهاد ساختار جدول `contact_messages` در دیتابیس:

```sql
CREATE TABLE contact_messages (
  id          CHAR(36)      PRIMARY KEY,          -- UUID
  full_name   VARCHAR(80)   NOT NULL,
  mobile      VARCHAR(20)   NOT NULL,
  email       VARCHAR(120)  NULL,
  topic       VARCHAR(50)   NOT NULL,             -- enum مقادیر بالا
  message     TEXT          NOT NULL,
  ip_address  VARCHAR(45)   NULL,                 -- برای لاگ و Rate Limit
  created_at  TIMESTAMP     DEFAULT NOW(),
  updated_at  TIMESTAMP     DEFAULT NOW() ON UPDATE NOW()
);
```

---

## ۷. پیشنهادهای اضافه

| موضوع              | توضیح                                                                              |
|--------------------|------------------------------------------------------------------------------------|
| ایمیل اطلاع‌رسانی | پس از ثبت، یک ایمیل یا نوتیفیکیشن به مدیران سایت ارسال شود.                      |
| موبایل نرمال‌سازی  | شماره موبایل را به فرمت `09XXXXXXXXX` ذخیره کنید (اعداد فارسی را تبدیل کنید).    |
| ایمیل تأیید        | اگر کاربر ایمیل داده، یک ایمیل تأیید دریافت پیام به او بفرستید.                  |
| پانل ادمین         | جدول `contact_messages` باید در پانل ادمین قابل مشاهده و جستجو باشد.             |
| پیوند به سفارش     | اگر `topic == "پیگیری سفارش"`، امکان لینک دستی به سفارش مرتبط مفید است.           |

---

## ۸. تست با curl

```bash
curl -X POST https://api.tamiranline.ir/v1/contact-messages \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_INTERNAL_TOKEN" \
  -d '{
    "fullName": "علی محمدی",
    "mobile": "09121234567",
    "email": "ali@example.com",
    "topic": "درخواست تعمیر",
    "message": "لپ تاپم خاموش نمی شود لطفا کمک کنید"
  }'
```

**پاسخ موفق انتظاری:**

```json
{
  "id": "9c4e2b1a-...",
  "created_at": "2025-06-01T10:30:00.000000Z"
}
```

---

## ۹. خلاصه سریع برای بک‌اند‌کار

```
اندپوینت:   POST /v1/contact-messages
احراز هویت: Bearer Token (سرور به سرور)
ورودی:      fullName, mobile, email?, topic, message
خروجی 201:  { id, created_at }
خطا 422:    { message, errors: { fieldName: [errorMsg] } }
```

---

## ۱۰) وضعیت پیاده‌سازی (پر شده توسط تیم بک‌اند)

| § | مورد | وضعیت | محل پیاده‌سازی |
|---|---|---|---|
| ۱ | `POST /v1/contact-messages` | ✅ | `Modules/Site/Routes/api.php` |
| ۱ | Bearer auth + 401 | ✅ | `app/Http/Middleware/VerifyInternalToken.php` (با `hash_equals` — timing-safe) |
| ۲ | همه‌ی فیلدها و قواعد | ✅ | `Modules/Site/Http/Requests/Api/V1/StoreContactMessageRequest.php` |
| ۲ | trim و نرمالایز موبایل به `09xxxxxxxxx` | ✅ | `validatedDto()` + `normaliseMobile()` |
| ۲ | enum topic با ۵ مقدار | ✅ | `Rule::in([...])` |
| ۳ | `201` با `{id, created_at}` ISO 8601 UTC | ✅ | `ContactMessageController::store()` با `toIso8601ZuluString()` |
| ۴.۱ | `422` با `errors` کلیددار به نام فیلد | ✅ | Laravel ValidationException default |
| ۴.۲ | `401 {"message": "Unauthorized"}` | ✅ | `VerifyInternalToken` |
| ۴.۳ | `429` با header `Retry-After` | ✅ | Laravel ThrottleRequests default |
| ۴.۴ | `500 {"message": "Server error"}` در production | ✅ | `bootstrap/app.php` exception handler |
| ۵ | Rate limit ۱۰/دقیقه | ✅ | `throttle:10,1` در route |
| ۶ | جدول `contact_messages` | ✅ | migration `2026_05_19_002` (با ULID به‌جای UUID) |
| ۷ | پانل ادمین | ✅ | `/admin/site/contact-messages` (index، show، تغییر وضعیت، حذف) |
| ۷ | نرمالایز موبایل (فرمت) | ✅ | `normaliseMobile()` |
| ۷ | تبدیل اعداد فارسی موبایل | ⏸️ | پیاده نشده — در روادمپ |
| ۷ | ایمیل اطلاع‌رسانی به مدیر | ⏸️ | پیاده نشده — در روادمپ |
| ۷ | ایمیل تأیید به کاربر | ⏸️ | پیاده نشده — در روادمپ |
| ۷ | پیوند به سفارش | ⏸️ | پیاده نشده — در روادمپ |

### تفاوت‌های جزئی با اسپک

1. **شناسه (`id`)** — به‌جای UUID از **ULID** استفاده شده (string، مرتب‌پذیر، URL-safe). فرمت پاسخ همچنان `string` است و فرانت تفاوتی احساس نمی‌کند.
2. **فرمت `created_at`** — `toIso8601ZuluString()` فعلاً بدون میکروثانیه است (`2026-05-19T10:34:00Z` به‌جای `.000000Z`). هر دو ISO 8601 معتبر هستند.

اگر مطابقت دقیق با اسپک لازم باشد (UUID یا میکروثانیه)، تغییر کوچک است.
