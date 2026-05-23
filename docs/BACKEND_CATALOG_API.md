# مستند API کاتالوگ برندها و دستگاه‌ها — برای بک‌اند (Laravel)

> نسخه: 1.0 | تاریخ: 1404/03/02

---

## خلاصه کلی

فرانت‌اند برای صفحات برند و دستگاه از یک **الگوی Fixture + CMS** استفاده می‌کند:

1. **Fixture** (داده‌ی ثابت): فایل‌های `data/brands.ts` و `data/devices.ts` در فرانت — تمام صفحات را خودکار پر می‌کنند.
2. **CMS Override** (بک‌اند): فرانت به اندپوینت‌های بک‌اند درخواست می‌زند — هر فیلدی که بک‌اند برگرداند جایگزین fixture می‌شود. **هر فیلد null یعنی "از fixture استفاده کن"**.

```
صفحه‌ی برند/دستگاه = fixture + هر فیلدی که ادمین از پانل پر کرده
```

---

## احراز هویت

```
Authorization: Bearer {INTERNAL_API_TOKEN}
Accept: application/json
```

درخواست‌های بدون توکن یا توکن نامعتبر باید `401` برگردانند.

---

## ۱. اندپوینت‌های مورد نیاز

| اولویت | متد | مسیر | توضیح |
|--------|-----|-------|-------|
| **اول** | GET | `/v1/catalog/brands/{slug}` | CMS override برند |
| **اول** | GET | `/v1/catalog/devices/{slug}` | CMS override دستگاه |
| دوم | GET | `/v1/catalog/brands` | لیست برندها (برای SSG) |
| دوم | GET | `/v1/catalog/devices` | لیست دستگاه‌ها (برای SSG) |

---

## ۲. GET /v1/catalog/brands/{slug}

### پاسخ موفق — `200 OK`

```json
{
  "id": 1,
  "name": "Samsung",
  "slug": "samsung",
  "logo": "https://cdn.example.com/brands/samsung.svg",
  "tagline": "تعمیر تخصصی محصولات سامسونگ با گارانتی ۱۸۰ روزه",
  "description": "...",
  "tone": "#1428A0",
  "bg": "#EEF2FF",
  "stats": [{"value": "۱۲+", "label": "سال تجربه"}],
  "issues": [{"title": "...", "description": "...", "icon": "Zap"}],
  "why_us": [{"title": "...", "description": "...", "icon": "ShieldCheck"}],
  "faq": [{"question": "...", "answer": "..."}],
  "meta_title": "تعمیر لوازم خانگی سامسونگ | تعمیرآنلاین",
  "meta_description": "..."
}
```

### پاسخ خطا

- `404` اگر brand یافت نشد یا `is_active=false`

---

## ۳. GET /v1/catalog/devices/{slug}

### پاسخ موفق — `200 OK`

```json
{
  "id": 3,
  "label": "لباسشویی",
  "slug": "washing-machine",
  "name": "تعمیر لباسشویی",
  "short_name": "لباسشویی",
  "description": "...",
  "service_name": "تعمیر لباسشویی",
  "technician_name": "تکنسین لباسشویی",
  "starting_price": 150000,
  "accent": "#3B82F6",
  "bg": "#EFF6FF",
  "icon": "washing-machine",
  "thumbnail": "https://.../wm.png",
  "tone": "tone-blue",
  "issues": [{"title": "...", "description": "..."}],
  "faq": [{"question": "...", "answer": "..."}],
  "meta_title": "...",
  "meta_description": "..."
}
```

### پاسخ خطا

- `404` اگر device یافت نشد یا `is_active=false`

---

## ۴. منطق اعمال CMS در فرانت

```
فیلد نهایی = اگر (cms_field != null و cms_field != "") → cms_field
             وگرنه → fixture_field
```

برای آرایه‌ها:

```
آرایه نهایی = اگر (cms_array.length > 0) → cms_array
              وگرنه → fixture_array
```

---

## ۵. وضعیت پیاده‌سازی (پر شده توسط تیم بک‌اند)

| § | مورد | وضعیت | محل پیاده‌سازی |
|---|------|--------|----------------|
| ۲ | `GET /v1/catalog/brands/{slug}` | ✅ | `CatalogBrandController::show()` |
| ۳ | `GET /v1/catalog/devices/{slug}` | ✅ | `CatalogDeviceController::show()` |
| ۴ | `GET /v1/catalog/brands` (LIST) | ✅ | `CatalogBrandController::index()` |
| ۵ | `GET /v1/catalog/devices` (LIST) | ✅ | `CatalogDeviceController::index()` |
| ۱ | Bearer auth برای detail endpoints | ✅ | `internal.token` middleware |
| ۶ | Migration ستون‌های CMS برای brand | ✅ | `2026_05_23_001_add_cms_columns_to_crm_brands` |
| ۶ | Migration ستون‌های CMS برای device | ✅ | `2026_05_23_002_add_cms_columns_to_crm_devices` |
| ۷ | پانل ادمین برای ویرایش CMS | ✅ | `/admin/crm/brands/{id}/edit` و `/admin/crm/devices/{id}/edit` |
| ۸ | Revalidation webhook trigger | ⏸️ | پیاده نشده — در روادمپ |

### نکات پیاده‌سازی

**۱. منبع داده:** برخلاف توصیه‌ی §۱۰ اسپک (جدول جداگانه `catalog_brands`)،
از جدول‌های موجود `crm_brands` و `crm_devices` استفاده شد — تا یک منبع
حقیقت برای brand/device بمونه (هم CRM هم Site از همین یکی می‌خونن).

**۲. Auth:** detail endpoints پشت `internal.token` (طبق اسپک). list endpoints
الان public هستن (backward compat). اگه فرانت BFF می‌خواد روی list هم
token بفرسته، مشکلی نیست — اضافه کردنش به middleware ساده‌ست.

**۳. فرمت تاریخ:** برخلاف contact-messages که `created_at` در پاسخ هست،
catalog detail endpoints `created_at` ندارن (طبق اسپک).

**۴. Cache:** `s-maxage=600` (۱۰ دقیقه) برای detail و list — مطابق §۶ اسپک.

**۵. ستون `tone` در device:** قبلاً برای CSS class بود (`tone-blue`). اسپک
از `accent` (hex color) استفاده می‌کنه که جداگانه اضافه شد. هر دو در API
خروجی برمی‌گردن.

**۶. JSON columns:** Laravel به‌صورت خودکار JSON رو parse می‌کنه (با cast
`'array'` در model). validation rules در controller هر آیتم repeater
رو چک می‌کنن.

**۷. آرایه‌های خالی:** اگه ادمین repeater رو خالی بذاره، controller
ردیف‌های بدون مقدار رو حذف می‌کنه. اگه همه ردیف‌ها خالی، فیلد `null`
می‌شه (نه `[]`) — مطابق رفتار مورد انتظار اسپک (null یا empty = fixture).

---

## ۶. تست با curl

```bash
# توکن از .env
TOKEN=$(grep '^INTERNAL_API_TOKEN=' .env | cut -d= -f2)

# brand
curl -s http://127.0.0.1:8000/v1/catalog/brands/samsung \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# device
curl -s http://127.0.0.1:8000/v1/catalog/devices/washing-machine \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# brand یافت نشد → 404
curl -i -s http://127.0.0.1:8000/v1/catalog/brands/nonexistent \
  -H "Authorization: Bearer $TOKEN"
```

---

## ۷. روادمپ

موارد باقی‌مانده برای فاز بعد:

1. **Webhook revalidation** — وقتی ادمین brand/device رو ذخیره می‌کنه،
   یک POST به `${FRONTEND_URL}/api/revalidate` با تگ `brand:{slug}`
   ارسال بشه. نیاز به `REVALIDATE_SECRET` در `.env`.
2. **Image uploader برای logo brand و thumbnail device** — قبلاً پیاده
   شده (با URL paste یا file upload).
3. **List endpoints پشت `internal.token`** — اگه فرانت تصمیم گرفت همه‌ی
   catalog رو internal-only کنه.
