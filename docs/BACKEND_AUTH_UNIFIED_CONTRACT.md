# قرارداد ورودِ یکپارچه (OTP) — برای تیم فرانت

> نسخه: 1.0 | پاسخ به گزارشِ «`/api/auth/send-otp` خطای ۴۰۴»

## خلاصه

سیستمِ ورودِ یکپارچه‌ی **مشتری** از قبل در بک‌اند وجود دارد و روی **`/v1/auth/*`** سرو می‌شود (ماژول Identity). همان توکن، هم برای **سایت (از طریق BFF)** و هم برای **اپ موبایل** استفاده می‌شود (auth subject مشترک: `Customer`).

علتِ `404` روی `https://stage.tamironline.com/api/auth/send-otp`:
`/api/auth/*` یک مسیرِ **BFF (Next.js)** است و باید به اندپوینتِ واقعیِ بک‌اند **`/v1/auth/*`** پراکسی شود. این مسیرِ BFF روی stage ساخته/دیپلوی نشده است. بک‌اند اندپوینت را دارد و سالم است.

- **Base URL بک‌اند:** `https://panel.tamironline.com`
- همه‌ی پاسخ‌ها JSON هستند. خطاهای اعتبارسنجی با `422` و ساختار استاندارد لاراول (`{ message, errors }`) برمی‌گردند.

---

## نگاشتِ پیشنهادیِ BFF → بک‌اند

| مسیر BFF (Next) | متد | اندپوینت بک‌اند | Auth |
|---|---|---|---|
| `/api/auth/send-otp` | POST | `/v1/auth/send-otp` | عمومی |
| `/api/auth/verify-otp` | POST | `/v1/auth/verify-otp` | عمومی |
| `/api/auth/me` | GET | `/v1/auth/me` | `Bearer <customer token>` |
| `/api/auth/complete-profile` | POST | `/v1/auth/complete-profile` | `Bearer <customer token>` |
| `/api/auth/logout` | POST | `/v1/auth/logout` | `Bearer <customer token>` |
| `/api/auth/logout-all` | POST | `/v1/auth/logout-all` | `Bearer <customer token>` |

> توکنِ مشتری از `verify-otp` می‌آید و یک Sanctum token است. برای اندپوینت‌های احراز‌هویت‌شده هدر `Authorization: Bearer <token>` بفرستید. (این با `INTERNAL_API_TOKEN` که برای `/v1/catalog/*` و `/v1/pages/*` استفاده می‌شود فرق دارد.)

---

## ۱) ارسال کد — `POST /v1/auth/send-otp`

درخواست:
```json
{ "mobile": "09123456789" }
```
پاسخ `200`:
```json
{
  "ok": true,
  "message": "کد تأیید ارسال شد.",
  "expires_in": 120,
  "can_resend_in": 60
}
```
خطاها: `422` شماره نامعتبر/سقفِ ارسال (`{ "message": "...", "errors": { "mobile": ["..."] } }`)، `429` در صورت عبور از throttle (`10/min`).

## ۲) تأیید کد — `POST /v1/auth/verify-otp`

درخواست:
```json
{ "mobile": "09123456789", "code": "12345" }
```
هدر اختیاری: `X-Device-ID` (اپ موبایل می‌فرستد؛ BFF لازم نیست).

پاسخ `200`:
```json
{
  "ok": true,
  "token": "<sanctum-plain-text-token>",
  "token_type": "Bearer",
  "customer": {
    "id": 12,
    "mobile": "09123456789",
    "first_name": null,
    "last_name": "هاشم‌پور",
    "full_name": "هاشم‌پور",
    "email": null,
    "avatar_url": null,
    "is_profile_complete": true,
    "mobile_verified_at": "2026-06-21T10:00:00+00:00",
    "subscription": 100012,
    "created_at": "..."
  },
  "is_new": false,
  "needs_profile": false
}
```
- **`needs_profile`** زمانی `true` است که **`last_name` خالی** باشد. (نام/`first_name` اختیاری است و در needs_profile دخیل نیست.)
- **`full_name`** فقط از نام/نام‌خانوادگی ساخته می‌شود و **هرگز شماره موبایل نیست**؛ اگر `first_name` نباشد، `full_name = last_name`.
- خطا: `422` کد نامعتبر (`errors.code`).

> اگر BFF نام فیلدها را camelCase می‌خواهد (`needsProfile`, `fullName`)، همان لایه‌ی BFF تبدیل کند؛ بک‌اند snake_case است تا قراردادِ اپ موبایل نشکند.

## ۳) تکمیل پروفایل — `POST /v1/auth/complete-profile`  (نیازمند توکن)

درخواست:
```json
{ "last_name": "هاشم‌پور" }
```
- **فقط `last_name` الزامی است** (حداقل ۲ کاراکتر).
- `first_name` اختیاری/nullable است؛ اگر بفرستید ذخیره می‌شود، اگر نه چیزی پاک نمی‌شود.

پاسخ `200`:
```json
{ "ok": true, "customer": { "...": "...", "full_name": "هاشم‌پور", "is_profile_complete": true } }
```
خطا: `422` (`errors.last_name`).

## ۴) اطلاعات کاربر — `GET /v1/auth/me`  (نیازمند توکن)
```json
{ "ok": true, "customer": { "...": "..." } }
```

## ۵) خروج — `POST /v1/auth/logout` و `POST /v1/auth/logout-all`  (نیازمند توکن)
```json
{ "ok": true, "message": "با موفقیت خارج شدید." }
```
- `logout` فقط توکنِ فعلی را باطل می‌کند؛ `logout-all` همه‌ی توکن‌های کاربر را.

---

## نکات

- **حریم خصوصیِ شماره:** نامِ نمایشی (`full_name`) و نامِ نمایشیِ انجمن **هرگز شماره موبایل نیست**. شماره محرمانه است.
- **انجمن:** برای ثبت سوال/پاسخ، کاربر باید `last_name` داشته باشد (وگرنه `422` با `needs_profile: true`).
- **Rate limit:** `send-otp`/`verify-otp` با `throttle:10,1` محدودند (per-IP). چون عمومی‌اند و کاربرِ نهایی مستقیم صدا می‌زند (نه BFF سرور-به-سرور) این مناسب است؛ اگر BFF آن‌ها را سرور-به-سرور پراکسی می‌کند و به سقف می‌خورید، اطلاع دهید تا مثل `catalog` معافشان کنیم.
