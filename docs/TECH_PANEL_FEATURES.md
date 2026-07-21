# پنل تکنسین — مرجعِ کاملِ قابلیت‌ها (Feature Reference)

> این سند **تک‌تکِ قابلیت‌های پنلِ تکنسین** را با ریزجزئیات فهرست می‌کند. پنلِ
> تکنسین دو نسخه دارد و هر قابلیت در هر دو (تا جای ممکن) یکسان است:
>
> 1. **پنلِ وب (PWA)** — Blade، گاردِ `tech` روی جدولِ `crm_technicians`،
>    زیرِ مسیرِ `/tech/*` (نام‌روت‌ها با پیشوندِ `tech.`).
> 2. **API اپِ موبایل** — توکنِ Sanctum، زیرِ `/v1/technician/*`. مرجعِ کاملِ
>    قرارداد در `docs/TECH_APP_API.md`.
>
> ستونِ **API** در هر بخش نشان می‌دهد آن قابلیت اندپوینتِ موبایل دارد یا فقط
> در وب است. جدولِ تطبیقِ کامل در انتهای سند (بخش ۱۴) آمده است.

---

## ۰) معماری، احراز و گیت‌ها

- **گارد/احراز:** وب با session (گاردِ `tech`)؛ موبایل با `Authorization: Bearer <token>` (Sanctum). subjectِ هر دو مدلِ `Technician` است.
- **تمدیدِ توکن (موبایل):** هر پاسخِ موفق ممکن است هدرِ `X-Renewed-Token` بدهد → توکن را جایگزین کنید. هدرِ `X-Device-ID` روی همهٔ درخواست‌ها.
- **قالبِ پاسخِ API:** موفق `{ success:true, data, [meta|stats|message] }`؛ خطا `{ success:false, message, [errors] }`. کدها: `401` (توکن نامعتبر→logout)، `403` (عدمِ مالکیت/غیرتکنسین)، `422` (اعتبارسنجی)، `429` (throttle)، `423` (حالتِ فریز).
- **مالکیت (ownership):** همهٔ عملیاتِ سفارش چک می‌کنند سفارش متعلق به همان تکنسین باشد (`order.technician_id === tech.id`) وگرنه `403`. سفارش‌های `Declined` کاملاً از فهرستِ تکنسین پنهان‌اند.
- **قفلِ اطلاعاتِ تماس:** روی سفارشِ نهایی (`is_final=true`) شماره‌ها ماسک و `contact_locked=true`؛ برخی اکشن‌ها (یادداشت، زمان مراجعه، نتیجهٔ تماس) روی سفارشِ نهایی قفل‌اند.

### دو گیتِ سراسری (middleware)

**الف) حالتِ فریز — `TechPanelReadOnly`** (فعال وقتی `crm_settings.tech_panel_readonly === '1'`):
- بلاک: **شارژِ کیف‌پول** و **تغییرِ وضعیت به وضعیت‌های نهایی** (`cancelled|completed|transit|declined`).
- بقیهٔ عملیات (یادداشت، زمان مراجعه، تغییرِ وضعیتِ غیرنهایی) باز است.
- ردّ: API → `423`؛ وب → بازگشت با پیام «⏳ پنل در حال به‌روزرسانی است».

**ب) گیتِ آموزش — `RequireTrainingCompleted`** (تا وقتی همهٔ ویدیوهای فعال دیده نشده):
- فقط این‌ها باز است: صفحاتِ **آموزش**، **پروفایل** (+رمز/آواتار)، و **خروج**.
- بقیهٔ مسیرها: API → `403 {redirect}`؛ وب → ریدایرکت به `/tech/training`.
- تکنسین‌های قدیمی با migration معاف شدند؛ فقط تکنسینِ واقعاً جدید قفل می‌شود.

---

## ۱) احراز هویت و ورود

ثابت‌ها: طولِ OTP از `config('sms.otp.length', 4)` (کفِ ۴ رقم)؛ **TTL: وبِ ۳۰۰s / APIِ موبایل `config('sms.otp.expires_in', 120)`s**؛ **فاصلهٔ ارسالِ مجدد = ۶۰s**؛ **حداکثر تلاشِ تأیید = ۵**. کلیدهای کش `tech_otp_{mobile}` و `tech_otp_last_{mobile}` (جدا از OTPِ ادمین/مشتری). سمتِ API: میدل‌ورهای `EnsureTechnician` (توکن باید مالِ تکنسینِ فعالِ غیرحذف‌شده باشد) + `TechRollingToken` (تمدیدِ کشویی با `X-Renewed-Token`).

| قابلیت | وب | API |
|---|---|---|
| نمایشِ فرمِ ورود (اگر لاگین باشد→داشبورد) | `GET /tech` | — |
| **ارسالِ OTP** | `POST /tech/auth/send-otp` | `POST /v1/technician/auth/send-otp` |
| **تأیید OTP و ورود** | `POST /tech/auth/verify-otp` | `POST /v1/technician/auth/verify-otp` |
| **ورود با رمز** | `POST /tech/auth/login-password` | ❌ (فقط وب) |
| اطلاعاتِ منِ تکنسین | — | `GET /v1/technician/me` |
| خروج | `POST /tech/logout` | `POST /v1/technician/auth/logout` |
| خروج از impersonate | `POST /tech/impersonate/leave` | — |

- **ارسالِ OTP:** ورودی `mobile` (regex `^09\d{9}$`). شماره باید در `crm_technicians` باشد وگرنه `403` («شماره در سامانه ثبت نیست»). اگر کمتر از ۶۰s از ارسالِ قبلی گذشته → `429` با `wait_time`. در `local` کدِ `debug_code` برمی‌گردد.
- **تأیید OTP:** ورودی `mobile` + `code` (`digits:{len}`). نبودِ کش → `422` («منقضی»)؛ ۵ تلاشِ غلط → پاک‌سازی و `422`؛ کدِ درست → ورود.
- **ورود با رمز:** فقط اگر تکنسین رمز تنظیم کرده باشد؛ پیامِ خطای عمومی (بدونِ افشای وجود/عدمِ کاربر).
- **قاعدهٔ ورود:** اگر `status==='inactive'` → `403`. ورود با `remember=true` و ثبتِ `last_login_at`.

---

## ۲) داشبورد و تقویم

| قابلیت | وب | API |
|---|---|---|
| داشبورد (تقویمِ ۷ روزه با بازه‌های ساعتی) | `GET /tech/dashboard` | `GET /v1/technician/dashboard` |
| نمای تقویم (لیستِ کاملِ روزها) | `GET /tech/calendar` | — (در همان dashboard) |

- **بازه‌های ساعتی:** ۹–۱۲، ۱۲–۱۵، ۱۵–۱۸، ۱۸–۲۱ + بازهٔ خارج‌از‌ساعت (`off_slot`) + بدونِ‌زمان (`unscheduled`).
- وضعیت‌های «فعال» که در تقویم می‌آیند: New, Coordinated, Open, Suspended, RepairStarted, AwaitingPart, AwaitingCustomerApproval.
- سفارش‌های بدونِ `visit_scheduled_at` بر اساسِ `created_at` سطل‌بندی می‌شوند (قدیمی‌ترها در سطلِ امروز).
- هدر: **بدهیِ فاکتور** (`invoice_debt` = مجموعِ company_share فاکتورهای `in_wallet=false`).

---

## ۳) سفارش‌ها (فهرست و جزئیات)

| قابلیت | وب | API |
|---|---|---|
| فهرستِ سفارش‌ها (+فیلترِ وضعیت +جست‌وجو +آمار) | `GET /tech/orders?status=&q=` | `GET /v1/technician/orders?status=&q=&page=` |
| جزئیاتِ سفارش | `GET /tech/orders/{id}` | `GET /v1/technician/orders/{id}` |

- **فهرست:** پایه = همهٔ سفارش‌های تکنسین **به‌جز `Declined`**. آمار (همیشه روی کلِ پایه): total, coordinated, open, completed. صفحه‌بندیِ ۱۵تایی.
- **جزئیات:** شاملِ مشتری، آدرس+مختصات، دستگاه/برند، **مشکل‌ها (objections به‌صورت برچسب + `problem_title/description`)**، اقلامِ مالی، یادداشت‌ها، `allowed_transitions` (رادیوهای مجازِ وضعیت که سرور حساب کرده)، تاریخچهٔ وضعیت، پیش‌فاکتورها، رسیدهای انتقال. مالکیت اجباری.
- وضعیت‌ها: `new, coordinated, open, suspended, completed, cancelled, transit, declined, repair_started, awaiting_part, …` — برچسب/بَج/گروه از خودِ پاسخ (hard-code نکنید).

---

## ۴) اکشن‌های سفارش (نوشتن)

| قابلیت | وب | API |
|---|---|---|
| **تغییرِ وضعیت** (+بلاکِ فاکتور هنگام «انجام کار») | `POST /tech/orders/{id}/status` | `POST /v1/technician/orders/{id}/status` |
| ثبتِ زمانِ مراجعه (یا پاک‌کردن) | `POST /tech/orders/{id}/schedule-visit` | `POST /v1/technician/orders/{id}/schedule-visit` |
| افزودنِ یادداشتِ تکنسین | `POST /tech/orders/{id}/notes` | `POST /v1/technician/orders/{id}/notes` |
| نتیجهٔ تماس (هماهنگ/بی‌پاسخ) | `POST /tech/orders/{id}/call-result` | `POST /v1/technician/orders/{id}/call-result` |
| پیامکِ «آمادهٔ تحویل» | `POST /tech/orders/{id}/deliver-sms` | `POST /v1/technician/orders/{id}/deliver-sms` |
| ارسالِ دستیِ پیامکِ رسیدِ انتقال (یک‌بار) | `POST /tech/orders/{id}/transfer-receipt/{tr}/send-sms` | — |
| ثبتِ دستیِ رسیدِ انتقال | — | `POST /v1/technician/orders/{id}/transfer-receipt` |

### تغییرِ وضعیت — قواعد دقیق
- `status` باید در `allowed_transitions` باشد. `description` وقتی هدف ∈ {Coordinated, Suspended, Declined, Transit} **اجباری (حداقل ۱۵ کاراکتر)**، وگرنه اختیاری.
- نگاشتِ توضیح به فیلد: Coordinated→`description_tech`، Suspended→`description_tech1`، Open→`description_tech2`، Declined→`cancel_reason`، Transit→`return_description`.
- **بلاکِ فاکتور — فقط `completed`:** `price_customer`, `pieces[] {title, buy_price, customer_price}`, `hire/transportation/discount` (اختیاری، `integer|min:0`), `invoice_descripotion`, `device_img1` (تصویر ≤۳۰MB)، `save_as_draft` (`0|1`).
  - قواعدِ اجباری (به‌جز پیش‌نویس/برگشتی): عکسِ دستگاه اجباری (مگر قبلاً آپلود شده)، `invoice_descripotion` اجباری (به مشتری می‌رود)، `price_customer > 0` و **`price_customer ≥ مجموعِ buy_price`ها**.
  - `total_invoice` را **سرور** حساب می‌کند = `max(0, price_customer − cost_price)` (ورودیِ کاربر نادیده).
- **عوارضِ خودکارِ سرور:** ثبتِ `OrderStatusLog`؛ هنگام `completed` (غیرِ پیش‌نویس) → **صدورِ فاکتور** (`InvoiceService`, کمیسیون/کیف‌پول)؛ اگر بدهیِ شرکت > ۰ و `tech_debt_popup_enabled != '0'` → پاپ‌آپِ بدهی؛ **پیامکِ وضعیت** به مشتری (روی سفارشِ برگشتی، پیامکِ تکمیل ارسال نمی‌شود)؛ هنگام `open` → **ساختِ رسیدِ انتقال** (بدونِ پیامکِ خودکار).
- **ماشینِ وضعیت (`allowedStatusesFor`):** وضعیت‌های نهایی قفل (بدونِ transition)؛ `return_type==1`→فقط Completed؛ `return_type==2`→Cancelled+Completed؛ در حالتِ فریز وضعیت‌های نهایی حذف می‌شوند.

### زمانِ مراجعه
- `visit_date` (`Y-m-d`, `after_or_equal:today`) + `visit_slot` (`1..4`)، یا `{clear:true}` برای پاک‌کردن. روی سفارشِ نهایی قفل.
- اگر سفارش `new` بود و Coordinated مجاز بود → خودکار «هماهنگ‌شده» + **پیامکِ هماهنگی** به مشتری.

### یادداشت
- `note` (`required|max:2000`). به `wp_notes` JSON اضافه می‌شود. روی سفارشِ نهایی قفل.

### سیستمِ بازخوردِ بعد از تماس با مشتری (Call Feedback) ★
سیستمی که تضمین می‌کند **هیچ تماسی بدونِ ثبتِ نتیجه رها نشود**. وقتی تکنسین در
فازِ هماهنگی روی دکمهٔ تماس (`tel:`) می‌زند و به اپ برمی‌گردد، یک **مودالِ
اجباریِ «نتیجهٔ تماس چه شد؟»** باز می‌شود (فرانت با `visibilitychange`/`AppState`
+ پشتیبانِ تایمر، برگشت به اپ را تشخیص می‌دهد — بدونِ رفرش).

- **مسیر:** `POST /tech/orders/{id}/call-result` (وب) و `POST /v1/technician/orders/{id}/call-result` (موبایل).
- **فازِ هماهنگی** = وضعیتِ فعلی ∈ {`new`, `awaiting_coordination`, `no_answer`}. این سیستم فقط در این فاز معنا دارد.
- **قفل:** روی سفارشِ نهایی → `422`. مالکیت اجباری.
- **ورودی:** `result ∈ {coordinated, no_answer}` (اجباری)؛ `reason` **فقط وقتی `no_answer`** اجباری است (حداقل ۳، حداکثر ۱۰۰۰ حرف — «چرا پاسخ نداد؟»).

**دو شاخهٔ نتیجه:**

1. **`coordinated` (با مشتری هماهنگ شد):**
   - تغییرِ وضعیت **همین‌جا انجام نمی‌شود**؛ فقط در تاریخچه ثبت می‌شود «هماهنگ شد — در انتظارِ ثبتِ زمانِ مراجعه».
   - پاسخ: `data: { status, next_action: "schedule_visit", has_default_time: bool }`.
   - **`has_default_time`**: اگر سفارش از اپِ مشتری با روز/ساعتِ پیشنهادی آمده باشد `true` است → فرانت باید تقویمِ «ثبتِ زمانِ مراجعه» را با همان زمان **پیش‌پر** و فقط دکمهٔ «تأیید» نشان دهد؛ اگر `false` تقویمِ خالی برای انتخاب.
   - سپس خودِ `schedule-visit` کارِ «هماهنگ‌شده + پیامکِ هماهنگی» را انجام می‌دهد.

2. **`no_answer` (مشتری پاسخ نداد):**
   - اگر در فازِ هماهنگی بود و قبلاً `no_answer` نبود → وضعیت به **«مشتری پاسخگو نیست»** (`NoAnswer`) می‌رود.
   - **`reason` در تاریخچه ثبت می‌شود** (چرا پاسخ نداد) تا اپراتور/ادمین ببیند.
   - پاسخ: `data: { status, next_action: null }`.

- راهِ فرارِ «تماس برقرار نشد» که فقط مودال را می‌بندد (بدونِ ثبت) هم پیش‌بینی شده.
- پاسخِ وب اگر `expectsJson` بود JSON، وگرنه ریدایرکت (وب می‌تواند به لنگرِ `#schedule-visit` برود).

### پیامکِ «آمادهٔ تحویل»
- فقط اگر **`tech.ready_for_delivery=true`** (وگرنه `403`) و سفارش `completed`.

### رسیدِ انتقال (پیامک)
- **وب:** ارسالِ دستیِ پیامکِ رسید — **فقط یک‌بار** (بعد از آن خطا؛ ارسالِ مجدد فقط از پنلِ ادمین). **API:** ثبتِ دستیِ رسیدِ جدید (فقط وقتی قابلیت فعال و سفارش `open|repair_started`).

---

## ۵) کیف‌پول و شارژ

| قابلیت | وب | API |
|---|---|---|
| تاریخچهٔ تراکنش‌ها (+فیلترِ نوع +آمار) | `GET /tech/wallet?type=` | `GET /v1/technician/wallet?type=&page=` |
| صفحهٔ شارژ | `GET /tech/wallet/recharge` | — |
| **شروعِ شارژِ کیف‌پول** | `POST /tech/wallet/recharge` | `POST /v1/technician/wallet/recharge` (throttle 20/min) |

- تراکنش‌ها بدونِ نوعِ `Adjustment` (ردیف‌های ممیزیِ ادمین پنهان). آمار: مجموعِ کمیسیون/پاداش/جریمه/شارژ + `invoice_debt`.
- **شارژ:** `amount` (تومان، حداقل ۵۰۰٬۰۰۰ — یا ۱۰٬۰۰۰ در `test_mode`؛ سقف ۵۰٬۰۰۰٬۰۰۰). درگاه از `payment_gateway` (پیش‌فرض `zibal`). **Zibal**: ریدایرکت به `payment_url`. **ملت**: فرمِ POST با `RefId`. بازگشت به callbackِ وب.
- **در حالتِ فریز کاملاً بلاک (`423`).**

---

## ۶) فاکتورها

| قابلیت | وب | API |
|---|---|---|
| فهرستِ فاکتورها (+فیلترِ وضعیت +آمار) | `GET /tech/invoices?status=` | `GET /v1/technician/invoices?status=&page=` |

- `status ∈ {draft, issued, paid, cancelled}`. آمار: count, total_sum, tech_share, company_share. هر ردیف: `invoice_code, status, total_amount, tech_share, company_share, order_code, customer_name, issued_at`.

---

## ۷) پیش‌فاکتور (پشتِ فلگ `tech_proforma_enabled` — اگر خاموش، `404`)

| قابلیت | وب | API |
|---|---|---|
| فهرستِ پیش‌فاکتورهای خودِ تکنسین | `GET /tech/proformas` | `GET /v1/technician/proformas` |
| فرمِ ساخت (+پیش‌پر با `order_id`) | `GET /tech/proformas/create` | — |
| **ساختِ پیش‌فاکتور** | `POST /tech/proformas` | `POST /v1/technician/proformas` |
| جزئیات | `GET /tech/proformas/{id}` | `GET /v1/technician/proformas/{id}` |
| **تأییدِ مشتری (finalize)** | `POST /tech/proformas/{id}/finalize` | `POST /v1/technician/proformas/{id}/finalize` |

- ورودیِ ساخت: `order_id?` (مالکیت چک می‌شود)، `customer_name?/customer_mobile?/device_name?/brand_name?/description?/valid_until?`، `items[] {title(اجباری), quantity?, unit_price?}`. **تخفیف همیشه صفر** (سیاستِ تکنسین). با ساخت خودکار «صادر/sent» می‌شود و در اپِ مشتری دیده می‌شود — **بدونِ SMS**.
- **finalize:** وضعیت را «accepted» می‌کند و به `tech.orders.show?proforma=code&price=total` می‌برد تا فرمِ «پایان سفارش» با قیمت پیش‌پر شود (فاکتورِ نهایی از مسیرِ عادیِ تکمیل صادر می‌شود).

---

## ۸) آموزش (گیتِ فعال‌سازیِ پنل)

| قابلیت | وب | API |
|---|---|---|
| فهرستِ دسته‌ها + پیشرفت | `GET /tech/training` | `GET /v1/technician/training` |
| ویدیوهای یک دسته | `GET /tech/training/category/{id}` | `GET /v1/technician/training/categories/{id}` |
| ویدیوهای بدونِ دسته | `GET /tech/training/uncategorized` | `GET /v1/technician/training/uncategorized` |
| جزئیاتِ ویدیو | `GET /tech/training/{id}` | `GET /v1/technician/training/videos/{id}` |
| **علامتِ «دیدم»** | `POST /tech/training/{id}/watched` | `POST /v1/technician/training/videos/{id}/watched` |

- فقط دسته‌ها/ویدیوهای فعال، مرتب. پیشرفت: `watched/total/remaining/percent`. با رسیدنِ `remaining=0` → پنل فعال می‌شود (`training_completed_at`) و به داشبورد می‌رود.
- شکلِ ویدیو (API): `provider (aparat|youtube|vimeo|mp4|unknown), video_url, playback_url, thumbnail_url, duration_seconds, watched`.

---

## ۹) پروفایل

| قابلیت | وب | API |
|---|---|---|
| نمایشِ پروفایل | `GET /tech/profile` | (از `GET /me`) |
| ویرایشِ اطلاعاتِ تماس | `POST /tech/profile` → **عمداً غیرفعال** | ❌ (مجاز نیست) |
| **آپلودِ آواتار — فقط یک‌بار** | `POST /tech/profile/avatar` | `POST /v1/technician/profile/avatar` |
| **تغییرِ رمز** | `POST /tech/profile/password` | `POST /v1/technician/profile/password` |

- **آواتار:** `avatar` (تصویر jpg/jpeg/png/webp، سقفِ `max:30720` KB ≈ ۳۰MB در هر دو نسخه). اگر `img_personal` قبلاً ست شده → خطا (ضدِسوءاستفاده، یک‌بار).
- **رمز:** `current_password` + `password` (`confirmed`, حداقل ۶). ویرایشِ اطلاعاتِ تماس در هر دو نسخه ممنوع است.

---

## ۱۰) تیکتِ پشتیبانی — فقط وب (API ندارد) ⚠️

| قابلیت | وب | API |
|---|---|---|
| فهرستِ تیکت‌ها | `GET /tech/tickets` | ❌ |
| فرمِ تیکتِ جدید | `GET /tech/tickets/create` | ❌ |
| ثبتِ تیکت | `POST /tech/tickets` | ❌ |
| مشاهدهٔ تیکت + گفتگو | `GET /tech/tickets/{id}` | ❌ |
| پاسخ به تیکت | `POST /tech/tickets/{id}/reply` | ❌ |

- ثبت: `category_id` (اجباری، `exists`), `body` (اجباری، ≤۵۰۰۰), `order_id?` (اگر باشد باید مالِ تکنسین باشد), `subject?`, `image?` (تصویر ≤۳۰MB). وضعیتِ اولیه `open`.
- پاسخ: روی تیکتِ `closed` مجاز نیست؛ `body` اجباری + `image?`. با هر پاسخ وضعیت `open` و `last_reply_at` به‌روز می‌شود. مالکیت اجباری.

---

## ۱۱) سیستمِ چت با پشتیبان (تکنسین ↔ اپراتور) — فقط وب (API ندارد) ⚠️

یک سیستمِ **پیام‌رسانِ دوطرفهٔ درون‌سازمانی** بینِ تکنسین و **اپراتورِ
تخصیص‌داده‌شده‌اش**. جایگزینِ تماس/تلگرام برای هماهنگی‌های روزمره است و کاملاً
داخلِ پنل ثبت و بایگانی می‌شود.

### مدلِ داده و تخصیص
- **پیام‌ها:** جدولِ `crm_tech_chat_messages` (مدلِ `TechChatMessage`): `technician_id`, `sender_type ∈ {tech, operator}`, `sender_id`, `body`, `read_at` (رسیدِ خواندن)، `created_at`.
- **تخصیصِ اپراتور:** رابطهٔ چند‌به‌چندِ `Technician::operators()` روی pivotِ `crm_technician_operators` (`technician_id ↔ user_id`). یعنی **هر تکنسین می‌تواند یک یا چند اپراتورِ پشتیبان داشته باشد** و هر اپراتور فقط تکنسین‌های خودش را می‌بیند.
- **چه کسی تخصیص می‌دهد:** ادمینِ دارای دسترسیِ `manage-technicians` از صفحهٔ **تخصیص‌ها** (`/admin/crm/tech-chats/assignments`).

### سمتِ تکنسین (پنلِ وب)
| قابلیت | وب | API |
|---|---|---|
| صفحهٔ گفتگو (+خوانده‌کردنِ پیام‌های اپراتور) | `GET /tech/messages` | ❌ |
| ارسالِ پیام | `POST /tech/messages/send` | ❌ |
| Poll پیام‌های جدید | `GET /tech/messages/poll?after_id=` | ❌ |
| شمارِ نخوانده (بَجِ ناوبری) | `GET /tech/messages/unread` | ❌ |

- باز‌کردنِ صفحه، همهٔ پیام‌های **اپراتور** را «خوانده» (`read_at`) می‌کند.
- **ارسال:** اگر هیچ اپراتوری تخصیص نیافته باشد → `422` («هنوز کارشناسی برای شما تخصیص داده نشده است»). `body` اجباری ≤۲۰۰۰. پاسخِ JSON شاملِ پیامِ ساخته‌شده.
- **poll:** پیام‌های `id > after_id` را برمی‌گرداند و همزمان پیام‌های اپراتور را «خوانده» می‌کند (هر چند ثانیه صدا می‌شود). `unread` فقط شمارِ نخوانده‌های اپراتور را برای بَج می‌دهد.

### سمتِ اپراتور/ادمین (`/admin/crm/tech-chats/*` — کنترلرِ `TechChatController`)
| قابلیت | مسیر |
|---|---|
| فهرستِ تکنسین‌هایِ منِ اپراتور (+آخرین پیام/نخوانده) | `GET /admin/crm/tech-chats` |
| خلاصهٔ نخوانده‌ها (بَج) | `GET /admin/crm/tech-chats/unread-summary` |
| جست‌وجوی تکنسین | `GET /admin/crm/tech-chats/search` |
| گفتگو با یک تکنسین (+خوانده‌کردنِ پیام‌های تکنسین) | `GET /admin/crm/tech-chats/{technician}` |
| ارسالِ پیام (به‌عنوانِ اپراتور) | `POST /admin/crm/tech-chats/{technician}/send` |
| Poll | `GET /admin/crm/tech-chats/{technician}/poll` |
| **صفحهٔ تخصیصِ اپراتورها** (گیت `manage-technicians`) | `GET /admin/crm/tech-chats/assignments` |
| **تغییرِ تخصیص** (گیت `manage-technicians`) | `PATCH /admin/crm/tech-chats/{technician}/assign` |

- **دامنهٔ دید:** اپراتور فقط تکنسین‌هایی را می‌بیند که در `operators` او هستند (`whereHas('operators', user.id)`). فقط ادمینِ `manage-technicians` می‌تواند تخصیص را عوض کند.
- **رسیدِ خواندن دوطرفه:** بازکردنِ گفتگو در سمتِ اپراتور، پیام‌های **تکنسین** را خوانده می‌کند؛ و بالعکس در سمتِ تکنسین.

> **برای اپِ موبایل:** این سیستم هنوز اندپوینتِ `/v1/technician/*` ندارد — قراردادِ
> پیشنهادی در `docs/TECH_APP_API.md` بخش ۱۲.۳ آمده (index/send/unread + polling).

---

## ۱۲) اعلانات — فقط وب (API ندارد) ⚠️

| قابلیت | وب | API |
|---|---|---|
| فهرستِ اعلانات (+وضعیتِ تأیید) | `GET /tech/announcements` | ❌ |
| فیدِ پاپ‌آپِ تأییدنشده‌ها | `GET /tech/announcements/unacked` | ❌ |
| ثبتِ «متوجه شدم» | `POST /tech/announcements/{id}/ack` | ❌ |

- `unacked`: اعلان‌های فعالِ تأییدنشده، قدیمی‌ترین اول، حداکثر ۱۰. `ack`: `insertOrIgnore` در `crm_announcement_acks` (idempotent). پاپ‌آپ در وب با `keepalive` + `localStorage` ضدِ‌لوپ است.

---

## ۱۳) نکاتِ مشترک (Cross-cutting)

- **آپلودِ تصاویر:** عکسِ دستگاه/آواتار/تیکت همه با `TechImageStorage::store` (بهینه‌سازیِ سخت؛ فایلِ اصلی نگه داشته نمی‌شود)، سقفِ ۳۰MB.
- **شناسه‌ها:** `OrderStatusLog.changed_by = tech.user_id` (کاربرِ لینک‌شده)، ولی مالکیت/کیف‌پول/تیکت/چت با `tech.id` (شناسهٔ تکنسین).
- **پیامکِ خودکار:** `OrderSmsNotifier` + `SmsTrigger::fromOrderStatus`؛ سفارشِ برگشتی پیامکِ تکمیل را سرکوب می‌کند.

---

## ۱۴) جدولِ تطبیقِ وب ↔ API (شکافِ پوشش)

| حوزه | وب | API موبایل | وضعیت |
|---|---|---|---|
| ورودِ OTP، me، خروج | ✅ | ✅ | کامل |
| ورود با رمز | ✅ | ❌ | **API ندارد** |
| داشبورد/تقویم | ✅ | ✅ | کامل |
| سفارش‌ها (فهرست/جزئیات) | ✅ | ✅ | کامل |
| اکشن‌های سفارش | ✅ | ✅ | کامل |
| کیف‌پول + شارژ | ✅ | ✅ | کامل |
| فاکتورها | ✅ | ✅ | کامل |
| پیش‌فاکتور | ✅ | ✅ | کامل (پشتِ فلگ) |
| آموزش | ✅ | ✅ | کامل |
| پروفایل (آواتار/رمز) | ✅ | ✅ | کامل |
| **تیکتِ پشتیبانی** | ✅ | ❌ | **API ندارد — برای هم‌ترازیِ اپ باید افزوده شود** |
| **چت با اپراتور** | ✅ | ❌ | **API ندارد** |
| **اعلانات** | ✅ | ❌ | **API ندارد** |

> **برای هم‌ترازیِ کاملِ اپِ موبایل با وب، سه حوزهٔ «تیکت پشتیبانی»، «چت با اپراتور»
> و «اعلانات» نیاز به اندپوینتِ `/v1/technician/*` دارند.** قراردادِ پیشنهادی برای
> این سه در `docs/TECH_APP_API.md` بخش‌های ۱۲–۱۴ آمده است (به‌عنوانِ مرجعِ
> پیاده‌سازی؛ منطق و اعتبارسنجی دقیقاً معادلِ نسخهٔ وب).
