# پاسخِ بک‌اند — کاتالوگِ دستگاه‌ها و انجمنِ اپ (PWA)

**در پاسخ به:** نامهٔ تیمِ فرانتِ اپ — ۱۴۰۵/۰۴/۱۵

| # | Endpoint | وضعیت |
|---|----------|-------|
| ۱ | `GET /v1/catalog/devices` | ✅ **از قبل موجود و عمومی بود** + aliasهای `name`/`image` اضافه شد |
| ۲ | `GET /v1/customer/forum/topics` | ✅ تحویل شد |
| ۳ | `GET /v1/customer/forum/topics/{id}` | ✅ تحویل شد |
| ۴ | `POST /v1/customer/forum/topics/{id}/comments` | ✅ تحویل شد (throttle ۵/ساعت) |
| ۵ | `GET /v1/customer/forum/my-questions` | ✅ تحویل شد (ادغامِ انجمن + مقالات با `context`) |

> روی سرور: `php artisan migrate` (یک مهاجرت: `customer_id` روی پاسخ‌های انجمن + backfill).

---

## پاسخِ سوالاتِ باز

1. **`catalog/devices` عمومی است** — همان `/v1/catalog/devices` که صدا می‌زنید (بدونِ auth، throttleِ catalog). پاسخ envelope‌ی `{data, meta}` است؛ فیلدها: `id` (همان `device_id` ثبتِ سفارش ✓)، `name` و `label` (هر دو)، `image` و `thumbnail` (هر دو، **URL مطلق**)، `slug/icon/tone/sort_order/is_featured/category`. مرتب‌سازی سمتِ سرور: `is_featured DESC → sort_order ASC → name` ✓. `badge` فعلاً نداریم (اگر لازم شد از پنل اضافه می‌کنیم).
   ⚠️ «تصویرِ ناقصِ بعضی دستگاه‌ها» مشکلِ داده است نه API — تصویرِ هر دستگاه در پنل → CRM → مدیریتِ دستگاه‌ها (فیلد thumbnail) آپلود می‌شود؛ هر دستگاهی آنجا تصویر داشته باشد در `image` مطلق برمی‌گردد.

2. **تعریفِ `is_answered`:** روی topic یعنی حداقل یک پاسخِ **تأییدشده** دارد (وضعیتِ داخلی: `answered`/`expert-answered`/`solved`؛ کارشناسی یعنی پاسخِ staff). روی commentهای داخلِ topic همیشه `false` است چون مدلِ ما **flat** است: پاسخِ کارشناس/AI خودش یک comment با `author.is_staff=true` است (نه reply تو در تو؛ `replies` فعلاً همیشه `[]`).

3. **دلیلِ رد:** بله ✅ — فیلدِ **`rejection_reason`** (نه `rejected_reason`) روی هر آیتمِ `status=rejected` در `my-questions` و روی کامنت‌های `is_mine` در جزئیاتِ topic. متنش توسطِ مودریشنِ AI به‌صورتِ جملهٔ محترمانهٔ خطاب به کاربر تولید می‌شود (مثلاً «متنِ شما حاویِ شماره‌تماسِ تبلیغاتی است؛ لطفاً بدونِ اطلاعاتِ تماس دوباره ارسال کنید»). `spam` هم برای کاربر `rejected` برمی‌گردد.

---

## نگاشتِ مدل (مهم)

سیستمِ انجمنِ ما «سوال/پاسخ» است؛ قراردادِ شما «topic/comment». نگاشت:

- **Topic = سوالِ منتشرشدهٔ انجمن** — همان‌هایی که در سایت `/forum/{slug}` دارند.
- **Comment = پاسخ/دیدگاهِ داخلِ آن** (flat). `author.is_staff=true` یعنی پاسخِ رسمی (AI/ادمین/کارشناس).
- `POST comments` یک دیدگاهِ pending می‌سازد که واردِ چرخهٔ **مودریشنِ خودکارِ AI** می‌شود (تأیید/رد با علت، حداکثر ~۵ دقیقه بعد).
- `comments_count` فقط تأییدشده‌ها ✓.

## جزئیاتِ پاسخ‌ها (مطابقِ قراردادِ شما)

- `topics`: صفحه‌بندیِ `{data, meta}`؛ آیتم: `id, title, excerpt, comments_count, is_answered, last_activity_at, created_at, device{id,name,slug}`. فیلترِ اختیاری: `?device_id=`.
- `topics/{id}`: همهٔ فیلدهای بالا + `body` + `comments[]` + `my_open_questions[]`.
  - **حریمِ خصوصی سمتِ سرور** ✓: `comments` = همهٔ approvedها + pending/rejectedهای **خودِ کاربرِ جاری** (`is_mine=true`). `rejection_reason` فقط برای آیتم‌های خودِ کاربر.
  - `my_open_questions` = pendingهای خودِ کاربر در همین topic.
- `my-questions`: صفحه‌بندی‌شده، **همهٔ وضعیت‌ها**، مرتبِ نزولی، هر آیتم: `id, kind, body, status, is_answered, is_mine, rejection_reason, created_at, context{type,id,title}`.
  - سه منبع ادغام می‌شوند: سوال‌های انجمنِ کاربر (`kind=forum_question`، contextِ خودش)، دیدگاه‌های انجمن (`kind=forum_comment`)، و کامنت‌های مقالات (`kind=article_comment`, `context.type=article`) — مقالات از همین حالا پوشش داده شده‌اند ✓.
  - فیلترهای `?context_type=forum_topic|article` و `?context_id=` ✓.
- همهٔ پاسخ‌های این namespace با `Cache-Control: private, no-store` (دادهٔ شخصی).
- envelopeِ استانداردِ `/v1/customer` (`{success, data, meta}`) توسطِ middleware اعمال می‌شود؛ خطاها `{success:false, message, code}` فارسی.

## بونوس‌هایی که خودکار می‌گیرید
- ثبتِ سوال/دیدگاهِ جدید → مودریشن و در صورتِ سالم‌بودن انتشارِ خودکار ظرفِ ~۵ دقیقه.
- وقتی پاسخی برای سوالِ کاربر منتشر شود، **پیامکِ اطلاع‌رسانی** با لینکِ صفحه برایش می‌رود.
