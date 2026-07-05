# پاسخِ بک‌اند — نیازمندی‌های جامعِ انجمن

**در پاسخ به:** «درخواستِ جامع از بک‌اند» + «سوالاتِ منِ انجمن»
**تاریخ:** ۱۴۰۵/۰۴/۱۵

| اولویت | مورد | وضعیت |
|--------|------|-------|
| 🔴 ۱ | sanitizeِ سمتِ سرور | ✅ از قبل انجام شده بود (هر دو مسیرِ سوال/پاسخ با `HtmlSanitizer::clean`) |
| 🔥 ۲ | `GET /v1/forum/my-questions` | ✅ تحویل شد |
| 🔥 ۳ | تاکسونومیِ موضوعات + `?topic=` | ✅ تحویل شد |
| 🔥 ۴ | `popular_searches_items` + `category_shortcuts` | ✅ در پنل تعریف شد (باید پر شوند) |
| 🟡 ۵ | `final_cta` و سایر فیلدهای پنل | ✅ از قبل تعریف شده بود (باید پر شوند) |
| 🟡 ۶ | downvote + popular-searches داینامیک | ✅ هر دو تحویل شد |
| ℹ️ ۷ | تأییدها | ✅ پایینِ سند |

> ⚠️ روی سرور `php artisan migrate` لازم است (۳ مهاجرت).

---

## ۱) `GET /v1/forum/my-questions` — دقیقاً طبقِ قرارداد

- **Auth:** Bearer (Sanctum) — همان توکنِ `POST /v1/forum/questions`. بدونِ توکن → `401`.
- **دامنه:** فقط سوالاتِ کاربرِ احرازشده در **همهٔ وضعیت‌ها**؛ جدیدترین اول؛ `Cache-Control: no-store`.
- **Throttle:** ۳۰ درخواست/دقیقه.

```jsonc
{ "data": [ {
    "id": 124,
    "slug": "124-علت-ارور-e3",
    "title": "علت ارور E3 لباسشویی چیست؟",
    "status": "pending",              // pending | published | rejected (spam هم rejected برمی‌گردد)
    "answer_count": 0,
    "view_count": 0,
    "created_at": "2026-07-05T10:30:00+03:30",
    "rejection_reason": "متنِ بی‌ربط به تعمیرات"   // از آخرین تصمیمِ مودریشنِ AI؛ در نبودش null
} ] }
```

نکتهٔ داخلی: ستونِ `customer_id` روی سوال‌ها وجود نداشت (در `store` ارسال می‌شد ولی
بی‌صدا حذف می‌شد). اضافه شد + سوال‌های قدیمی از روی `author_phone == mobile`ِ مشتری
backfill شدند؛ برای اطمینان، کوئری هم fallbackِ موبایل دارد.

## ۲) تاکسونومیِ موضوعات

### `GET /v1/forum/topics`
```jsonc
{ "data": [ { "slug": "errors", "label": "ارورها و کدخطاها", "description": "...",
              "icon": "alert-circle", "tone": "rose", "question_count": 12,
              "sort_order": 1, "is_active": true } ] }
```
(کش ۱۰ دقیقه؛ فقط موضوع‌های فعال؛ `question_count` = سوال‌های منتشرشده.)

**۶ موضوعِ پیش‌فرض seed شد:** errors، troubleshooting، maintenance، installation، electrical، warranty — با همان آیکن/tone های موردِ قبولِ فرانت.

### فیلترِ `?topic=<slug>`
روی `GET /v1/forum/questions` — کنارِ device/brand/q؛ در `tab_counts` هم لحاظ می‌شود.

### انتساب از پنل
در صفحهٔ جزئیاتِ سوال (ادمین → انجمن → سوال) بخشِ «موضوع» اضافه شد — انتخاب و ذخیره با یک کلیک (دسترسیِ moderate).

## ۳) downvote

```
POST /v1/forum/questions/{id}/downvote   → { "downvotes": 3 }
POST /v1/forum/answers/{id}/downvote     → { "downvotes": 1 }
```
دقیقاً مثلِ upvote: بدونِ auth، throttle 30/min، **dedupeِ IP-based با uniqueِ دیتابیسی**.

## ۴) `GET /v1/forum/popular-searches?limit=6`

```jsonc
{ "data": [ { "text": "ارور F04 پکیج", "count": 123 } ] }
```
- جستجوهای واقعیِ کاربران (پارامترِ `q`) از این پس ثبت می‌شوند (`site_forum_search_logs`).
- خروجی = پرتکرارترین‌های **۳۰ روزِ اخیر** (کش ۱۰ دقیقه، سقفِ limit=20).
- توجه: تا چند روزِ اول داده کم است — fallbackِ فرانت همچنان لازم است.

## ۵) فیلدهای پنل (`GET /v1/pages/forum`)

- **`hero.popular_searches_items`** — فیلد از قبل بود ولی با کلیدِ `popular_searches` سریالایز می‌شد و فرانت نمی‌دیدش؛ **rename شد** به همان کلیدِ قرارداد. (اگر قبلاً چیزی در پنل پر شده بود باید یک‌بار دوباره ذخیره شود.)
- **`category_shortcuts`** — سکشنِ جدید در پنل: `title` + repeaterِ `items` با `slug/label/description/icon/tone/href`. اگر `href` خالی بماند به `/forum?topic={slug}` لینک بدهید (تاکسونومیِ بخش ۲ حاضر است).
- **`sections_visibility.show_category_shortcuts`** — فلگِ جدید اضافه شد (بقیهٔ فلگ‌ها از قبل بود).
- **`final_cta`** — از قبل کامل تعریف شده (title/subtitle/primary/secondary) — فقط باید در پنل **پر شود**؛ hardcode فرانت بعد از پرشدن قابلِ حذف است.
- `experts_section` / `expert_answers_section` / `sidebar_banners` / `seo` — از قبل موجود.

## ۶) تأییدهای درخواستی

- **sanitize:** ✅ هر دو مسیرِ ثبتِ سوال و پاسخ از قبل `HtmlSanitizer::clean` دارند (مسیرِ ویرایشِ ادمین هم).
- **دامنهٔ جستجوی `q`:** حالا **عنوان + بدنه + تگ‌ها** (تگ‌ها اضافه شد).
- **`tab_counts`:** ✅ از قبل در `meta` برمی‌گردد.
- **dedupeِ upvote/downvote:** با **unique constraintِ دیتابیس** روی `(id, ip)` — از `$request->ip()` استفاده می‌شود؛ چون پشتِ پراکسیِ فرانت هستید، IPِ واقعی از طریقِ trusted proxy هندل می‌شود (نه هدرِ خامِ `X-Forwarded-For` که spoof‌پذیر است).
- **فضای slugِ دستگاه:** **انگلیسی و مشترک با کاتالوگ** — هر دو (`device-stats` و فیلترِ `?device=`) از `crm_devices.slug` می‌خوانند (مثلِ `washing-machine`)؛ mismatch ممکن نیست.
- **اطلاع‌رسانیِ داوری:** وقتی پاسخی برای سوال منتشر می‌شود (دستی یا خودکارِ AI)، اگر سوال `author_email` داشته باشد ایمیل ارسال می‌شود.

---

## اجرا روی سرور
```
php artisan migrate
```
سه مهاجرت: topics (+seed) / downvotes + search-logs / customer_id (+backfill).
