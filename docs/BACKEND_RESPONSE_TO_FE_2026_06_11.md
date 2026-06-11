# پاسخ به نامه تیم فرانت — وضعیت endpointها

> تاریخ: 2026-06-11
> پاسخ به: درخواست‌های backend برای brands، categories، banners، objections، reviews/tags، invoice، bootstrap

---

## TL;DR

| Endpoint | وضعیت | اقدام |
|---|---|---|
| `GET /v1/customer/services/brands` | ✅ آماده | فقط patch URL absolute |
| `GET /v1/customer/services/categories` | ✅ آماده | فقط patch URL absolute |
| `GET /v1/customer/services/banners` | ✅ آماده | فقط patch URL absolute |
| `GET /v1/customer/services/objections?device_id=X` | ✅ آماده | **device_id حالا اجباری است** |
| `GET /v1/customer/reviews/tags` | ✅ آماده | (در patch قبلی ساخته شد) |
| `POST /v1/customer/orders/{id}/review` با `tag_ids[]` | ✅ آماده | (در patch قبلی ساخته شد) |
| `GET /v1/customer/orders/pending-reviews` | ✅ آماده | از مدت‌ها قبل |
| `GET /v1/customer/bootstrap` | ✅ آماده | از مدت‌ها قبل، شامل time_slots/cancel_reasons/holidays/service_types |

**خلاصه:** هیچ endpointی نیست که نیاز به پیاده‌سازی جدید داشته باشد. فقط ۳ باگ گزارش‌شده توسط شما fix شد.

---

## جزئیات fixهای patch جدید

### Bug fix #1: URL مطلق برای رسانه‌ها

قبلاً اگر مقدار `logo` یا `thumbnail` یا `image_url` در DB به‌صورت مسیر مطلق نسبی ذخیره‌شده بود (مثل `/storage/brands/x.png`)، API همان را برمی‌گرداند بدون اضافه‌کردن domain.

**حالا** `MediaUrl::resolve()` در همه‌ی حالت‌ها URL کامل تحویل می‌دهد:

```jsonc
// قبل (باگ)
{ "logo": "/storage/brands/samsung.png" }

// بعد (درست)
{ "logo": "https://panel.tamironline.com/storage/brands/samsung.png" }
```

این اصلاح **همه‌ی** فیلدهای رسانه را در `brands`، `categories`، `banners` در یک‌جا fix می‌کند.

### Bug fix #2: Banner.image_url

اگر بنری بدون `media_id` ولی با `image_url` خام در DB ذخیره شده بود، fallback path مستقیم می‌رفت و نسبی می‌ماند. حالا از `MediaUrl::resolve` عبور می‌کند.

### Bug fix #3: objections → device_id اجباری

دیگر `GET /v1/customer/services/objections` بدون پارامتر نمی‌تواند فراخوانی شود.

```jsonc
// درست
GET /v1/customer/services/objections?device_id=12
→ 200
{
  "success": true,
  "data": [
    { "id": 5, "slug": "...", "name": "روشن نشدن دستگاه", "description": "...", "icon": null }
  ],
  "meta": { "device_id": 12, "total": 15 }
}

// بدون device_id
GET /v1/customer/services/objections
→ 422
{
  "success": false,
  "message": "برای دیدن لیست ایرادات، باید ابتدا دستگاه انتخاب شود.",
  "code": "device_id_required"
}

// device_id نامعتبر
GET /v1/customer/services/objections?device_id=9999
→ 422
{
  "success": false,
  "message": "دستگاه انتخاب‌شده معتبر نیست.",
  "code": "invalid_device"
}
```

**اقدام لازم در فرانت:** مطمئن شوید endpoint را فقط زمانی صدا می‌زنید که `device_id` انتخاب‌شده دارید. در غیر این صورت 422 می‌گیرید.

---

## بقیه endpointها — هیچ تغییری نکرده

اگر استفاده شده باشد، همان قراردادی که قبلاً مستند شده هنوز معتبر است. مرجع‌های مستندات:

- `docs/MOBILE_API_CONTRACT.md` — قرارداد کامل API موبایل
- `docs/FRONTEND_REVIEW_TAGS_AND_BFF.md` — تگ‌های نظرسنجی + پیشنهاد BFF
- `docs/FRONTEND_OBJECTIONS_BANNERS_INVOICE.md` — راهنمای objections per-device + banners + فرمت فاکتور

---

## برای ادمین (شما)

برای فعال شدن این تغییرات روی production:

```bash
# pull آخرین کد
cd /path/to/project
git pull origin develop-site

# هیچ migration ای نیست برای این patch — فقط:
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

تستی که می‌توانید بزنید:

```bash
# باید 422 بدهد:
curl -s https://panel.tamironline.com/v1/customer/services/objections

# باید لیست بدهد:
curl -s "https://panel.tamironline.com/v1/customer/services/objections?device_id=12" | jq .meta.total

# باید URL مطلق بدهد:
curl -s "https://panel.tamironline.com/v1/customer/services/brands?category_id=12" | jq '.data[0].logo'
```
