# مستند کامل اتصال صفحه دستگاه به بک‌اند

> نسخه: 1.0 | تاریخ: 1404/03/02

این فایل verbatim spec فرانت‌اند برای صفحه‌ی دستگاه است. ضمیمه‌ی §۱۲ وضعیت پیاده‌سازی هر مورد و معماری template+override را توضیح می‌دهد.

---

## نقشه کلی صفحه

```
/devices/{slug}
│
├── 1. Hero              ← GET /v1/catalog/devices/{slug}
├── 2. Steps             ← Static
├── 3. LiveActivity      ← GET /v1/activity/recent?device={slug}
├── 4. DeviceContent     ← GET /v1/catalog/devices/{slug}
├── 5. DeviceFaq         ← GET /v1/catalog/devices/{slug}
├── 6. CommentsSection   ← GET/POST /v1/catalog/devices/{slug}/reviews
│                          POST /v1/catalog/reviews/{id}/like
├── 7. DeviceBrands      ← GET /v1/catalog/brands?device={slug}
├── 8. Testimonials      ← GET /v1/testimonials  (✅ موجود)
└── 9. GlobalPromoBanner ← Static
```

---

## احراز هویت

| نوع | احراز هویت |
|------|------------|
| GET catalog/devices/{slug} | Bearer `INTERNAL_API_TOKEN` |
| GET activity/recent, testimonials | Public |
| GET reviews | Public |
| POST review submit | Public (با throttle:5,1) |
| POST review like | Public (با throttle:30,1) |

---

## ۱۲. وضعیت پیاده‌سازی (پر شده توسط تیم بک‌اند)

| § | مورد | وضعیت | محل پیاده‌سازی |
|---|------|--------|----------------|
| ۱ | `GET /v1/catalog/devices/{slug}` | ✅ | `CatalogDeviceController::show()` با merge logic |
| ۲ | `GET /v1/activity/recent?device=` filter | ✅ | `ActivityController::recent()` — alias `?device` پشتیبانی شده |
| ۳ | `GET /v1/catalog/devices/{slug}/reviews` | ✅ | `DeviceReviewController::index()` |
| ۴ | `POST /v1/catalog/devices/{slug}/reviews` | ✅ | `DeviceReviewController::store()` |
| ۵ | `POST /v1/catalog/reviews/{id}/like` | ✅ | `DeviceReviewController::like()` (IP-based unique) |
| ۶ | `GET /v1/catalog/brands?device=` filter | ✅ | `CatalogBrandController::index()` با pivot `crm_device_brands` (`whereHas('devices')`) |
| ۷ | `warranty_text`, `support_info` در device detail | ✅ | ستون‌های جدید روی `crm_devices` + Template/Override merge |
| ۸ | جدول reviews | ✅ | جدول واحد `site_reviews` (migration `2026_05_23_030`) با `type=audio|text` — جایگزین `testimonials` و `site_device_reviews` |
| ۹ | جدول replies | ✅ | `site_review_replies` (پس از یکپارچه‌سازی) |
| ۱۰ | پانل ادمین برای مدیریت نظرات | ✅ | `/admin/site/reviews` (یکپارچه: audio + text، با تب type و فیلتر status/device) |
| ۱۱ | Like rate-limit IP-based | ✅ | جدول `site_review_likes` با unique(review_id, ip) |
| ۱۲ | Permissions جدید | ✅ | `view-site-reviews`, `manage-site-reviews` (legacyهای قبلی هم نگه داشته شده‌اند) |
| ۱۳ | فیلدهای hero (`subtitle`, `eyebrow`) | ✅ | migration `2026_05_23_020` + merge در `CatalogDeviceController::show()` |
| ۱۴ | `service_steps` repeater | ✅ | ستون JSON روی `crm_devices` + section در template + merge |
| ۱۵ | brand↔device pivot | ✅ | جدول `crm_device_brands` (migration `2026_05_23_021`) — جایگزین `supported_device_slugs` JSON |
| ۱۶ | testimonial↔device pivot (per-device filter) | ✅ | جدول `site_review_devices` با fallback به generic |
| ۱۷ | `/v1/settings/global` | ✅ | `SettingsController::global()` — phone/order_url/social از `site_settings` |
| ۱۸ | HTML rich editor روی device/brand description | ✅ | TinyMCE self-hosted در `/vendor/tinymce/` + sanitize allowlist روی backend (`Modules\CRM\Support\HtmlSanitizer`) |

---

## ۱۳. معماری Template + Per-Instance Override

این بزرگ‌ترین تصمیم معماری این spec است. مشکل اصلی:

> «صفحات من خیلی مفاهیم بهم نزدیکی دارند. محتوا یکسان است مثلاً یک متن در همه‌ی صفحات تکرار می‌شود اما فقط طبق اطلاعات اون دستگاه این متن در بعضی قسمت‌های من جایگذاری می‌شود.»

### ۱۳.۱ راه‌حل

دو لایه‌ی محتوا برای هر دستگاه/برند:

1. **Template** (پیش‌فرض همه دستگاه‌ها): در `site_page_sections` با `page_slug='device'` ذخیره می‌شود.
   - متن می‌تواند شامل **placeholder** باشد: `{device}`, `{device_label}`, `{device_slug}`, `{page_title}`
   - ادمین در `/admin/site/page-content/device` یک‌بار آن را پر می‌کند
   - برای brand: `page_slug='brand'` با placeholderهای `{brand}`, `{brand_slug}`

2. **Per-Instance Override** (اختصاصی هر دستگاه/برند): در ستون‌های CMS روی `crm_devices` / `crm_brands`.
   - ادمین در `/admin/crm/devices/{id}/edit` می‌تواند هر فیلد را override کند
   - فیلدهای null یا آرایه‌ی خالی = "از template استفاده کن"

### ۱۳.۲ منطق merge

در `CatalogDeviceController::show()`:

```php
$template = $sectionsService->loadForPublic('device', [
    'device'       => $device->short_name ?? $device->name,
    'device_label' => $device->name,
    'device_slug'  => $device->slug,
    'page_title'   => $device->service_name ?? $device->name,
]);

return [
    'service_name'  => CatalogMerger::pick($device->service_name, $template['identity']['service_name']),
    'warranty_text' => CatalogMerger::pick($device->warranty_text, $template['support']['warranty_text']),
    'issues'        => CatalogMerger::pick($device->issues, $template['issues']['items']),
    'faq'           => CatalogMerger::pick($device->faq, /* از category_ids_items یا faq_ids_items */),
    // ...
];
```

`CatalogMerger::pick()` قانون:

- `null` → fallback به template
- رشته‌ی خالی → fallback
- آرایه‌ی خالی → fallback
- در غیر این صورت → override

### ۱۳.۳ مثال عملی

ادمین در template صفحه‌ی device می‌نویسد:

```
service_name:  "تعمیر {device}"
warranty_text: "تمام تعمیرات {device} با گارانتی ۱۸۰ روزه ارائه می‌شود."
```

برای دستگاه «لباس‌شویی» (slug=`washing-machine`، name=`لباس‌شویی`):

API برمی‌گرداند:
```json
{
  "service_name":  "تعمیر لباس‌شویی",
  "warranty_text": "تمام تعمیرات لباس‌شویی با گارانتی ۱۸۰ روزه ارائه می‌شود."
}
```

برای دستگاه «ظرفشویی» (slug=`dishwasher`، name=`ظرفشویی`):

```json
{
  "service_name":  "تعمیر ظرفشویی",
  "warranty_text": "تمام تعمیرات ظرفشویی با گارانتی ۱۸۰ روزه ارائه می‌شود."
}
```

اگر ادمین برای دستگاه ظرفشویی به‌صورت اختصاصی `service_name` را در `/admin/crm/devices/{id}/edit` پر کند با مقدار `"تعمیر فوق‌العاده ظرفشویی"`:

API برمی‌گرداند:
```json
{
  "service_name": "تعمیر فوق‌العاده ظرفشویی"  // override استفاده شد
}
```

### ۱۳.۴ FAQ از مخزن مشترک

برای FAQ، الگوی template می‌تواند:
- دسته‌بندی‌هایی از `site_taxonomies` (type=faq) را انتخاب کند → خروجی به‌صورت تب
- یا سوالات منفرد را انتخاب کند → خروجی لیست تخت

`CatalogMerger::templateFaq()` هر دو را به یک آرایه‌ی تخت ادغام می‌کند.

placeholderها در متن سوال/پاسخ به‌صورت خودکار با context صفحه substitute می‌شوند.

---

## ۱۴. اندپوینت‌های جدید — مرجع کوتاه

### `GET /v1/catalog/devices/{slug}/reviews`

```bash
curl -s 'http://127.0.0.1:8000/v1/catalog/devices/washing-machine/reviews?page=1&limit=10&sort=newest' | jq .
```

پاسخ:
```json
{
  "data": [
    {
      "id": "01HX...",
      "author_name": "علی",
      "author_avatar": null,
      "is_verified": false,
      "is_expert": false,
      "rating": 5,
      "content": "...",
      "created_at": "2026-05-20T10:30:00Z",
      "likes": 12,
      "reply": null
    }
  ],
  "meta": { "total": 47, "page": 1, "limit": 10, "last_page": 5, "average_rating": 4.8 }
}
```

### `POST /v1/catalog/devices/{slug}/reviews`

```bash
curl -X POST http://127.0.0.1:8000/v1/catalog/devices/washing-machine/reviews \
  -H "Content-Type: application/json" \
  -d '{"author_name":"سارا","email":"s@example.com","rating":5,"content":"سرویس خوبی بود."}'
```

پاسخ 201:
```json
{
  "id": "01HX...",
  "status": "pending",
  "message": "نظر شما دریافت شد و پس از بررسی نمایش داده می‌شود."
}
```

### `POST /v1/catalog/reviews/{id}/like`

```bash
curl -X POST http://127.0.0.1:8000/v1/catalog/reviews/01HX.../like
```

پاسخ:
```json
{ "likes": 13 }
```

هر IP فقط یک‌بار می‌تواند هر نظر را لایک کند (یونیک ایندکس).

---

## ۱۵. روادمپ

موارد باقی‌مانده:

1. **Email notification** برای ادمین وقتی نظر جدید ثبت می‌شود
2. **Webhook revalidation** فرانت Next.js بعد از تأیید/رد نظر
3. **Soft delete** برای reviews (به‌جای hard delete)
4. **Bulk approve/reject** در پنل ادمین (الان فقط یکی-یکی)
5. **Spam detection** ساده با Akismet یا regex-based filter
