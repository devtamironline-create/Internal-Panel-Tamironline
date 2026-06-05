# مستند فرانت — سیستم یکپارچه‌ی ورود (Identity / Phone + OTP + Sanctum)

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۲
> سیستم احراز هویت یکپارچه برای سایت Next.js، اپلیکیشن موبایل، و هر client دیگر.
> همه‌ی کاربران از این endpoint وارد می‌شوند — مشتری، تکنسین، اپلیکیشن.
> Token از نوع Sanctum (Bearer)، اعتبار **۳۰ روز** از آخرین استفاده.

> **توجه:** پنل ادمین Blade و پنل تکنسین Blade فعلی **بدون تغییر** ادامه دارند
> (همان session-based). این سیستم برای کلاینت‌های API است.

---

## ۱) جریان کلی

```
کاربر شماره موبایل وارد می‌کند
  └─→ POST /api/v1/auth/send-otp { mobile }                   (public)
        ← { ok, expires_in: 120, can_resend_in: 60 }
کاربر کد ۶ رقمی دریافتی از SMS را وارد می‌کند
  └─→ POST /api/v1/auth/verify-otp { mobile, code }           (public)
        ← { token, user, is_new, needs_profile }
        💾 توکن را در storage ذخیره کنید (httpOnly cookie یا secure storage)
اگر needs_profile === true:
  └─→ POST /api/v1/auth/complete-profile { first_name, last_name? }   (Bearer)
        ← { user }
کاربر حالا کاملاً وارد شده — همه‌ی request های بعدی با Authorization: Bearer {token}
```

---

## ۲) Endpoints

| Endpoint | Method | Auth | Throttle | کاربرد |
|---|---|---|---|---|
| `/api/v1/auth/send-otp` | POST | عمومی | 10/min IP + 5/hour per phone | ارسال کد به موبایل |
| `/api/v1/auth/verify-otp` | POST | عمومی | 10/min IP | تأیید کد، گرفتن token |
| `/api/v1/auth/complete-profile` | POST | Bearer | — | ست کردن نام (یک‌بار یا تغییر) |
| `/api/v1/auth/me` | GET | Bearer | — | اطلاعات کاربر فعلی |
| `/api/v1/auth/logout` | POST | Bearer | — | خروج از این دستگاه |
| `/api/v1/auth/logout-all` | POST | Bearer | — | خروج از همه دستگاه‌ها |

---

## ۳) شکل پاسخ‌ها

### `POST /send-otp`

**درخواست:**
```jsonc
{ "mobile": "09123456789" }
```
فرمت‌های پذیرفته‌شده: `09xxxxxxxxx`، `+989xxxxxxxxx`، `00989xxxxxxxxx`، `989xxxxxxxxx`. ارقام فا/عربی هم پذیرفته می‌شوند.

**پاسخ 200:**
```jsonc
{
  "ok": true,
  "message": "کد تأیید ارسال شد.",
  "expires_in": 120,         // ثانیه — کد تا کِی معتبر است
  "can_resend_in": 60        // ثانیه — کِی می‌توان دوباره درخواست داد
}
```

**پاسخ 422 (شماره نامعتبر یا rate limit):**
```jsonc
{
  "message": "شماره موبایل نامعتبر است.",
  "errors": { "mobile": ["شماره موبایل نامعتبر است."] }
}
```

### `POST /verify-otp`

**درخواست:**
```jsonc
{ "mobile": "09123456789", "code": "123456" }
```

**پاسخ 200:**
```jsonc
{
  "ok": true,
  "token": "1|abcdef0123456789...",
  "token_type": "Bearer",
  "user": {
    "id": 42,
    "mobile": "09123456789",
    "first_name": null,             // null = هنوز نام ست نشده
    "last_name": null,
    "full_name": "کاربر",            // یا "نام نام‌خانوادگی" اگر پر شده
    "email": null,
    "avatar_url": null,
    "is_profile_complete": false,
    "mobile_verified_at": "2026-06-05T13:00:00+00:00",
    "roles": ["customer"],
    "created_at": "2026-06-05T13:00:00+00:00"
  },
  "is_new": true,                   // اولین بار وارد می‌شود؟
  "needs_profile": true             // باید نام بپرسد؟
}
```

**پاسخ 422 (کد اشتباه):**
```jsonc
{ "message": "کد تأیید نامعتبر است.", "errors": { "code": ["..."] } }
```

### `POST /complete-profile` (Bearer)

**درخواست:**
```jsonc
{ "first_name": "علی", "last_name": "محمدی" }
```

**پاسخ 200:**
```jsonc
{
  "ok": true,
  "user": { ... }   // همان شکل user که `is_profile_complete: true` دارد
}
```

### `GET /me` (Bearer)

پاسخ 200: `{ "ok": true, "user": { ... } }`

### `POST /logout` (Bearer)
فقط توکن این دستگاه را revoke می‌کند. → `{ "ok": true }`

### `POST /logout-all` (Bearer)
همه‌ی توکن‌های این کاربر را revoke می‌کند → کاربر از همه دستگاه‌ها خارج می‌شود.

---

## ۴) نمونه‌ی Next.js / React

```ts
// lib/identity.ts
const API = process.env.NEXT_PUBLIC_API_URL!;
const TOKEN_KEY = 'identity_token';

export async function sendOtp(mobile: string) {
  const res = await fetch(`${API}/api/v1/auth/send-otp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ mobile }),
  });
  if (!res.ok) throw new Error((await res.json()).message ?? 'failed');
  return res.json();
}

export async function verifyOtp(mobile: string, code: string) {
  const res = await fetch(`${API}/api/v1/auth/verify-otp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ mobile, code }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.message ?? 'failed');
  // ذخیره‌ی توکن — برای Next.js توصیه: httpOnly cookie از طریق route handler
  localStorage.setItem(TOKEN_KEY, data.token);
  return data;
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export async function api(path: string, init: RequestInit = {}) {
  const token = getToken();
  return fetch(`${API}${path}`, {
    ...init,
    headers: {
      ...(init.headers || {}),
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
}

export async function completeProfile(first: string, last?: string) {
  const res = await api('/api/v1/auth/complete-profile', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ first_name: first, last_name: last }),
  });
  if (!res.ok) throw new Error((await res.json()).message);
  return res.json();
}

export async function logout() {
  await api('/api/v1/auth/logout', { method: 'POST' });
  localStorage.removeItem(TOKEN_KEY);
}
```

> **نکته‌ی امنیتی:** برای SSR Next.js استفاده از **httpOnly cookie** به‌جای localStorage توصیه می‌شود.
> یک route handler می‌سازید (`app/api/auth/login/route.ts`) که POST می‌گیرد، توکن را در cookie ذخیره می‌کند،
> و در requestهای بعدی از همان cookie می‌خواند.

---

## ۵) Forum + کامنت‌ها: حالا auth الزامی است

**تغییر مهم — breaking change:**

این endpoint ها از این پس **Bearer token** می‌خواهند:
- `POST /v1/forum/questions`
- `POST /v1/forum/questions/{slug}/answers`
- `POST /v1/blog/articles/{slug}/comments`

رفتار:
- بدون token → `401`
- کاربر بدون `first_name` → `422` با `needs_profile: true`
- فیلدهای `author_phone` / `author_email` / `author_name` از body **حذف شدند** — همه از حساب کاربر می‌آیند

### Forum question

```jsonc
// POST /v1/forum/questions    Authorization: Bearer ...
{
  "title": "...",                    // required
  "body": "...",                     // required
  "device_slug": "washing-machine",  // ← اختیاری
  "brand_slug": "samsung",           // ← اختیاری
  "model": "WW80...",                // اختیاری
  "tags": ["leak", "water"]          // اختیاری
}
```

> **تغییر اخیر:** `device_slug` و `brand_slug` هر دو **اختیاری** شدند تا کاربر بتواند سوال عمومی هم بپرسد. اگر admin لازم بداند، می‌تواند بعداً سوال را به برند/دستگاه assign کند (bulk categorize).

### Forum answer

```jsonc
// POST /v1/forum/questions/{slug}/answers    Authorization: Bearer ...
{
  "body": "..."   // required (min 30 char)
}
```

### Comments (مقالات بلاگ)

```jsonc
// POST /v1/blog/articles/{slug}/comments    Authorization: Bearer ...
{
  "content": "...",         // required (min 5 char)
  "parent_id": 123          // اختیاری — برای reply به کامنت approved موجود
}
```

پاسخ 422 با `needs_profile`:
```jsonc
{ "ok": false, "message": "ابتدا نام خود را در پروفایل تکمیل کنید.", "needs_profile": true }
```

---

## ۶) رفتار توکن

- **اعتبار**: ۳۰ روز از آخرین استفاده (configurable via `SANCTUM_TOKEN_EXPIRATION` در env)
- **revoke**: با logout / logout-all / یا اگر admin بخواهد
- **انواع scope**: فعلاً همه `['*']` — کنترل granular در آینده

---

## ۷) Migration برای کاربران موجود

اگر کاربر قبلاً به‌صورت admin/staff/technician در `users` رکورد داشت، می‌تواند با همان شماره موبایل از این سیستم هم وارد شود — هیچ duplicate ساخته نمی‌شود (در `verifyOtp` بر اساس `mobile` query می‌کنیم).

**سناریوها:**
- کاربر جدید: ثبت‌نام خودکار، `is_new: true`، `needs_profile: true`
- کاربر existing بدون نام: `is_new: false`، `needs_profile: true`
- کاربر existing با نام: `is_new: false`، `needs_profile: false`

---

## ۸) Checklist مهاجرت فرانت

- [ ] تعریف type های پاسخ (نمونه‌ی بالا).
- [ ] صفحه‌ی `/login` با ۲ گام: phone → OTP.
- [ ] صفحه/modal بعد از login برای پرسیدن نام اگر `needs_profile`.
- [ ] ذخیره‌ی token در httpOnly cookie یا secure storage.
- [ ] تمام درخواست‌های احتیاج به auth → `Authorization: Bearer {token}`.
- [ ] مدیریت 401 → پاکسازی token و redirect به login.
- [ ] مدیریت 422 `needs_profile` → redirect به فرم تکمیل نام.
- [ ] forum: حذف `author_*` از فرم سوال/پاسخ. فقط title/body فرستاده شود (device_slug/brand_slug اختیاری).
- [ ] comments: حذف `author_name` و `author_email` از فرم. فقط content + parent_id فرستاده شود.
- [ ] دکمه‌ی logout (در پروفایل کاربر).
- [ ] صفحه‌ی «دستگاه‌های فعال من» با لیست توکن‌ها (اختیاری، فاز بعد).

---

پایان.
