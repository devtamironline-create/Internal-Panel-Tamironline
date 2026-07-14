# API اپلیکیشن تکنسین — راهنمای پیاده‌سازی برای تیمِ فرانت

مرجعِ کاملِ API اپِ تکنسین (Next.js/PWA). Auth subject: **Technician** (توکنِ
Sanctum). این API جدا از API مشتری (`/v1/customer/*`) است.

> همهٔ مسیرها زیرِ `/v1/technician/*` هستند. اگر از BFFِ Next استفاده می‌کنید،
> معمولاً از پیشوندی مثلِ `/api/bff` پراکسی می‌شوند (مثلِ اپِ مشتری) → مقصدِ
> بک‌اند `/v1/technician/...`.

---

## ۱) قراردادهای عمومی

- **Base URL:** `NEXT_PUBLIC_API_BASE_URL` (مثلاً `https://panel.tamironline.com`).
- **فرمتِ پاسخ:** همیشه JSON. موفق: `{ "success": true, "data": ..., ["meta"|"stats"|"message"] }`. خطا: `{ "success": false, "message": "...", ["errors": {field: [..]}] }`.
- **احراز:** روی همهٔ مسیرهای خصوصی هدرِ `Authorization: Bearer <token>`.
- **تمدیدِ توکن (session کشویی):** هر پاسخِ خصوصیِ موفق ممکن است هدرِ
  **`X-Renewed-Token`** داشته باشد. اگر بود، توکنِ ذخیره‌شده را **جایگزین** کنید.
- **دستگاه:** هدرِ `X-Device-ID` (یک UUID پایدارِ ذخیره‌شده در دستگاه) روی همهٔ
  درخواست‌ها بفرستید. در `verify-otp` برای tag کردنِ توکن استفاده می‌شود.
- **کدهای خطا:**
  - `401` → توکن نامعتبر/منقضی → **logout** و صفحهٔ ورود.
  - `403` → غیرتکنسین/عدمِ مالکیتِ سفارش → پیام، **logout نکنید**.
  - `422` → خطای اعتبارسنجی؛ `errors` را زیرِ فیلدها نشان دهید.
  - `429` → throttle (OTP/شارژ)؛ کمی صبر و retry.
  - `5xx`/`403 گذرا` → پیام دوستانه + اجازهٔ retry؛ **logout نکنید**.
- **تاریخ‌ها:** ISO-8601 UTC (`2026-07-13T08:30:00+00:00`) — سمتِ اپ به شمسی/محلی.
- **مبالغ:** تومان (integer).
- **تلفن‌ها:** روی سفارشِ نهایی (`is_final=true`) شماره‌ها **ماسک‌شده** می‌آیند و
  `contact_locked=true` — دکمهٔ تماس را غیرفعال کنید.

---

## ۲) احراز هویت

### `POST /v1/technician/auth/send-otp`  (public, throttle per mobile)
Body: `{ "mobile": "09121112233" }`
Res: `{ "success": true, "message": "...", "data": { "expires_in": 120, "can_resend_in": 60 } }`
خطاها: شماره متعلق به تکنسینِ فعال نباشد → `422 { errors: { mobile: [...] } }`.

### `POST /v1/technician/auth/verify-otp`  (public, throttle per mobile)
Body: `{ "mobile": "09121112233", "code": "123456" }` + هدرِ `X-Device-ID`.
Res:
```jsonc
{ "success": true, "data": {
  "token": "12|abcdef...",        // Bearer — ذخیره کنید
  "token_type": "Bearer",
  "technician": { /* شکلِ Technician (پایین) */ }
}}
```

### `GET /v1/technician/me`  (auth)
Res: `{ "success": true, "data": { "technician": {...} } }`

**شکلِ Technician:**
```jsonc
{
  "id": 460, "name": "مسعود حسینی", "mobile": "09914454673",
  "avatar_url": "https://.../x.webp" | null,
  "status": "active", "is_ready_for_delivery": true,
  "training": { "completed": true, "watched": 12, "total": 12, "remaining": 0, "percent": 100 },
  "created_at": "..."
}
```

### `POST /v1/technician/auth/logout`  (auth)
توکنِ فعلی را باطل می‌کند. Res: `{ success, message }`.

---

## ۳) سفارش‌ها

### `GET /v1/technician/orders?status=&q=&page=`  (auth)
- `status` (اختیاری): یکی از مقادیرِ وضعیت (پایین). `q`: جست‌وجو. `page`: صفحه.
- Res: `data` (آرایهٔ سفارش‌های فشرده) + `meta` (صفحه‌بندی) + `stats`.
```jsonc
{
  "success": true,
  "data": [{
    "id": 78904, "order_code": "ORD-2607-01330",
    "customer_name": "محسن هاشم‌پور",
    "status": "open", "status_label": "باز شده",
    "status_badge": "bg-...", "status_group": "in_progress",
    "is_final": false, "is_returned": false,
    "device_name": "لباسشویی", "brand_name": "سامسونگ",
    "scheduled_at": "...", "created_at": "..."
  }],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 },
  "stats": { "total": 42, "coordinated": 5, "open": 8, "completed": 20 }
}
```

### `GET /v1/technician/orders/{id}`  (auth, فقط سفارشِ خودِ تکنسین)
پاسخِ `data` شاملِ همهٔ بخش‌های صفحهٔ سفارش:
```jsonc
{ "success": true, "data": {
  "id": 78904, "order_code": "ORD-2607-01330", "order_type": "repair", "subscription": null,
  "status": "open", "status_label": "باز شده", "status_badge": "...", "status_group": "in_progress",
  "is_final": false, "is_returned": false, "return_type": null,
  "scheduled_at": "...", "scheduled_date": "2026-07-13",
  "customer": { "name": "...", "mobile": "0912...", "phone": null, "contact_locked": false },
  "address": { "full_address": "...", "province": "تهران", "city": "تهران", "district": "منطقه 11",
               "postal_code": null, "latitude": 35.7, "longitude": 51.4, "has_coordinates": true },
  "device": { "name": "لباسشویی", "brand": "سامسونگ" },
  "problem": { "title": "...", "description": "..." },
  "tech_notes": [{ "key": "description_tech2", "label": "...", "value": "..." }],
  "my_notes": [{ "content": "...", "date": "2026-07-13 11:31:00" }],
  "financial": { "price_customer": 0, "cost_price": 0, "total_invoice": 0, "hire": 0,
                 "transportation": 0, "discount": 0, "final_price": 0,
                 "invoice_description": null, "pieces": [{ "title": "...", "buy_price": 0, "customer_price": 0 }] },
  "device_image_url": null,
  "allowed_transitions": [{ "value": "completed", "label": "انجام کار", "action_label": "پایان سفارش", "badge": "..." }],
  "status_history": [{ "from_label": "جدید", "to_label": "باز شده", "to_status": "open", "badge": "...", "note": "...", "created_at": "..." }],
  "proformas": [{ "id": 1, "code": "PF-...", "status": "sent", "total": 500000, "public_url": "...", "created_at": "..." }],
  "transfer_receipts": [{ "code": "TR-2607-00042", "description": "...", "print_url": "https://.../crm/transfer-receipt/<token>", "created_at": "..." }],
  "return_logs": [],
  "created_at": "...", "updated_at": "..."
}}
```
> `allowed_transitions` = رادیوهای مجازِ تغییرِ وضعیت (سرور محاسبه کرده). فقط
> همین‌ها را نشان دهید. `status_history` عمداً بدونِ نامِ تغییردهنده است.

### مقادیرِ وضعیت (`status`)
`new, coordinated, open, suspended, completed, cancelled, transit, declined,`
`repair_started, awaiting_part` و … . برچسب/بَج/گروه از خودِ پاسخ می‌آید — hard-code نکنید.

---

## ۴) اکشن‌های سفارش (نوشتن)

### `POST /v1/technician/orders/{id}/status`  (auth)
تغییرِ وضعیت. `Content-Type`: برای «پایان سفارش» **multipart/form-data** (عکس)، وگرنه JSON.
- فیلدِ مشترک: `status` (از `allowed_transitions`)، `description`.
  - `description` برای `coordinated|suspended|declined|transit` **اجباری** (حداقل ۱۵ کاراکتر)؛ برای `open` **اختیاری** (رسیدِ انتقال خودکار ساخته می‌شود).
- **بلاکِ فاکتور — فقط وقتی `status=completed`:**
  - `price_customer` (تومان)، `pieces[]` (`{title, buy_price, customer_price}`),
    `invoice_descripotion`، `device_img1` (فایلِ عکس، اجباری مگر برگشتی/پیش‌نویس یا عکسِ قبلی)،
    `save_as_draft` (`0|1`). `hire/transportation/discount` اختیاری.
  - قاعده: `price_customer ≥ sum(buy_price)`؛ سرور `total_invoice` را خودش حساب می‌کند.
- Res: `{ success, message, data: <جزئیاتِ کاملِ سفارش> }` (همان شکلِ show — UI را رفرش کنید).
- عوارضِ خودکارِ سرور: هنگام `completed` فاکتور صادر می‌شود؛ هنگام `open` رسیدِ انتقال ساخته و پیامک می‌شود؛ پیامکِ وضعیت به مشتری می‌رود.

### `POST /v1/technician/orders/{id}/schedule-visit`  (auth)
Body: `{ "visit_date": "2026-07-15", "visit_slot": 1..4 }` یا `{ "clear": true }` برای پاک‌کردن.
اگر سفارش `new` بود، خودکار `coordinated` و پیامکِ هماهنگی می‌رود. Res: `{ success, message }`.

### `POST /v1/technician/orders/{id}/notes`  (auth)
Body: `{ "note": "..." }`. روی سفارشِ نهایی مجاز نیست (`422`). Res: `{ success, message }`.

### `POST /v1/technician/orders/{id}/deliver-sms`  (auth)
پیامکِ «آماده تحویل» به مشتری. فقط اگر تکنسین `is_ready_for_delivery` و سفارش `completed`. Res: `{ success, message }`.

### `POST /v1/technician/orders/{id}/transfer-receipt`  (auth)
ثبتِ **دستیِ** رسیدِ انتقال (علاوه بر ساختِ خودکار هنگامِ `open`). فقط وقتی قابلیت
فعال و سفارش در `open|repair_started` است. Body: `{ "description": "..." }` (اختیاری).
Res: `{ success, message, data: { code, description, print_url, created_at } }`.
> `print_url` = نمای رسمیِ HTMLِ رسید (مهر/امضا، فونتِ وزیر) — دکمهٔ «دانلود PDF» به همین وصل شود.

---

## ۵) داشبورد

### `GET /v1/technician/dashboard`  (auth)
تقویمِ ۷ روزِ پیشِ‌رو با بازه‌های ساعتی (۹–۱۲، ۱۲–۱۵، ۱۵–۱۸، ۱۸–۲۱).
```jsonc
{ "success": true, "data": {
  "days": [{ "date": "2026-07-13", "count": 3,
    "slots": [{ "key": 1, "label": "۹ تا ۱۲", "orders": [{ id, order_code, customer_name, status, status_label, scheduled_at }] }],
    "off_slot": [...], "unscheduled": [...] }],
  "invoice_debt": 1200000
}}
```

---

## ۶) کیف‌پول

### `GET /v1/technician/wallet?type=&page=`  (auth)
`type` (اختیاری): `commission|reward|penalty|wallet_charge|...`.
Res: `data` (تراکنش‌ها) + `meta` + `stats` (مجموع‌ها) + `invoice_debt`.
```jsonc
{ "data": [{ "id": 1, "type": "commission", "type_label": "کمیسیون", "amount": 300000,
             "description": "...", "order_code": "ORD-...", "created_at": "..." }],
  "stats": { "commission_sum": ..., "reward_sum": ..., "penalty_sum": ..., "charge_sum": ... },
  "invoice_debt": 1200000, "meta": {...} }
```

### `POST /v1/technician/wallet/recharge`  (auth, throttle 20/min)
Body: `{ "amount": 500000 }` (تومان، حداقل ۵۰۰٬۰۰۰).
- Zibal: `{ "data": { "gateway": "zibal", "method": "GET", "payment_url": "https://..." } }` → همان URL را باز کنید.
- ملت: `{ "data": { "gateway": "mellat", "method": "POST", "start_pay_url": "...", "ref_id": "..." } }` → فرمِ POST با `RefId=ref_id` به `start_pay_url` بسازید/submit کنید.
- بازگشت از درگاه به callbackِ وبِ موجود می‌رود (اپ فقط شروع می‌کند).

---

## ۷) فاکتورها

### `GET /v1/technician/invoices?status=&page=`  (auth)
`status`: `draft|issued|paid|cancelled`.
Res: `data` (فاکتورها: `invoice_code, status, total_amount, tech_share, company_share, order_code, customer_name, issued_at`) + `meta` + `stats` (`count, total_sum, tech_share, company_share`).

---

## ۸) پیش‌فاکتور  (پشتِ فلگ — اگر غیرفعال، `404`)

- `GET /v1/technician/proformas?page=` → لیستِ پیش‌فاکتورهای خودِ تکنسین (`data` با شکلِ کاملِ ProformaService + `meta`).
- `POST /v1/technician/proformas` → ساخت. Body: `{ order_id?, customer_name?, customer_mobile?, device_name?, brand_name?, description?, valid_until?, items: [{ title, quantity?, unit_price? }] }`. تخفیف سمتِ تکنسین صفر است. با ساخت «صادر» می‌شود و در اپِ مشتری دیده می‌شود (بدونِ SMS). Res: `{ success, message, data: <proforma> }`.
- `GET /v1/technician/proformas/{id}` → جزئیات (فقط مالِ خودِ تکنسین).
- `POST /v1/technician/proformas/{id}/finalize` → «تأیید مشتری» + `data: { order_id, suggested_price, proforma_code }`؛ سپس سفارش را با `suggested_price` در فرمِ «پایان سفارش» تکمیل کنید تا فاکتورِ نهایی صادر شود.

---

## ۹) آموزش  (گِیتِ فعال‌سازیِ پنل)

- `GET /v1/technician/training` → `{ categories: [{ id, name, description, videos_count }], uncategorized_count, progress: { watched, total, remaining, percent }, training_completed }`.
- `GET /v1/technician/training/categories/{id}` → `{ category, videos: [...] }`.
- `GET /v1/technician/training/uncategorized` → ویدیوهای بدونِ دسته.
- `GET /v1/technician/training/videos/{id}` → جزئیاتِ ویدیو (+`description`).
- `POST /v1/technician/training/videos/{id}/watched` → علامتِ دیده‌شد؛ Res `{ progress, training_completed }`.
- شکلِ ویدیو: `{ id, title, provider: "aparat|youtube|vimeo|mp4|unknown", video_url, playback_url, thumbnail_url, duration_seconds, watched }`.
> اگر `training_completed=false`، طبقِ سیاست ممکن است تا اتمامِ آموزش برخی
> بخش‌ها گِیت شوند؛ UI می‌تواند «X ویدیوی باقی‌مانده» را نشان دهد.

---

## ۱۰) پروفایل

- خواندنی: از `GET /me`.
- `POST /v1/technician/profile/avatar` (multipart، `avatar` تصویر ≤ ۳MB) — **فقط یک‌بار**؛ بعد از آن `422`. Res شاملِ Technicianِ به‌روز.
- `POST /v1/technician/profile/password` — Body: `{ current_password, password, password_confirmation }` (حداقل ۶). Res `{ success, message }`.
- ویرایشِ اطلاعاتِ تماس از اپ **مجاز نیست** (مثلِ پنل).

---

## ۱۱) نکاتِ پیاده‌سازیِ فرانت
- یک axios/fetch wrapper: هدرهای `Authorization` + `X-Device-ID`، خواندنِ `X-Renewed-Token` از هر پاسخ، مدیریتِ ۴۰۱/۴۰۳/۴۲۲/۴۲۹.
- TanStack Query برای cache/invalidation؛ بعد از هر اکشنِ سفارش، `orders` و `orders/{id}` را invalidate کنید (پاسخِ status خودش جزئیاتِ تازه می‌دهد).
- فرم‌های مالی: محاسبهٔ زنده سمتِ کلاینت، اما مقدارِ نهایی را از پاسخِ سرور نشان دهید.
- تاریخِ شمسی سمتِ فرانت؛ اعداد فارسی؛ RTL؛ فونتِ Vazirmatn.
