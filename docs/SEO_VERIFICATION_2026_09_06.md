# گزارشِ تأیید — نسخهٔ Production، وضعیتِ دو خدمت، تطبیقِ سایت‌مپ

**تاریخ:** ۲۰۲۶-۰۹-۰۶ (UTC) · **دامنه:** tamironline.com (فرانتِ Next.js) / panel.tamironline.com (بک‌اندِ لاراول)
**روش:** GET واقعیِ عمومی + بررسیِ کدِ پنل + سایت‌مپِ authoritative. مواردی که به دسترسیِ سرور/CI نیاز دارند صریح علامت خورده‌اند.

---

## ۱) نسخهٔ واقعیِ Production

### این کامیت‌ها مالِ کدامند؟
هر سه کامیت **در ریپوی پنل نیستند** → متعلق به **ریپوی فرانت (Next.js)‌اند**:
- `96ee143` — robots/صفحاتِ ناموجود
- `1cdead4` — مسیرِ سایت‌مپِ مقالات
- `5daf483` — ریدایرکتِ مسیرِ قدیمیِ مایکروویو سامسونگ

### شواهدِ رفتاریِ زنده (۲۰۲۶-۰۹-۰۶)
| کامیت | رفتارِ موردانتظار | نتیجهٔ زنده | وضعیت |
|---|---|---|---|
| `1cdead4` | سایت‌مپِ بلاگ در دسترس | `sitemap-blog-1/-2.xml` = **200** (۲۲۷ + ۲۲۷) | ✅ فعال |
| `5daf483` | ریدایرکتِ مسیرِ مایکروویو سامسونگ | `/services/samsung-microwave-repair/` → **308** → `/services/samsung-microwave-repair` (۲۰۰) | ✅ فعال |
| `96ee143` | صفحاتِ ناموجود noindex شوند | دو ترکیبِ ناموجود اکنون `<meta name="robots" content="noindex">` دارند؛ ترکیبِ واقعی (bosch) `index,follow` | ⚠️ نیمه — **robots درست شد ولی HTTP هنوز ۲۰۰ (soft-404)** است، نه ۴۰۴ واقعی |

### ❗ محدودیت (طبقِ خواستهٔ نامه)
رفتار ≠ اثباتِ نسخه. **SHA بیلد / شناسهٔ ایمیج/کانتینر / زمانِ دقیقِ دیپلوی از طریقِ HTTP قابلِ استخراج نیست:**
- در HTML `buildId` نیست (App Router)، `/api/health` فقط `{ok, ts}` می‌دهد، هیچ هدرِ commit/version نیست؛ `etag: "j6ilhey72xboej"` صرفاً هشِ محتوای صفحهٔ اصلی است، نه نسخه.
- این‌ها **روی سرور/CIِ فرانت** به‌دست می‌آیند. دستورهای پیشنهادی (روی هاستِ فرانت):
  ```bash
  # اگر داکر است:
  docker ps --format '{{.ID}}  {{.Image}}  {{.CreatedAt}}  {{.Status}}'
  docker inspect <container> --format '{{.Id}} | image={{.Image}} | started={{.State.StartedAt}}'
  docker image inspect <image> --format '{{.Id}} | created={{.Created}} | labels={{.Config.Labels}}'
  # SHAی بیلدشده داخلِ کانتینر (اگر در build ذخیره شده):
  docker exec <container> sh -lc 'cat .next/BUILD_ID 2>/dev/null; git rev-parse HEAD 2>/dev/null; cat build-info.json 2>/dev/null'
  ```
  پیشنهادِ پایدار: یک endpointِ `/api/version` که `{ commit, buildId, builtAt }` بدهد تا از این پس نسخه از بیرون قابلِ اثبات باشد.

**جمع‌بندیِ بند ۱:** `1cdead4` و `5daf483` رفتارشان در Production دیده می‌شود؛ `96ee143` فقط نیمهٔ robots‌اش فعال است و صفحاتِ ناموجود هنوز HTTP 200 برمی‌گردانند (باید ۴۰۴ شوند). اثباتِ قطعیِ نسخه منوط به خروجیِ سرور/CIِ فرانت است.

---

## ۲) وضعیتِ واقعیِ دو خدمت

| | `/services/vacuum-cleaner/philips` | `/services/wall-mounted-boiler/lorch` |
|---|---|---|
| دستگاه (device) | `vacuum-cleaner` **موجود** (چند ترکیبِ منتشرشده: lg, bosch, …) | `wall-mounted-boiler` **موجود** (ariston, bosch, iran-radiator, …) |
| برند (brand) | `philips` **موجود** (microwave/philips, tv/philips, solardom/philips) | `lorch` **موجود** (water-heater/lorch) |
| **ترکیبِ منتشرشده؟** | **خیر** — در `sitemap-services-combo.xml` (۳۶۲ ترکیبِ منتشرشده) نیست | **خیر** — در همان لیست نیست |
| قراردادِ API پنل | `CatalogDeviceBrandController@show` برای ترکیبِ ناموجود/غیرفعال **۴۰۴** برمی‌گرداند (کدِ خطوط ۳۵ و ۴۸) | همان |
| پاسخِ فرانت (عمومی) | **HTTP 200** + `robots: noindex` + محتوای «۴۰۴» (soft-404) | همان |

**نتیجه:** هر دو **دستگاه و برندشان واقعی‌اند**، ولی **ترکیبِ منتشرشده ندارند**؛ پس API پنل درست ۴۰۴ می‌دهد و نبودشان در سایت‌مپ صحیح است. HTTP 200 سمتِ فرانت است (soft-404). این نتیجه از **لیستِ authoritativeِ ترکیب‌های منتشرشده + قراردادِ کد** گرفته شده، **نه** از timeoutِ API (API پنل از سندباکسِ من مقطعی timeout داشت؛ آن را «عدمِ وجود» تفسیر نکردم).

### تأییدِ authoritativeِ داده (روی سرورِ پنل — tinker)
برای «رکورد هست/نیست، پیش‌نویس یا منتشر، slug و اتصالِ دستگاه/برند، محتوای اختصاصی» این را اجرا کنید:
```php
/opt/alt/php84/usr/bin/php artisan tinker --execute='
foreach ([["vacuum-cleaner","philips"],["wall-mounted-boiler","lorch"]] as [$d,$b]) {
  $dev = \Modules\CRM\Models\Device::where("slug",$d)->first();
  $brand = \Modules\CRM\Models\Brand::where("slug",$b)->first();
  $page = ($dev && $brand) ? \Modules\CRM\Models\DeviceBrandPage::where("device_id",$dev->id)->where("brand_id",$brand->id)->first() : null;
  echo "$d/$b => device:".($dev?"#{$dev->id}(active=".(int)$dev->is_active.")":"—").
       " brand:".($brand?"#{$brand->id}(active=".(int)$brand->is_active.")":"—").
       " page:".($page?"#{$page->id}(active=".(int)$page->is_active.")":"NONE").
       ($page? " content_len=".mb_strlen((string)($page->content ?? "")) : "")."\n";
}'
```
اگر اتصالِ داده اشتباه بود (مثلاً برند/دستگاه به هم وصلِ غلط)، همان‌جا اصلاح می‌شود. اگر مجموعه واقعاً این خدمت را ارائه می‌دهد ولی صفحه‌اش منتشر نشده، ادمین باید صفحهٔ ترکیبی را بسازد/منتشر کند؛ آن‌گاه خودکار وارد سایت‌مپ و لینک‌ها می‌شود.

---

## ۳) تطبیقِ سایت‌مپ

- **`sitemap.xml`** به هر دو نقشهٔ بلاگ ارجاع می‌دهد:
  `…/sitemaps/sitemap-blog-1.xml` و `…/sitemaps/sitemap-blog-2.xml` (به‌همراهِ core/local/services-devices/services-combo/brands/forum).
- **شمارش (GET زنده):** blog-1 = **۲۲۷**، blog-2 = **۲۲۷**، جمع = **۴۵۴**، **یکتا = ۴۵۴** (هیچ URLِ تکراری بینِ دو فایل).
- **تولیدکنندهٔ واقعی:** **بک‌اندِ لاراول (پنل)** — `Modules\Seo\Services\SitemapBuilder` از مسیرِ `/v1/seo/sitemap.xml` و `/v1/seo/sitemaps/sitemap-{name}.xml`؛ فرانت این‌ها را rewrite/proxy می‌کند. (ساختِ خامِ هر فایل روی پنل ~۰٫۶ ثانیه، از دیتابیس.)
- **تطبیق با پنل:** مقالاتِ سایت‌مپ = ۴۵۴ یکتا. شمارشِ «مقالاتِ منتشرشدهٔ» پنل هم قبلاً روی سرور **۴۵۴** بود → **بدونِ اختلاف**. ملاکِ ورود به سایت‌مپ: `published_at ≤ now` + بدونِ `noindex` + canonical به‌خودِ صفحه.

### تأییدِ authoritativeِ تطبیق (روی سرورِ پنل)
```php
/opt/alt/php84/usr/bin/php artisan tinker --execute='
$pub = \Modules\Site\Models\Article::whereNotNull("published_at")->where("published_at","<=",now())->count();
$map = count(app(\Modules\Seo\Services\SitemapBuilder::class)->specUrls("blog-1"))
     + count(app(\Modules\Seo\Services\SitemapBuilder::class)->specUrls("blog-2"));
echo "published=$pub | in_sitemap=$map | diff=".($pub-$map)."\n";'
```
اگر `diff` صفر نبود، اختلاف = مقالاتی که `noindex` خورده‌اند یا canonicalشان به URLِ دیگری اشاره می‌کند (عمداً حذف می‌شوند) — این‌ها را می‌توان تک‌تک فهرست کرد.

---

## جمع‌بندیِ خروجی
1. **نسخه:** سه کامیت مالِ فرانت‌اند؛ `1cdead4`/`5daf483` رفتارشان زنده است، `96ee143` فقط robots را درست کرده و صفحاتِ ناموجود هنوز HTTP 200‌اند (باید ۴۰۴). اثباتِ قطعیِ SHA/ایمیج/زمانِ دیپلوی → از سرور/CIِ فرانت (دستورها بالا).
2. **دو خدمت:** دستگاه و برندشان واقعی؛ ترکیبِ منتشرشده ندارند؛ API پنل درست ۴۰۴ می‌دهد؛ HTTP 200 مالِ فرانت است. اصلاح: یا فرانت این حالت را ۴۰۴ کند، یا اگر خدمت واقعاً ارائه می‌شود ادمین صفحهٔ ترکیبی را منتشر کند (سپس خودکار در سایت‌مپ/لینک‌ها می‌آید).
3. **سایت‌مپ:** `sitemap.xml` به هر دو نقشهٔ بلاگ ارجاع دارد؛ ۲۲۷+۲۲۷=۴۵۴ یکتا؛ تولیدکننده = پنلِ لاراول؛ تطبیق با مقالاتِ منتشرشده بدونِ اختلاف (۴۵۴).
