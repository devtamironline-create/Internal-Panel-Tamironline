# پاسخِ بک‌اند به نامهٔ ممیزی امنیت/کارایی/UXِ اپ

**در پاسخ به:** نامهٔ تیم فرانت (PWA) — ۱۴۰۵/۰۴/۰۸
**تاریخ پاسخ:** ۱۴۰۵/۰۴/۱۴

خلاصهٔ وضعیت هر مورد:

| # | مورد | وضعیت |
|---|------|-------|
| ۱ | مهاجرت auth به کوکی HttpOnly | 🟡 آماده برای جلسه — نیازی به تغییرِ قرارداد نیست (پاسخ زیر) |
| ۲ | فلگ `transient` برای آدرس‌های یک‌بارمصرف | ✅ پیاده شد |
| ۳ | پارامتر `status` روی `GET /orders` | ✅ از قبل موجود بود (مقادیر زیر) |
| ۴ | ذخیرهٔ `attribution` روی سفارش | ✅ پیاده شد |
| ۵ | سیاست `X-Renewed-Token` | ✅ تأیید + حالا فقط روی پاسخِ ۲xx |
| ۶ | موارد باز قبلی | ✅ همه بسته/تأیید شد |

> ⚠️ برای اعمالِ مواردِ ۲ و ۴ روی سرور: `php artisan migrate` (php 8.4).

---

## ۱) 🔴 مهاجرت توکن به کوکی HttpOnly

**سمتِ بک‌اند تغییری لازم ندارد.** Sanctum از قبل توکنِ Bearer صادر می‌کند؛
BFF می‌تواند آن را در کوکیِ `HttpOnly; Secure; SameSite=Lax` بگذارد و در هر
درخواست به `Authorization: Bearer` تبدیل کند. پاسخ به دو سؤالِ شما:

- **(الف) طول عمر و سیاستِ renewal توکن:**
  - طول عمر: `SANCTUM_TOKEN_EXPIRATION` = **۳۰ روز** (`config/sanctum.php`، پیش‌فرض `43200` دقیقه).
  - renewal: میان‌افزارِ `RollingToken` روی هر درخواستِ احرازشده، اگر بیش از
    **۵۰٪** عمرِ توکن گذشته باشد (~۱۵ روز)، یک توکنِ تازه می‌سازد و در هدرِ
    `X-Renewed-Token` می‌گذارد. توکنِ قدیمی تا انقضای واقعی معتبر می‌ماند.
  - **اقدام BFF:** روی هر پاسخِ ۲xx، اگر `X-Renewed-Token` بود، مقدارِ کوکی را
    با آن جایگزین کنید تا نشست تمدید‌شونده بماند و کاربر هرگز logout نشود.
- **(ب)** بله، `X-Renewed-Token` روی همان پاسخ‌های احرازشده می‌آید (حالا فقط ۲xx — بند ۵).

**آماده برای جلسه.** تا آن زمان قرارداد بدون تغییر است و localStorage می‌تواند بماند.

---

## ۲) 🔴 آدرس‌های یک‌بارمصرف (transient) — ✅ پیاده شد

گزینهٔ **(الف)** پیاده شد:

- `POST /v1/customer/addresses` حالا فیلدِ اختیاریِ **`transient: true`** را می‌پذیرد.
- آدرس‌های transient در **`GET /v1/customer/addresses` برنمی‌گردند** (دفترچهٔ
  آدرس تمیز می‌ماند)، ولی مثلِ آدرسِ عادی `id` دارند و در `POST /orders` به‌عنوان
  `address_id` قابل‌استفاده‌اند.
- آدرسِ transient **هرگز پیش‌فرض نمی‌شود** و پیش‌فرضِ فعلیِ کاربر را جابه‌جا
  نمی‌کند؛ هنگام حذفِ آدرسِ پیش‌فرض هم برای جانشینی نادیده گرفته می‌شود.

```jsonc
POST /v1/customer/addresses
{ "city_id": 12, "full_address": "…", "transient": true }
// → 201، ولی در GET /addresses دیده نمی‌شود. از id همان برای POST /orders استفاده کنید.
```

> اگر کاربر بعداً «ذخیره برای بعد» را زد، یک `POST /addresses` بدونِ `transient`
> بزنید (آدرسِ ماندگارِ جدید). آدرسِ transientِ قبلی همچنان به سفارش وصل می‌ماند.

---

## ۳) 🟠 فیلتر وضعیت روی `GET /orders` — ✅ از قبل موجود بود

`GET /v1/customer/orders` پارامترِ `?status=` را (کاما-جدا) می‌پذیرد و
stringهای موبایل را به enumِ داخلی نگاشت می‌کند. مقادیرِ پشتیبانی‌شده:

| مقدار `status` | معادلِ داخلی |
|----------------|--------------|
| `pending` | New |
| `assigned` | Coordinated |
| `scheduled` | Open |
| `in_progress` | Transit، Returned |
| `suspended` | Suspended |
| `completed` | Completed |
| `cancelled` | Cancelled |
| `declined` | Declined |

```
GET /v1/customer/orders?page=1&per_page=10&status=completed
GET /v1/customer/orders?status=pending,scheduled     // چند وضعیت با کاما
```

`per_page` حداکثر ۵۰. `meta` شاملِ `page/per_page/total/last_page`.

---

## ۴) 🟠 ذخیرهٔ `attribution` — ✅ پیاده شد

`POST /orders` حالا آبجکتِ `attribution` را می‌پذیرد و روی سفارش (ستونِ JSON)
ذخیره می‌کند. فقط کلیدهای مجاز نگه داشته می‌شوند (whitelist)، هر مقدار تا ۵۰۰
کاراکتر:

```
gclid, gbraid, wbraid, fbclid, msclkid, ttclid,
utm_source, utm_medium, utm_campaign, utm_term, utm_content,
referrer, landing_page
```

```jsonc
POST /v1/customer/orders
{ "...": "...", "attribution": { "gclid": "abc123", "utm_source": "google", "utm_medium": "cpc" } }
```

کلیدهای ناشناخته بی‌صدا حذف می‌شوند؛ اگر چیزی نفرستید مشکلی نیست (`null` ذخیره می‌شود).

---

## ۵) 🟢 `X-Renewed-Token` — ✅ تأیید + سخت‌گیرتر شد

تأیید می‌شود: renewal فقط روی endpointهای **احرازشده** رخ می‌دهد. علاوه بر آن،
حالا فقط روی پاسخ‌های **موفق (۲xx)** ارسال می‌شود؛ روی خطاها (۴xx/۵xx) هیچ
`X-Renewed-Token`ی نمی‌آید (میان‌افزارِ `RollingToken` قبل از ساختِ توکن،
statusِ پاسخ را چک می‌کند).

---

## ۶) 🟢 موارد باز قبلی

- **`GET /v1/customer/invoices/{token}`** — ✅ دیپلوی شده. متدِ `showByToken`
  فقط فاکتورِ خودِ مشتری را برمی‌گرداند؛ در غیر این صورت ۴۰۴ (وجودِ فاکتور لو نرود).
- **دامنهٔ `pending-reviews`** — ✅ از قبل به سفارش‌های اخیر محدود است
  (`config('customerapp.reviews.pending_window_days')`، پیش‌فرض **۳۰ روز**، بر
  اساسِ `completed_at`). برای ۱۴ روز، همین کلید را ست کنید.
- **فرمتِ تاریخ‌ها** — بک‌اند در DB همیشه میلادی ذخیره می‌کند؛ ورودیِ
  `scheduled_date` هم شمسی و هم میلادی (`YYYY-MM-DD`/`YYYY/MM/DD`) را می‌پذیرد و
  خودش نرمالایز می‌کند. خروجی‌های زمانی (`completed_at`، `updated_at`) ISO8601
  UTC هستند. تبدیل به شمسیِ نمایشی سمتِ فرانت انجام شود.

---

با تشکر — تیم بک‌اند تعمیرآنلاین 🙏
