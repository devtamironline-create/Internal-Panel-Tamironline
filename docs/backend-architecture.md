# معماری بک‌اند — ماژول Site

این سند برای **تیم بک‌اند Laravel** نوشته شده. ساختار، الگوها و راهنمای
نگهداری ماژول `Site` و یکپارچگی آن با CRM را پوشش می‌دهد.

> برای داکیومنت مصرف API توسط فرانت، به `docs/frontend-integration.md`
> مراجعه کنید.

---

## ۱) دامنه‌ی ماژول و فلسفه‌ی طراحی

ماژول `Site` کنترل کامل سایت عمومی `tamironline.com` را در پنل ادمین
فراهم می‌کند. سایت فرانت با Next.js نوشته شده و فقط محتوای ساختاریافته
از Laravel می‌خواند — هیچ منطق رندری در بک‌اند نیست.

**اصول طراحی:**

1. **Schema-driven** — ساختار همه‌ی صفحات در یک فایل PHP config تعریف شده. تغییر در schema بدون migration ممکن است.
2. **JSON payload** — محتوا در ستون `payload` (JSON) ذخیره می‌شود. فرم ادمین به‌صورت داینامیک از schema تولید می‌شود.
3. **منبع حقیقت مشترک** — Brand و Device از CRM می‌آیند (بدون duplicate). تغییر در CRM در فرانت سایت دیده می‌شود.
4. **بدون لاجیک رندر** — Laravel فقط داده می‌دهد، فرانت تصمیم می‌گیرد چگونه نمایش دهد.
5. **idempotent everywhere** — همه‌ی migrationها، seederها، و updateOrCreate‌ها قابل تکرار هستند.

---

## ۲) ساختار فایل‌ها

```
Modules/Site/
├── module.json
├── Config/
│   ├── page-sections.php       ← Schema همه‌ی صفحات و سکشن‌ها
│   └── activity-areas.php      ← لیست مناطق برای Live Activity
├── Database/
│   ├── Migrations/             ← migrationهای ماژول
│   └── Seeders/
│       ├── SiteContentSeeder.php
│       └── SiteCrmCatalogSeeder.php
├── Models/
│   ├── AboutStat.php
│   ├── Banner.php
│   ├── ContactMessage.php
│   ├── Faq.php
│   ├── PageSection.php          ← هسته‌ی سیستم محتوا
│   ├── Page.php                 ← (legacy CRUD)
│   ├── SiteSetting.php          ← KV settings
│   ├── Taxonomy.php             ← دسته‌بندی FAQ/Testimonial
│   └── Testimonial.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/               ← UI ادمین
│   │   │   ├── AboutStatController.php
│   │   │   ├── BannerController.php
│   │   │   ├── ContactMessageController.php
│   │   │   ├── FaqController.php
│   │   │   ├── PageContentController.php   ← اصلی
│   │   │   ├── PageController.php          ← legacy
│   │   │   ├── SettingsController.php
│   │   │   ├── TaxonomyController.php
│   │   │   └── TestimonialController.php
│   │   └── Api/V1/              ← API public
│   │       ├── AboutStatController.php
│   │       ├── ActivityController.php
│   │       ├── CatalogBrandController.php
│   │       ├── CatalogDeviceController.php
│   │       ├── ContactMessageController.php
│   │       ├── DevicePageController.php
│   │       ├── HealthController.php
│   │       ├── PageController.php
│   │       └── TestimonialController.php
│   └── Requests/                ← FormRequests
├── Providers/
│   ├── SiteServiceProvider.php   ← register validators + load config
│   └── RouteServiceProvider.php
├── Resources/views/admin/        ← Blade views پنل ادمین
├── Routes/
│   ├── web.php                   ← روت‌های ادمین
│   └── api.php                   ← روت‌های v1
├── Services/
│   └── PageSectionService.php    ← قلب سیستم — validation + hydrate
└── Support/
    └── MediaUrl.php              ← نرمالایز URL تصاویر
```

---

## ۳) سیستم Schema-driven محتوای صفحات

### ۳.۱ منبع حقیقت: `Config/page-sections.php`

این فایل ساختار همه‌ی صفحات سایت را تعریف می‌کند:

```php
return [
    'home' => [
        'title' => 'صفحه‌ی اصلی',
        'sections' => [
            'hero' => [
                'label' => 'سکشن Hero',
                'fields' => [
                    'title'    => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                    'services' => ['label' => 'دستگاه‌ها', 'type' => 'reference', 'source' => 'devices'],
                ],
            ],
        ],
    ],
    'about'   => [...],
    'contact' => [...],
    'layout'  => [...],
];
```

این config در `SiteServiceProvider::boot()` با `mergeConfigFrom()` لود می‌شود
و در سراسر کد با `config('site.page-sections')` در دسترس است.

### ۳.۲ ذخیره‌سازی محتوا: `site_page_sections`

```
site_page_sections
├── id (bigint, PK)
├── page_slug (varchar 50, indexed)
├── section_key (varchar 50)
├── payload (JSON)
├── is_published (bool)
└── unique(page_slug, section_key)
```

هر ردیف = محتوای یک سکشن از یک صفحه. payload یک JSON آزاد است که با
schema validate می‌شود.

### ۳.۳ Field Types

| نوع | کاربرد | DB storage |
|---|---|---|
| `string` | input تک‌خطی | `"text"` |
| `textarea` | چندخطی | `"text\nwith\nlines"` |
| `url` | URL کامل (strict) | `"https://..."` |
| `site_url` (rule) | URL کامل یا مسیر داخلی | `"/order"` یا `"https://..."` |
| `responsive_image` | تصویر دسکتاپ + موبایل | `{ desktop, mobile }` |
| `int` | عدد | `42` |
| `bool` | چک‌باکس | `true`/`false` |
| `select` | dropdown | `"option_value"` |
| `repeater` | آرایه‌ای از آیتم‌ها | `[{...}, {...}]` |
| `reference` | انتخاب از مخزن | `[id1, id2, ...]` |

#### Reference sources

```
'source' => 'faqs'                  // string ULID — جدول faqs
'source' => 'testimonials'          // string ULID — جدول testimonials
'source' => 'brands'                // int — crm_brands
'source' => 'devices'               // int — crm_devices
'source' => 'faq_categories'        // int — site_taxonomies type=faq
'source' => 'testimonial_categories'// int — site_taxonomies type=testimonial
```

### ۳.۴ افزودن یک سکشن جدید — راهنمای کامل

برای اضافه کردن سکشن `services_grid` به صفحه `home`:

**۱. ویرایش `Config/page-sections.php`:**

```php
'home' => [
    'sections' => [
        // ... سکشن‌های قبلی
        'services_grid' => [
            'label' => 'گرید خدمات',
            'fields' => [
                'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
                'items' => [
                    'label' => 'آیتم‌ها',
                    'type'  => 'repeater',
                    'item_fields' => [
                        'icon'  => ['label' => 'آیکن', 'type' => 'string'],
                        'title' => ['label' => 'تیتر', 'type' => 'string', 'rules' => 'required|string|max:80'],
                    ],
                ],
            ],
        ],
    ],
],
```

**۲. تمام شد.** بدون migration یا کد دیگر:
- فرم ادمین خودکار از schema تولید می‌شود
- API خودکار سکشن جدید را در `GET /v1/pages/home → sections.services_grid` برمی‌گرداند
- ادمین در `/admin/site/page-content/home` کارت جدید را می‌بیند

**۳. (اختیاری) seed داده پیش‌فرض در `SiteContentSeeder`.**

**۴. (اختیاری) آپدیت داکیومنت فرانت** — جدول سکشن‌های Home در §۸ و §۴.۲.

---

## ۴) سرویس اصلی — `PageSectionService`

این کلاس قلب سیستم است. مسئولیت‌ها:

- خواندن schema (`pages()`, `sectionsOf()`, `schema()`)
- بارگذاری برای ادمین (`loadForAdmin()`) — حتی سکشن‌های خالی
- بارگذاری برای public (`loadForPublic()`) — با hydrate و placeholder
- ذخیره‌سازی (`saveAll()`) — validation کامل + sync با DB

### ۴.۱ Validation pipeline

`saveAll()` این مراحل را طی می‌کند:

```
input → پاکسازی repeaterها (حذف ردیف‌های خالی) → ساخت rules از schema
      → Validator::make() → throw ValidationException در صورت خطا
      → updateOrCreate در site_page_sections
```

پیام‌های خطا با prefix `sections.{key}.payload.{field}` برمی‌گردند تا
فرم بداند کجا highlight کند.

### ۴.۲ Hydration برای public

```
DB payload → hydrateReferences (faqs/testimonials/brands/devices → آیتم کامل)
           → applyPlaceholders (substitute {device}, {device_slug}, {page_title})
           → خروجی JSON
```

برای هر فیلد `reference`، یک کلید `<field>_items` در خروجی اضافه می‌شود
که آرایه‌ی hydrate شده‌ی آیتم‌ها است:

```json
"faq": {
  "faq_ids": ["01HX...", "01HY..."],
  "faq_ids_items": [
    { "id": "01HX...", "question": "...", "answer": "..." }
  ]
}
```

برای source های دسته‌بندی (`faq_categories`)، خروجی متفاوت است:

```json
"category_ids_items": [
  {
    "id": 1, "slug": "support", "label": "پشتیبانی",
    "items": [{ "id": "01HX...", "question": "...", "answer": "..." }]
  }
]
```

### ۴.۳ Placeholder substitution

در `loadForPublic($pageSlug, $context)`، context آرایه‌ای از متغیرها است.
هر مقدار string در سکشن‌ها بازنویسی می‌شود:

```php
// در DevicePageController:
$context = [
    'device'      => $device->name,
    'device_slug' => $device->slug,
    'page_title'  => $device->name,
];
$sections = $this->sections->loadForPublic('device', $context);
```

نتیجه: `"تعمیر {device} چقدر زمان می‌برد؟"` → `"تعمیر لباس‌شویی چقدر زمان می‌برد؟"`

برای افزودن متغیر جدید، فقط کلید را در `$context` پاس دهید — قانون
`{var}` در `applyPlaceholders()` خودکار آن را جایگزین می‌کند.

### ۴.۴ Hero auto-fallback

سکشن `home.hero` رفتار خاص دارد:

- اگر admin هیچ device انتخاب نکرده باشد، `services_items` خودکار با همه‌ی
  دستگاه‌های فعال پر می‌شود (مرتب با `is_featured DESC, sort_order ASC`)
- این لاجیک در `loadForPublic()` بعد از hydration اعمال می‌شود
- همچنین `services_total = COUNT(devices WHERE is_active=true)` اضافه می‌شود

این تضمین می‌کند فرانت همیشه دیتای معنی‌دار می‌گیرد، حتی قبل از پر شدن
کامل ادمین.

---

## ۵) مدل‌ها و رابطه‌ها

### ۵.۱ نقشه‌ی مدل‌های Site

```
PageSection (site_page_sections)        ← هسته‌ی محتوا

Faq (faqs) ──┬── many-to-many ──> Taxonomy (type=faq)
             └── site_faq_taxonomies

Testimonial (testimonials) ──┬── many-to-many ──> Taxonomy (type=testimonial)
                             └── site_testimonial_taxonomies

Page (site_pages) ── many-to-many ──> Testimonial, Faq (legacy)

Banner (site_banners)                ← standalone
AboutStat (site_about_stats)         ← standalone
SiteSetting (site_settings)          ← KV
ContactMessage (contact_messages)    ← فقط درج/نمایش/حذف
```

### ۵.۲ مدل‌های CRM که استفاده می‌شوند

```
Brand (crm_brands)
  + is_featured, is_active, sort_order

Device (crm_devices)
  + is_featured, is_active, sort_order, icon, tone, thumbnail
```

---

## ۶) Validators سفارشی

در `SiteServiceProvider::registerValidators()`:

### `site_url`

برای فیلدهای لینک که هم مسیر داخلی (`/order`) و هم URL کامل را قبول می‌کنند:

```php
Validator::extend('site_url', function ($attribute, $value, $params, $validator) {
    if ($value === null || $value === '') return true;
    if (str_starts_with($value, '/'))     return ! str_starts_with($value, '//');
    if (str_starts_with($value, 'mailto:') || str_starts_with($value, 'tel:')) return true;
    return filter_var($value, FILTER_VALIDATE_URL) !== false
        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
});
```

**جدول پذیرفته/رد:**

| ورودی | نتیجه |
|---|---|
| `/order` | ✅ |
| `https://example.com` | ✅ |
| `mailto:`, `tel:` | ✅ |
| `//evil.com` | ❌ (protocol-relative — risk) |
| `javascript:...` | ❌ (XSS) |
| `order` | ❌ (no scheme) |

برای افزودن validator جدید، در همان متد `registerValidators()` اضافه کنید.

---

## ۷) Permissions

### ۷.۱ ماتریس

| Permission | کاربرد |
|---|---|
| `manage-site` | super — همه چیز |
| `view-site-contact-messages` | فقط مشاهده پیام‌ها |
| `manage-site-contact-messages` | تغییر وضعیت/حذف پیام |
| `manage-site-testimonials` | CRUD نظرات + categories |
| `manage-site-faqs` | CRUD سوالات + categories |
| `manage-site-pages` | ویرایش محتوای صفحات |
| `manage-site-banners` | CRUD بنرها |
| `manage-site-settings` | تنظیمات عمومی + about-stats |

`manage-permissions` (super-admin) همه‌ی permissions را override می‌کند.

### ۷.۲ الگوی Controller

هر controller ادمین یک متد `checkAccess()` دارد:

```php
private function checkAccess(): void
{
    $u = auth()->user();
    if (! $u || (
        ! $u->can('manage-site-testimonials')
        && ! $u->can('manage-site')
        && ! $u->can('manage-permissions')
    )) {
        abort(403);
    }
}
```

این الگو در همه‌ی controller‌های Admin/* تکرار شده.

### ۷.۳ افزودن permission جدید

```php
// در migration جدید:
$permission = Permission::firstOrCreate(['name' => 'new-permission', 'guard_name' => 'web']);
$admin = Role::where('name', 'admin')->first();
if ($admin && !$admin->hasPermissionTo('new-permission')) {
    $admin->givePermissionTo($permission);
}
```

---

## ۸) API Endpoints — نقشه‌ی روت‌ها

همه در `Modules/Site/Routes/api.php`:

| Method | URI | Controller | Auth | Throttle |
|---|---|---|---|---|
| POST | `v1/contact-messages` | `ContactMessageController@store` | `internal.token` | `10,1` |
| GET | `v1/pages/{slug}` | `PageController@show` | — | `60,1` |
| GET | `v1/devices/{slug}` | `DevicePageController@show` | — | `60,1` |
| GET | `v1/activity/recent` | `ActivityController@recent` | — | `60,1` |
| GET | `v1/testimonials` | `TestimonialController@index` | — | `60,1` |
| GET | `v1/catalog/brands` | `CatalogBrandController@index` | — | `60,1` |
| GET | `v1/catalog/devices` | `CatalogDeviceController@index` | — | `60,1` |
| GET | `v1/site/about-stats` | `AboutStatController@index` | — | `60,1` |
| GET | `v1/health` | `HealthController@__invoke` | — | `120,1` |

**Internal token:** در `app/Http/Middleware/VerifyInternalToken.php` با `hash_equals`
(timing-safe). از `.env: INTERNAL_API_TOKEN` خوانده می‌شود. برای rotation،
`INTERNAL_API_TOKEN_OLD` هم پشتیبانی می‌شود.

**Cache strategy:**
- POST: بدون cache
- GET ثابت (brands, devices, about-stats): `s-maxage=600`
- GET نیمه‌ثابت (pages, testimonials): `s-maxage=300`
- GET پویا (activity): `s-maxage=60`

---

## ۹) Admin Routes

همه در `Modules/Site/Routes/web.php` تحت `/admin/site/*`:

| منبع | روت‌ها |
|---|---|
| Dashboard | `GET /` |
| Contact Messages | `index`, `show`, `update-status`, `destroy` |
| Testimonials | full CRUD + `toggle-publish` |
| FAQs | full CRUD |
| Pages (legacy) | full CRUD — در حال جایگزینی |
| Page Content (جدید) | `index`, `edit/{slug}`, `update/{slug}` |
| Banners | full CRUD |
| Settings | `edit`, `update` |
| About Stats | full CRUD |
| Taxonomies | `index/{type}`, `store/{type}`, `update/{type}/{id}`, `destroy/{type}/{id}` |

روت‌های CRM brand/device در `Modules/CRM/Routes/web.php` نگه داشته شدند
(چون متعلق به ماژول CRM هستند) ولی در سایدبار «مدیریت سایت» هم لینک
داده می‌شوند.

---

## ۱۰) Database Migrations

```
database/migrations/
├── 2026_05_19_001_add_manage_site_permission.php
├── 2026_05_19_010_add_site_granular_permissions.php
├── 2026_05_19_011_add_is_featured_to_crm_brands.php
├── 2026_05_19_013_add_tone_and_featured_to_crm_devices.php
└── 2026_05_19_014_add_thumbnail_to_crm_devices.php

Modules/Site/Database/Migrations/
├── 2026_05_19_002_create_contact_messages_table.php
├── 2026_05_19_003_create_testimonials_table.php
├── 2026_05_19_005_create_faqs_table.php
├── 2026_05_19_006_create_site_pages_table.php       (+ 2 pivot)
├── 2026_05_19_007_create_site_banners_table.php
├── 2026_05_19_008_create_site_settings_table.php
├── 2026_05_19_009_create_site_about_stats_table.php
├── 2026_05_19_012_create_site_page_sections_table.php
└── 2026_05_19_015_create_site_taxonomies_tables.php  (+ 2 pivot)
```

همه idempotent‌اند و قابل rollback.

---

## ۱۱) Storage — آپلود فایل

### ۱۱.۱ Disk و مسیرها

از disk پیش‌فرض `public` استفاده می‌شود (`storage/app/public/`). مسیرها:

- `storage/app/public/site/brands/` — لوگوی برندها
- `storage/app/public/site/devices/` — thumbnail دستگاه‌ها

### ۱۱.۲ ذخیره و حذف

در `Modules\CRM\Http\Controllers\BrandController::handleLogo()` و
`DeviceController::handleThumbnail()`:

```php
if ($request->hasFile('logo_file')) {
    $path = $request->file('logo_file')->store('site/brands', 'public');
    if ($brand) $this->deleteStoredImage($brand->logo);
    return $path;
}
```

ستون DB یا path نسبی (`site/brands/abc.png`) ذخیره می‌کند یا URL خارجی.
هرگز path مطلق ذخیره نشود.

### ۱۱.۳ نمایش با URL کامل

در API responses، `Modules\Site\Support\MediaUrl::resolve($value)` همیشه
URL کامل برمی‌گرداند:

```php
public static function resolve(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    if (Str::startsWith($value, ['http://', 'https://'])) return $value;
    if (Str::startsWith($value, '/')) return $value;
    return asset('storage/' . ltrim($value, '/'));
}
```

### ۱۱.۴ Storage link

**در deploy حتماً اجرا کن:**

```bash
php artisan storage:link
```

بدون این، فایل‌های آپلودشده accessible نیستن.

### ۱۱.۵ پارشال Uploader

`Modules/CRM/Resources/views/partials/image-uploader.blade.php` —
component قابل استفاده مجدد با file input + URL paste + preview.

```blade
@include('crm::partials.image-uploader', [
    'name'     => 'logo',
    'fileName' => 'logo_file',
    'label'    => 'لوگو',
    'value'    => old('logo', $brand->logo ?? null),
])
```

فرم والد باید `enctype="multipart/form-data"` داشته باشد.

---

## ۱۲) Live Activity Generation

`/v1/activity/recent` دیتای فیک تولید می‌کند (نه از CRM orders).

### ۱۲.۱ ورودی‌ها

- `config('site.activity-areas')` — ۴۰ منطقه‌ی تهران/کرج
- `Modules\CRM\Models\Device` فعال
- `Modules\CRM\Models\Brand` فعال

### ۱۲.۲ الگوریتم

```
seed = crc32(minute | device_slug | brand_slug)
Randomizer با Mt19937 engine

for i in [0..limit):
    device = random device (filtered if device_slug)
    area   = random area
    minutes_ago = 60% < 30min, 30% < 6h, 10% < 48h
    status = 75% completed, 25% in_progress
    label  = brand ? "تعمیر {device} {brand}" : "تعمیر {device}"

sort by minutes_ago asc
```

### ۱۲.۳ چرا seed مبتنی بر دقیقه؟

برای هماهنگی با HTTP cache (`s-maxage=60`):
- در پنجره‌ی ۶۰ ثانیه‌ای، dataset ثابت می‌ماند
- بعد از انقضای cache، seed جدید → dataset جدید
- بدون نیاز به DB write یا redis

---

## ۱۳) Taxonomies (دسته‌بندی)

### ۱۳.۱ مدل

یک جدول عمومی `site_taxonomies` با `type` field — برای FAQ، Testimonial
(و انواع آینده):

```
site_taxonomies(id, type='faq'|'testimonial', slug, name, sort_order, is_active)
site_faq_taxonomies(faq_id, taxonomy_id)
site_testimonial_taxonomies(testimonial_id, taxonomy_id)
```

### ۱۳.۲ Scope در مدل

```php
public function scopeOfType($query, string $type) {
    return $query->where('type', $type);
}
```

استفاده:
```php
Taxonomy::ofType(Taxonomy::TYPE_FAQ)->active()->ordered()->get();
```

### ۱۳.۳ افزودن type جدید

برای مثلاً `service` taxonomy:

1. در Taxonomy model: `public const TYPE_SERVICE = 'service';`
2. relations جدید: `services()` با cascade pivot
3. در TaxonomyController: `assertType()` را گسترش دهید
4. در page-sections schema: source جدید `service_categories`
5. در `PageSectionService::referenceItemRule()` و `resolveReference()` پشتیبانی اضافه کنید

---

## ۱۴) Seeders

### ۱۴.۱ ترتیب

```
DatabaseSeeder
  ├── RolesAndPermissionsSeeder
  ├── AdminSeeder
  └── SiteContentSeeder
        └── (call) SiteCrmCatalogSeeder
            ├── seedDevices (با check وجود ستون thumbnail/tone/is_featured)
            └── seedBrands (با check وجود ستون is_featured)
```

### ۱۴.۲ Idempotent

همه seederها از `updateOrCreate()` استفاده می‌کنند. می‌توان بی‌نهایت بار
اجرا کرد بدون duplicate.

### ۱۴.۳ اجرا

```bash
# همه:
php artisan db:seed

# فقط site:
php artisan db:seed --class="Modules\Site\Database\Seeders\SiteContentSeeder"

# فقط catalog:
php artisan db:seed --class="Modules\Site\Database\Seeders\SiteCrmCatalogSeeder"
```

### ۱۴.۴ دیتای واقعی

محتوا از سایت لایو `tamironline.com` استخراج شده. محتوای placeholder (مثل
testimonials با URL صوتی) seed نشده — ادمین باید خودش وارد کند.

---

## ۱۵) Deploy Checklist

```bash
# 1) Pull کد
git pull origin develop-site

# 2) Dependencies
composer install --optimize-autoloader --no-dev

# 3) Migrations
php artisan migrate --force

# 4) Storage link (اگه اولین بار)
php artisan storage:link

# 5) Seeders (اختیاری برای فرست deploy)
php artisan db:seed --class="Modules\Site\Database\Seeders\SiteContentSeeder" --force

# 6) Cache rebuild
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7) Restart workers اگر دارید
php artisan queue:restart
sudo systemctl reload nginx
```

### `.env` متغیرها

```env
# اجباری
INTERNAL_API_TOKEN=<48 byte random hex>

# اختیاری برای rotation
INTERNAL_API_TOKEN_OLD=

# پیش‌فرض Laravel کافی است
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.tamironline.com
```

تولید توکن:
```bash
php artisan tinker
> bin2hex(random_bytes(48))
```

---

## ۱۶) کارهای رایج — How-To

### چطور یک صفحه جدید به سایت اضافه کنم؟

برای مثلاً صفحه‌ی `services`:

1. در `Config/page-sections.php`:
   ```php
   'services' => [
       'title' => 'صفحه‌ی خدمات',
       'sections' => [
           'hero' => [...],
           'list' => [...],
       ],
   ],
   ```
2. تمام شد. ادمین در `/admin/site/page-content` کارت جدید می‌بیند.
   API: `GET /v1/pages/services` پاسخ می‌دهد.

### چطور یک field type جدید اضافه کنم؟

برای مثلاً `color`:

1. در `PageSectionService::validateSection()` یک case جدید:
   ```php
   if ($type === 'color') {
       $value = Arr::get($payload, $key);
       $data[$key] = is_string($value) ? trim($value) : null;
       $rules[$key] = 'nullable|regex:/^#[0-9a-fA-F]{3,8}$/';
       continue;
   }
   ```
2. در `Modules/Site/Resources/views/admin/page-content/_field.blade.php`:
   ```blade
   @elseif($type === 'color')
       <input type="color" name="{{ $name }}" value="{{ old($name, $value ?? '#000000') }}">
   ```
3. در داکیومنت‌ها به جدول field types اضافه کنید.

### چطور یک endpoint جدید اضافه کنم؟

1. Controller در `Modules/Site/Http/Controllers/Api/V1/`
2. روت در `Routes/api.php` با throttle مناسب
3. Cache-Control header در پاسخ
4. مستندسازی در `docs/frontend-integration.md`

### چطور یک reference source جدید اضافه کنم؟

برای مثلاً `services`:

1. در `PageSectionService::referenceItemRule()`:
   ```php
   'services' => 'integer|exists:services,id',
   ```
2. در `resolveReference()` یک شرط جدید با شکل خروجی:
   ```php
   if ($source === 'services') {
       return Service::whereIn('id', $ids)->get()->map(...)->all();
   }
   ```
3. در `PageContentController::edit()` لیست را در `$references` بدهید
4. در `edit.blade.php` نمایش نام برای هر آیتم

### چطور placeholder جدید اضافه کنم؟

برای مثلاً `{brand}`:

1. در controller endpoint که context را می‌سازد، کلید جدید اضافه کنید:
   ```php
   $context = [..., 'brand' => $brand->name];
   ```
2. تمام شد — `applyPlaceholders()` خودکار آن را جایگزین می‌کند.

---

## ۱۷) Troubleshooting

### `ParseError: unexpected end of file, expecting @endif`

دلیل: استفاده از Alpine directive (مثل `@error`) که با Blade conflict می‌کند.

راه حل: `@@error` بنویسید — Blade آن را به `@error` literal تبدیل می‌کند.

دستکاری Alpine directiveها در Blade:
- `@click`, `@submit`, `@change`, `@input`, `@keyup`, `@load` → بدون escape (Blade این‌ها را directive نمی‌داند)
- `@error` → **با escape**: `@@error`
- `@props`, `@verbatim` → با escape

### `SQLSTATE: Table doesn't exist`

ترتیب اجرای migration را چک کنید. seeder قبل از migrate نباید اجرا شود.

### Live activity همیشه خالی است

- بررسی کنید `Device::where('is_active', true)->count() > 0`
- اگر صفر است، `SiteCrmCatalogSeeder` را اجرا کنید

### تصاویر آپلودشده نمایش داده نمی‌شوند

- `php artisan storage:link` اجرا کنید
- `storage/app/public/site/...` مجوز ۷۵۵ داشته باشد
- nginx/apache باید `public/storage/` را serve کند

### API همیشه 500 می‌دهد بدون پیام

در production، `Exceptions` handler در `bootstrap/app.php` فقط
`"Server error"` می‌دهد بدون stack-trace. برای دیباگ:

```bash
tail -f storage/logs/laravel.log
# یا موقت:
APP_DEBUG=true
```

---

## ۱۸) منابع و لینک‌های مرتبط

- داکیومنت فرانت: `docs/frontend-integration.md`
- لایو سایت مرجع: `http://tamironline.com`
- PR اصلی: develop-site → main

برای تاریخچه:
```bash
git log --oneline Modules/Site/
git log --oneline docs/
```

---

## ۱۹) تماس و مسئولیت‌ها

| مسئولیت | کسی که می‌داند |
|---|---|
| معماری ماژول Site | تیم بک‌اند |
| Schema page-sections | بک‌اند (هماهنگ با فرانت) |
| سایت Next.js | تیم فرانت |
| محتوای ادمین | تیم محتوا / مدیر سایت |
| Deploy | DevOps |

هر تغییری در schema که شکل API را تغییر دهد، **قبل از deploy** باید
داکیومنت فرانت آپدیت شود.
