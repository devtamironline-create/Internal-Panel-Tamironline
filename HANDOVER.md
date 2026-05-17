# راهنمای انتقال پروژه — پنل داخلی تعمیرآنلاین

> این سند برای انتقال پروژه به همکار جدید نوشته شده. هر بخش شامل: اهداف، فایل‌های کلیدی، نقاط ورود، نکات عملیاتی.

---

## ۱) معماری کلی

### پشتهٔ فنی
| لایه | تکنولوژی |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| ساختار ماژولار | `nwidart/laravel-modules` در `/Modules/` |
| ORM | Eloquent + MySQL |
| تاریخ شمسی | `morilog/jalali` |
| UI | Blade + Livewire 3 + Alpine.js + Tailwind |
| Auth/Permission | Spatie Laravel-Permission |
| WebSocket | Laravel Reverb (برای پیام‌رسان داخلی) |
| پیامک | Kavenegar API |
| PDF | mPDF |
| Excel | PhpSpreadsheet |
| Timezone | Asia/Tehran |
| Locale | fa |

### دامنه‌ها
- **panel.tamironline.com** → پنل لاراول (همین پروژه)
- **crm.tamironline.com** → WordPress CRM قدیمی (هنوز فعال، با لاراول دو طرفه سینک می‌شود)

### ماژول‌های فعال
| ماژول | وضعیت | توضیح |
|---|---|---|
| **CRM** | اصلی | مدیریت مشتری، تکنسین، سفارش، فاکتور، کیف‌پول، تنظیمات سینک |
| **Technician** | فعال | ثبت‌نام تکنسین + بررسی توسط ادمین + قرارداد |
| **Attendance** | فعال | حضور و غیاب پرسنل با IP validation |
| **Salary** | فعال | محاسبهٔ حقوق با اضافه‌کار، تعطیلی، تاریخ شمسی |
| **Staff** | فعال | مدیریت پرسنل |
| **OKR** | فعال | اهداف و کلیدهای موفقیت |
| **Task** | فعال | تسک با ادغام در چت |
| **SMS** | فعال | پیامک Kavenegar |
| **Core** | فعال | شیر شده |
| ~~Warehouse~~ | **منتقل شده** | دیگر در این پنل نیست — به پنل دیگری منتقل شده. لایه‌های قدیمی هنوز در `/Modules/Warehouse/` هست ولی استفاده نمی‌شود. |

> ⚠️ همکار جدید کاری به `Modules/Warehouse/` ندارد. می‌تواند نادیده بگیرد یا در آینده پاک کند.

---

## ۲) ماژول CRM — قلب پروژه

این مهم‌ترین ماژول است. تقریباً همهٔ کار اخیر روی آن انجام شده.

### ۲.۱) موجودیت‌ها (Models)

`Modules/CRM/Models/`

| مدل | جدول | نقش |
|---|---|---|
| `Customer` | `crm_customers` | مشتری (mobile, first_name, phone) |
| `Technician` | `crm_technicians` | تکنسین — login جدا دارد |
| `Order` | `crm_orders` | سفارش تعمیر |
| `OrderStatusLog` | `crm_order_status_logs` | تاریخچهٔ تغییر وضعیت |
| `Invoice` | `crm_invoices` | فاکتور (پس از اتمام سفارش) |
| `WalletTransaction` | `crm_tech_wallet_transactions` | شارژ/پاداش/جریمه/کمیسیون کیف‌پول |
| `Payment` | `crm_payments` | پرداخت آنلاین |
| `Brand` / `Device` / `Province` / `City` | crm_brands و … | تاکسونومی‌ها (همگی wp_id دارند) |
| `Ticket` / `TicketReply` | crm_tickets, crm_ticket_replies | تیکت پشتیبانی |
| `TrainingVideo` / `TrainingCategory` | … | آموزش تکنسین‌ها |
| `SyncLog` | `crm_sync_logs` | لاگ سینک با WP (هر inbound و outbound) |
| `CrmSetting` | `crm_settings` | key-value تنظیمات (token, push URL, …) |

### ۲.۲) وضعیت‌های سفارش (`OrderStatus` enum)

`Modules/CRM/Enums/OrderStatus.php`

| اسم | کد WP | فارسی | نهایی؟ |
|---|---|---|---|
| `New` | 0 | جدید | ❌ |
| `Coordinated` | 1 | هماهنگ شده | ❌ |
| `Open` | 2 | باز شده (در تعمیر) | ❌ |
| `Suspended` | 3 | معلق | ❌ |
| `Completed` | 4 | پایان سفارش | ✅ |
| `Cancelled` | 5 | کنسل | ✅ |
| `Transit` | 10 | فقط ایاب و ذهاب | ✅ |
| `Declined` | 100 | رد سفارش (توسط تکنسین) | ✅ |
| `Returned` | … | برگشتی | ✅ |

**جریان کاری معمول:**
- ادمین/سیستم سفارش می‌سازد → `New`
- تکنسین با مشتری تماس می‌گیرد، زمان مراجعه را تنظیم → `Coordinated`
- تکنسین در محل/تعمیرگاه شروع می‌کند → `Open`
- اگر گیر افتاد → `Suspended`
- تمام → `Completed` (فاکتور صادر می‌شود)
- اگر فقط ایاب رفت و دستگاه قابل تعمیر نبود → `Transit`
- اگر تکنسین قبول نکرد → `Declined` (سفارش از لیست او پنهان می‌شود)

### ۲.۳) پنل تکنسین (PWA)

تکنسین‌ها از `/tech` وارد می‌شوند. این یک پنل **mobile-first / PWA** است.

`Modules/CRM/Resources/views/tech-panel/`:
- `dashboard.blade.php` — صفحهٔ اصلی + تقویم ۷ روزه
- `orders.blade.php` — لیست سفارش‌ها (Declined پنهان)
- `order_show.blade.php` — جزئیات + تغییر وضعیت + فاکتور
- `profile.blade.php` — پروفایل تکنسین
- `invoices.blade.php` — فاکتورها و سهم تکنسین
- `wallet.blade.php` — کیف‌پول
- `training.blade.php` + `training-category.blade.php` + `training-show.blade.php` — آموزش
- `tickets/` — تیکت پشتیبانی

**Login تکنسین:** با موبایل + کد یکبارمصرف. کنترلر: `Modules/CRM/Http/Controllers/Tech/AuthController.php`.

**نکته**: PWA assets (manifest, sw, آیکن‌ها) در `public/manifest.json` و … است.

### ۲.۴) پنل ادمین

از `/admin/crm/...` شروع می‌شود. منو در `resources/views/layouts/admin.blade.php`.

سکشن‌ها:
- داشبورد، مشتری‌ها، **تکنسین‌ها** (مهم)، **پیشنهاد هوشمند تکنسین**
- مالی (فاکتورها، پرداخت‌ها، کیف‌پول)
- تیکت‌ها، آموزش، تنظیمات
- ابزار: **سینک WordPress**، **لاگ سینک**، انتقال داده WP، ظاهر پنل تکنسین، الگوهای پیامک

> ⚠️ آیتم «سفارش‌های تعمیر» موقتاً از منو پنهان شده (با `@if(false)` در `admin.blade.php` خط ~۶۲۷). همچنان با URL `/admin/crm/orders` در دسترس است.

### ۲.۵) پیشنهاد هوشمند تکنسین

`Modules/CRM/Services/TechnicianSuggestionService.php`

برای هر سفارش، چند تکنسین مناسب با امتیاز پیشنهاد می‌دهد. وزن‌ها:
| محور | وزن |
|---|---|
| open_orders (سفارش‌های باز کم) | 30 |
| invoice_debt (بدهی کم) | 25 |
| satisfaction (رضایت بالا) | 20 |
| cancel_rate (نرخ کنسلی کم) | 10 |
| recent_activity (فعالیت اخیر) | 10 |
| response_speed (سرعت پاسخ) | 5 |

**فیلترهای سخت:** active، ظرفیت پر نشده، باید tag (شهر/برند/دستگاه) داشته باشد.

**ابزار ادمین برای تگ‌گذاری:** فرم تکنسین در `_form.blade.php` با چک‌باکس‌های شهر/برند/دستگاه + سرچ.

---

## ۳) **سیستم سینک با WordPress CRM** — مهم‌ترین بخش

این پیچیده‌ترین قسمت است. هر دو طرف به‌صورت **دو طرفه** سینک می‌کنند. بدون فهمیدن این بخش نمی‌توان روی پروژه کار کرد.

### ۳.۱) معماری سینک

**دو سرور:**
1. **Laravel** (panel.tamironline.com) — پنل اصلی این پروژه
2. **WordPress CRM** (crm.tamironline.com) — پنل قدیمی، با plugin به پروژه ما متصل

**پلاگین WordPress در ریپو:** `wp-sync-plugin/tamironline-crm-sync/`

```
[ WordPress CRM ] ←──── HTTP HMAC ────→ [ Laravel ]
       ↑                                       ↑
       └── plugin: tamironline-crm-sync ───────┘
```

### ۳.۲) جهت‌های سینک

| جهت | تریگر | endpoint |
|---|---|---|
| **WP → Laravel** (inbound) | hookهای WP (`save_post`, `set_object_terms`, …) | `POST /api/crm/sync/{entity}` با Bearer token |
| **Laravel → WP** (outbound) | event listenerهای مدل Laravel (`created`, `updated`) | `POST /wp-json/tcs/v1/{endpoint}` با HMAC SHA256 |

### ۳.۳) Endpointهای موجود

**Laravel (inbound از WP) — مسیر: `/api/crm/sync/`:**
- `/order` — upsert سفارش
- `/orders/batch` — batch
- `/customer`, `/customers/batch`
- `/technician`, `/technicians/batch`
- `/financial` — فاکتور / تراکنش کیف‌پول
- `/taxonomy/{brand|device|province|city}` + batch
- `/setting`, `/settings/batch`
- `/ping`

کنترلرها: `Modules/CRM/Http/Controllers/Api/Sync*Controller.php`

**WordPress (inbound از Laravel) — مسیر: `/wp-json/tcs/v1/`:**
- `/order-update` — به‌روزرسانی سفارش موجود
- `/order-create` — ساخت سفارش جدید (Laravel → WP)
- `/technician-upsert` — ساخت/به‌روزرسانی تکنسین
- `/customer-upsert` — ساخت/به‌روزرسانی مشتری
- `/financial-upsert` — تراکنش کیف‌پول / فاکتور

پلاگین‌ها: `wp-sync-plugin/tamironline-crm-sync/includes/class-inbound-*.php`

### ۳.۴) Authentication

| جهت | روش |
|---|---|
| WP → Laravel | Bearer token در `crm_settings.wp_sync_token` |
| Laravel → WP | HMAC SHA256 (هدر `X-TCS-Signature`) با کلید `crm_settings.wp_push_secret` |

تنظیمات: `/admin/crm/sync`

### ۳.۵) **کنترل جهت سینک (دو لایه)**

این مهم‌ترین مفهوم تازه است.

**لایه ۱ — per-Technician:**
- ستون `order_sync_direction` و `wallet_sync_direction` روی `crm_technicians`
- مقادیر: `both` | `wp_to_laravel` | `laravel_to_wp` | `none`
- پیش‌فرض: **سفارش = both**، **کیف‌پول = wp_to_laravel**
- محل تنظیم: فرم ادمین تکنسین

**لایه ۲ — per-Order:**
- ستون `source_of_truth` روی `crm_orders`
- مقادیر: `auto` | `panel` | `crm`
- پیش‌فرض: `auto` (تابع تنظیم تکنسین)
- محل تنظیم: کارت «منبع داده این سفارش» در صفحهٔ ادمین سفارش

**اولویت:** اگر سفارش `source_of_truth ≠ auto` باشد، روی تنظیم تکنسین override می‌کند.

**متدهای helper روی `Order`:**
- `shouldAcceptInboundFromWp()` — برای inbound چک
- `shouldPushToWp()` — برای outbound چک

### ۳.۶) **لاگ سینک — ابزار حیاتی دیباگ**

هر فراخوانی inbound و outbound در جدول `crm_sync_logs` ثبت می‌شود.

**UI:** `/admin/crm/sync-logs`
**فیلترها:** direction, entity_type, status, جستجو روی endpoint/wp_id/error
**هر ردیف نشان می‌دهد:**
- direction (inbound/outbound)
- entity_type (order, technician, customer, wallet_tx, …)
- endpoint, http_status, status (success/failed/skipped)
- payload (JSON کامل)
- response (JSON کامل)
- error_message

**نکته:** هر skip (مثلاً به‌خاطر `blocked_by_order_source_of_truth`) هم لاگ می‌شود.

**Middleware inbound:** `Modules/CRM/Http/Middleware/LogWpSyncInbound.php` — همه را خودکار لاگ می‌کند.
**outbound:** در `Modules/CRM/Services/WpPushService.php::sendTo()` لاگ می‌شود.

### ۳.۷) جلوگیری از حلقه‌های بی‌نهایت

دو ابزار:
1. **WP transient suppress:** هر بار `tcs_suppress_hooks_{post_id}` ست می‌شود (۱۰ ثانیه) → هوک‌های WP این پست را echo نمی‌کنند.
2. **`laravelManaged` filter در `SyncOrderController`:** فیلدهای مدیریت‌شده توسط اپراتور (status, technician_id, piece_list, …) از inbound update فیلتر می‌شوند تا WP cron تغییرات لاراول را عقب نبرد.

### ۳.۸) Command های مفید

```bash
# push دستی همه تکنسین‌ها به WP (مثلاً بعد از تغییر منطق)
php artisan crm:resync-technicians
php artisan crm:resync-technicians --laravel-only
php artisan crm:resync-technicians --id=5

# سفارش‌های یتیم: technician_wp_id دارند ولی technician_id ندارند
php artisan crm:orders:resolve-technicians

# بازنویسی فاکتورها با منطق جدید کمیسیون
php artisan crm:invoices:recompute

# بازمحاسبهٔ مانده کیف‌پول
php artisan crm:wallet:recompute-balances
```

### ۳.۹) آپدیت پلاگین WP

پوشهٔ پلاگین: `wp-sync-plugin/tamironline-crm-sync/` در ریپو.

**برای deploy روی WP:**
1. در پنل لاراول → `/admin/crm/sync` → **«دانلود پلاگین»** → فایل ZIP می‌گیری
2. در WP admin → Plugins → Add New → Upload Plugin → Replace
3. تایید: روی WP حجم فایل را چک کن (مثلاً `wc -c .../includes/class-inbound-technician.php`)

> ⚠️ **هر تغییر در پلاگین** نیاز به آپدیت روی WP دارد (فقط `git pull` لاراول کافی نیست).

---

## ۴) منطق کمیسیون

`Modules/CRM/Services/CommissionCalculator.php` و `Order::financialSummary()`

**قانون فعلی (مه ۲۰۲۶):**
- `percent` در `crm_technicians` = «درصد سهم **شرکت** از کل فاکتور» (نه سهم تکنسین)
- پایهٔ محاسبه = `price_customer` (جمع کل دریافتی از مشتری)
- هزینهٔ قطعات (`cost_price`) **کسر نمی‌شود**

**فرمول:**
```
company_share = price_customer × percent / 100
tech_share    = price_customer − company_share
```

**استثناها:**
- `status = Transit` (ایاب و ذهاب): تکنسین ۱۰۰٪ می‌گیرد
- `type_of_calc_tech = 1` (internal): همان منطق ولی با `tech_per_of_all` به‌عنوان درصد شرکت

> ⚠️ این مهم است: قبلاً `percent` به‌عنوان «سهم تکنسین» تفسیر می‌شد. اگر فاکتور قدیمی پیدا کردی که اعداد متفاوت دارد، `php artisan crm:invoices:recompute` بزن.

---

## ۵) ماژول Technician

ثبت‌نام تکنسین جدید (قبل از اینکه به CRM وارد شود).

**جریان:**
1. تکنسین فرم در `/technician/register` پر می‌کند (با OTP موبایل)
2. ادمین در `/admin/technician/registrations` بررسی می‌کند
3. اگر تایید → یک رکورد در `crm_technicians` ساخته می‌شود

**فایل‌های کلیدی:**
- `Modules/Technician/Models/TechnicianRegistration.php`
- `Modules/Technician/Models/ApplianceCategory.php` — دستگاه‌های قابل سرویس (با parent_id برای سلسله‌مراتب)
- `Modules/Technician/Http/Controllers/RegistrationController.php`
- `Modules/Technician/Resources/views/register.blade.php` — فرم ثبت‌نام
- `Modules/Technician/Resources/views/admin/registrations.blade.php`

**permissions:**
- `view-technician-registrations`
- `approve-technician`, `delete-technician`, `edit-technician-registration`

---

## ۶) پیام‌رسان داخلی (Admin Messenger)

برای ارتباط بین کارمندان داخل پنل.

**ویژگی‌ها:**
- چت یک‌به‌یک و گروهی
- آپلود فایل (تصویر، ویدیو، صوت، PDF، …) — paperclip button
- منشن `@user`، ری‌اکشن، forward، reply
- صوتی/تصویری call (LiveKit/WebRTC) — `Modules/CRM/Resources/views/...`
- اطلاعیه (announcement) و ساخت تسک از پیام

**فایل اصلی:** `resources/views/admin/messenger/index.blade.php` (حدود ۳۲۰۰ خط، Alpine.js)
**Controller:** `app/Http/Controllers/Admin/ChatController.php`
**Models:** `app/Models/Chat/{Conversation,Message,MessageReaction,MessageMention,Call,UserPresence}.php`

**نکتهٔ مهم:** برای نمایش فایل‌ها از پراکسی `/file/{path}` استفاده می‌شود (نه `/storage/...`) چون symlink روی cPanel/LiteSpeed خراب است.

---

## ۷) Storage Proxy — راه‌حل symlink

روی هاست cPanel/LiteSpeed، لینک سمبلیک `public/storage` کار نمی‌کند. به‌جایش:

**کنترلر:** `app/Http/Controllers/StorageProxyController.php`
**Route:** `GET /file/{path}` در `routes/web.php`
**Helper:** `storage_url($path)` در `app/helpers.php` (تابع جهانی)

**استفاده در Blade:**
```blade
{{-- بد --}}
<img src="{{ asset('storage/avatars/x.jpg') }}">

{{-- خوب --}}
<img src="{{ storage_url('avatars/x.jpg') }}">
```

---

## ۸) سایر ماژول‌ها — توضیح کوتاه

### Attendance (حضور و غیاب)
- ورود/خروج پرسنل با چک IP
- محاسبهٔ ساعت کار، لاپچ، مرخصی
- `Modules/Attendance/Http/Controllers/AttendanceController.php`

### Salary (حقوق)
- محاسبهٔ ماهانه با اضافه‌کار، تعطیلی شمسی، مرخصی
- `Modules/Salary/Services/SalaryCalculator.php`

### Staff (پرسنل)
- مدیریت کارمندان (لیست، CRUD)

### OKR (اهداف)
- اهداف و کلیدهای موفقیت تیمی

### Task (تسک)
- ادغام با پیام‌رسان — می‌توان از هر پیام تسک ساخت
- وضعیت، اولویت، مسئول
- `Modules/Task/Models/Task.php`

### SMS
- ارسال یکباره و گروهی Kavenegar
- الگوهای پیامک قابل تنظیم در `/admin/crm/sms-templates`
- log در `crm_sms_log`
- triggerهای خودکار: تخصیص تکنسین، هماهنگی، پایان سفارش، آماده تحویل، …

---

## ۹) Permission‌ها (Spatie)

مدیریت در `/admin/permissions`. نقش‌های اصلی:
- `super-admin` — همه‌چیز
- `manage-permissions` — مثل super-admin
- `manage-crm-orders`, `view-crm-orders`
- `manage-crm-sync`, `manage-crm-settings`
- `view-crm-customers`, `view-crm-technicians`, `view-crm-financial`
- `approve-technician`, `delete-technician`
- `manage-attendance`, `manage-salary`, `manage-staff`
- و …

---

## ۱۰) Deployment

### سرور
- **Host:** Shatel (`shatel148`) — cPanel/LiteSpeed
- **Path:** `/home/<user>/public_html`
- **Branch:** `claude/review-panel-5TKU2` (شاخهٔ کاری فعلی)
- **DB:** `panel_crm` (user: `panel_db`، رمز در .env)
- **PHP CLI:** `/opt/cpanel/ea-php82/root/usr/bin/php` (یا 83/84) — **نه** `/usr/local/bin/php` که PDO ندارد

### روال deploy
```bash
cd /home/<user>/public_html
git pull origin claude/review-panel-5TKU2
php artisan migrate --force
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

اگر تغییر روی پلاگین WP بود، علاوه بر بالا روی سرور WP هم پلاگین را آپدیت کن (بخش ۳.۹).

---

## ۱۱) Git workflow

- شاخهٔ فعلی: `claude/review-panel-5TKU2`
- هر فیکس = یک کامیت
- پیام کامیت به این فرم: `fix(module): short fa description` یا `feat(module): ...`
- تست‌ها: PHPUnit ^11 پیکربندی شده ولی هنوز تست‌ای نوشته نشده

---

## ۱۲) جدیدترین کارها (تاریخچه‌ای از مه ۲۰۲۶)

> این لیست به همکار کمک می‌کند بفهمد چه بود و چه شد.

### سینک WP-Laravel
- اضافه شدن `WpPushService` با همه entityها (تکنسین/مشتری/سفارش/تراکنش)
- اضافه شدن inbound endpointهای WP plugin (`technician-upsert`, `customer-upsert`, `financial-upsert`, `order-create`)
- اضافه شدن `crm_sync_logs` + middleware خودکار + UI
- اضافه شدن کنترل جهت سینک per-technician و per-order
- فیکس شدن چندین مشکل: deduplication موبایل، type_tech mapping، display_name، …

### پنل تکنسین
- حذف Declined از لیست
- اجباری شدن توضیح برای Transit و همه وضعیت‌ها
- min 15 char trim برای توضیحات
- single description textarea (قبل‌تر چندین `name=description` در DOM داشت → bug)
- حذف badge آماده تحویل از پروفایل
- Video player Plyr با fullscreen برای آموزش (locally vendored)

### کمیسیون
- معنای `percent` فلیپ شد: سهم شرکت (نه تکنسین)
- پایهٔ محاسبه: `price_customer` (نه `total_invoice`)
- هزینهٔ قطعات کسر نمی‌شود

### مدیریت تکنسین در ادمین
- جهت سینک per-tech
- سرچ روی چک‌باکس‌های شهر/برند/دستگاه
- درصد ۳۰٪ پیش‌فرض برای Laravel-only

### Appliance categories
- parent/child support (ساختار درختی)
- modal ویرایش برای تغییر والد

### messenger
- آپلود فایل (UI)
- storage proxy برای فایل‌ها
- merge نسخهٔ duplicate شده

### سفارش
- CREATE flow: سفارش جدید از پنل لاراول به WP push می‌شود
- بازگرداندن وضعیت Declined → New هنگام reassign
- منو «سفارش‌های تعمیر» موقتاً پنهان شد

---

## ۱۳) باگ‌های شناخته‌شده / TODO

- **تست‌ها:** هیچ تست خودکاری نوشته نشده. PHPUnit پیکربندی شده.
- **چندین تکنسین تکراری روی WP:** قبلاً به‌خاطر dedup ضعیف چند کاربر مشابه ساخته شده. اگر در WP کاربر تکراری دیدی، بررسی کن:
  ```sql
  SELECT user_id, meta_value FROM or_usermeta WHERE meta_key='mobile' GROUP BY meta_value HAVING COUNT(*) > 1;
  ```
- **تنظیم `wallet_sync_direction` پیش‌فرض `wp_to_laravel`:** اگر مشکل overwrite شارژ کیف‌پول دیدی، این تنظیم را روی تکنسین خاص چک کن.
- **`technician_wp_id` کهنه:** ممکن است در بعضی سفارش‌ها به‌خاطر دلایل تاریخی، `technician_wp_id` با `technician.wp_id` همخوانی نداشته باشد. کامند `crm:orders:resolve-technicians` این را درست می‌کند.

---

## ۱۴) شروع سریع برای همکار جدید

1. **دسترسی:**
   - SSH به `shatel148` (یا هر سروری که الان host پنل است)
   - GitHub repo access
   - cPanel access برای آپلود فایل‌ها در صورت لزوم
   - WP admin برای تست plugin

2. **تست محلی:**
   - `git clone` ریپو، `composer install`، `npm install && npm run dev`
   - `cp .env.example .env`، `php artisan key:generate`
   - دسترسی به یک MySQL محلی و `php artisan migrate`

3. **آشنایی با کد:**
   - `Modules/CRM/Services/WpPushService.php` (مهم‌ترین فایل سینک)
   - `Modules/CRM/Http/Controllers/Api/SyncOrderController.php` (inbound)
   - `Modules/CRM/Models/Order.php` (مفهوم source_of_truth)
   - `wp-sync-plugin/tamironline-crm-sync/includes/class-inbound-order.php` (همتای WP)

4. **منابع داخل کد:**
   - تقریباً هر متد کامنت فارسی دارد که هدف و منطق را توضیح می‌دهد
   - `CLAUDE.md` (در ریشه) — قواعد توسعه و نمای کلی
   - `crm_sync_logs` در DB — برای دیباگ زنده

5. **روش کار توصیه‌شده:**
   - هر تغییر، یک کامیت با پیام واضح
   - قبل از deploy، روی محلی تست کن
   - **هر تغییر در `wp-sync-plugin/`** نیاز به آپدیت روی WP دارد
   - برای دیباگ سینک، اول `/admin/crm/sync-logs` را نگاه کن

---

## ۱۵) تماس‌های ضروری

- **DB credentials (سرور):**
  - Laravel: `.env` در `/home/<user>/public_html/`
  - WP: `/home/crmtamironline/public_html/wp-config.php`
- **تنظیمات سینک:** در `/admin/crm/sync`
- **پنل WP CRM:** `crm.tamironline.com/wp-admin`

---

> **آخرین به‌روزرسانی این سند:** مه ۲۰۲۶  
> برای سؤال در مورد هر بخش، کامنت‌های داخل کد را بخوان — هر متد توضیح فارسی دارد.
