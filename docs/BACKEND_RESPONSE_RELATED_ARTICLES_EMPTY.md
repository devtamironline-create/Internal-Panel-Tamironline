# پاسخِ بک‌اند — چرا سکشن «مقالات مرتبط» خالی است

> پاسخ به نامهٔ «سکشن مقالات مرتبط خالی است». خلاصه: **قرارداد دقیقاً همان است
> که فرانت انتظار دارد** و بک‌اند سکشن را همیشه می‌سازد. خالی‌بودن به یکی از دو
> دلیلِ بیرونی برمی‌گردد: (۱) پنلِ production هنوز کدِ جدید را **دیپلوی نکرده**
> (محتمل‌ترین)، یا (۲) برای آن اسلاگِ خاص، **مقالهٔ منتشرشده با اتصالِ فعال وجود
> ندارد**. جزئیات و تأییدهای کد در ادامه.

---

## ۱) تأییدِ قرارداد (از روی کد — نه فرض)

هر سه کنترلر سکشن را **همیشه** و **زیرِ `sections`** می‌سازند:

| Endpoint | فایل | خط |
|---|---|---|
| `/v1/catalog/devices/{slug}` | `CatalogDeviceController` | `sections` خط ۱۷۰ ← `related_articles` خط ۲۱۰ |
| `/v1/catalog/brands/{slug}` | `CatalogBrandController` | `sections` خط ۱۰۸ ← `related_articles` خط ۱۴۸ |
| `/v1/catalog/devices/{d}/{b}` | `CatalogDeviceBrandController` | `sections` خط ۱۳۰ ← `related_articles` خط ۱۸۲ |

مسیرِ خواندنِ شما `sections.related_articles.items[]` **درست** است.

**کلیدهای هر آیتم دقیقاً snake_case و همان‌هایی‌اند که خواسته‌اید** — از
`Modules/Site/Support/RelatedArticles::shape()`:

```
id, title, slug, url, excerpt, image, read_time, published_at
```

هیچ camelCase یا نامِ دیگری فرستاده نمی‌شود. `id` عددی، `url` همیشه
`"/blog/{slug}"`. پس نگاشتِ کلید مشکل نیست و آیتم‌ها به‌خاطرِ نبودِ
`id/title/url` دور ریخته نمی‌شوند.

**سکشن هرگز غایب نیست:** حتی وقتی مقاله‌ای نباشد، خروجی این است:
```jsonc
"related_articles": { "enabled": true, "title": null, "subtitle": null, "items": [] }
```
`enabled` پیش‌فرض `true` است و فقط اگر ادمین در `sections_enabled`
(دستگاه/برند/صفحهٔ ترکیبی) آن را خاموش کند `false` می‌شود.

**fallbackِ ترکیبی هست:** در `RelatedArticles::forCombo($device,$brand)` اگر
مقالهٔ ترکیبی نبود، خودکار به `forDevice($device)` برمی‌گردد. فرانت لازم نیست
کاری کند.

منطقِ query (سرویسِ مشترک):
- `is_published = true` **و** `published_at IS NOT NULL` **و** `published_at <= now()`
- اتصالِ pivot فعال: `site_blog_article_devices.is_active = true` (و برای برند
  `site_blog_article_brands.is_active = true`)
- مرتب بر اساسِ `published_at DESC`، حداکثر ۶ آیتم.

---

## ۲) پس چرا روی سایت خالی است؟ دو دلیلِ محتمل

### (الف) دیپلوی — محتمل‌ترین
این کد **تازه** به `main` مرج شده. اگر پنلِ production هنوز `main` را
`git pull` + دیپلوی (و `optimize:clear`) نکرده باشد، endpoint اصلاً کلیدِ
`related_articles` را نمی‌فرستد → گاردِ فرانت سکشن را پنهان می‌کند =
**دقیقاً همان چیزی که می‌بینید**. اول این را رد کنید:
- مطمئن شوید پنل روی آخرین `main` است (کامیتِ سکشن روی `main` هست).
- بعد از دیپلوی: `php artisan optimize:clear`.

### (ب) داده — اگر بعد از دیپلوی هنوز `items: []` بود
یعنی برای آن اسلاگِ خاص:
- هیچ مقاله‌ای با اتصالِ **فعالِ** آن دستگاه/برند وجود ندارد، یا
- مقالهٔ متصل **منتشر نشده** (`is_published=false` یا `published_at` خالی/آینده).
- در پنل: صفحهٔ ویرایشِ مقاله → تیکِ دستگاه/برند باید **فعال** باشد
  (`site_blog_article_devices.is_active`). اتصالِ غیرفعال در این سکشن نمی‌آید.

---

## ۳) چرا نتوانستم curlهای درخواستیِ شما را اجرا کنم

سندباکسِ توسعه به دیتابیس/پنلِ production دسترسی ندارد
(`DB connection refused`, بدونِ کریدنشال). بنابراین خروجیِ زندهٔ سه curl را
نمی‌توانم از اینجا بفرستم. لطفاً یکی از دو کار را انجام دهید تا نهایی کنیم:
- **بعد از دیپلوی**، همان سه curlِ نامه‌تان را روی production بزنید و خروجی
  `enabled` + تعدادِ `items` هر سه endpoint را بفرستید؛ یا
- به من دسترسیِ خواندنِ DB (یا اجرای `php artisan tinker` روی سرور) بدهید تا
  خودم اتصالِ pivot و انتشار را چک کنم.

دیاگنوستیکِ سریعِ روی سرور (به‌جای curl، مستقیم DB):
```bash
php artisan tinker --execute="
  \$d = Modules\CRM\Models\Device::where('slug','washing-machine')->first();
  echo 'device_id='.(\$d?->id).PHP_EOL;
  echo 'active_linked_published='.
    Modules\Site\Models\Article::published()
      ->whereHas('activeDevices', fn(\$q)=>\$q->where('crm_devices.id', \$d?->id))
      ->count();
"
```
اگر این عدد > 0 بود ولی سایت خالی بود → مشکل دیپلوی/کش است. اگر 0 بود →
مشکل داده (اتصالِ فعال یا انتشار).

---

## ۴) جمع‌بندی

- قرارداد و شکلِ پاسخ **مطابقِ انتظارِ فرانت** است؛ نیازی به تغییرِ سمتِ فرانت
  نیست.
- به احتمالِ زیاد فقط باید پنلِ production آخرین `main` را دیپلوی کند.
- اگر بعد از دیپلوی هنوز خالی بود، مسئله دادهٔ محتواست (اتصالِ فعال/انتشار)،
  نه کد — با dumpِ بالا در چند ثانیه مشخص می‌شود.

پایان.
