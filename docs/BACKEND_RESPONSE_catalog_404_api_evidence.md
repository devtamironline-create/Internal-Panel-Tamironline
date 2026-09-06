# پاسخِ بک‌اند — بررسی نهاییِ API صفحاتِ ناموجود (شواهد)

**تاریخ:** ۲۰۲۶-۰۹-۰۶ · **دامنه:** فقط بک‌اند (پنل). اصلاحِ statusِ HTMLِ فرانت، رندرِ Next.js و Caddy خارج از این سند است.
**هیچ انتشار/تغییرِ داده‌ای انجام نشد** — این سند فقط رفتارِ واقعیِ API و شواهد است.

---

## خلاصهٔ حکم (از روی کدِ زنده، نه حدس)
API ترکیبی که فرانت مصرف می‌کند: `GET /v1/catalog/devices/{deviceSlug}/{brandSlug}`
→ `Modules/Site/Http/Controllers/Api/V1/CatalogDeviceBrandController@show`.

سه حالتِ خواسته‌شده **همین حالا درست پاسخ می‌دهند**؛ نیازی به اصلاح نیست:

| حالت | شرط | پاسخِ API | مرجع در کد |
|---|---|---|---|
| ۱ — منتشرشدهٔ واقعی | device فعال **و** brand فعال **و** `DeviceBrandPage` موجود **و** `is_active=true` | **۲۰۰** + دادهٔ همان صفحه | خطوط ۳۱–۴۹ سپس ۱۰۴ |
| ۲الف — device/brand نامعتبر | device یا brand نبود/غیرفعال | **۴۰۴** `{"message":"Not Found"}` | خطوط ۳۱–۳۶ |
| ۲ب — بدونِ صفحهٔ منتشرشده | جفت وجود دارد ولی صفحهٔ ترکیبی نیست یا `is_active=false` | **۴۰۴** `{"message":"این صفحه‌ی ترکیبی فعال نیست."}` | خطوط ۴۱–۴۹ |
| ۳ — خطای داخلی/دیتابیس | استثناء در کوئری/رندر | **۵xx** (Laravel) — **به ۴۰۴ تبدیل نمی‌شود** | هیچ try/catchی خطا را به ۴۰۴ نمی‌بلعد |

**نکتهٔ کلیدیِ نگرانیِ فرانت:** API در حالتِ ۲ **هرگز ۲۰۰ با دادهٔ خالی/عمومی نمی‌دهد** — همیشه ۴۰۴ با پیامِ مشخص. «عنوانِ عمومیِ برنامه» که فرانت روی این صفحات می‌بیند، از پاسخِ ۲۰۰ی بک‌اند نمی‌آید (پاسخِ بک‌اند ۴۰۴ است)؛ یعنی رندرِ عنوانِ عمومی سمتِ فرانت/کش است.

> منبعِ رابطهٔ device↔brand فقط `DeviceBrandPage`ِ فعال است (بدونِ fallbackِ pivotِ legacy). پس «نبودن در سایت‌مپ» و «۴۰۴ گرفتن از API» هر دو از یک منبع می‌آیند و سازگارند.

---

## ۱) وضعیتِ `washing-machine / lorch` — روی سرور اجرا کنید
«نبودن در سایت‌مپ» اثباتِ منتشرنشدن نیست؛ حقیقتِ رکورد را از دیتابیس بخوانید:

```bash
/opt/alt/php84/usr/bin/php artisan tinker --execute='
$pairs=[["washing-machine","lorch"],["vacuum-cleaner","philips"]];
foreach($pairs as [$ds,$bs]){
  $d=\Modules\CRM\Models\Device::where("slug",$ds)->first();
  $b=\Modules\CRM\Models\Brand::where("slug",$bs)->first();
  $p=($d&&$b)?\Modules\CRM\Models\DeviceBrandPage::where("device_id",$d->id)->where("brand_id",$b->id)->first():null;
  echo "{$ds}/{$bs} | device:".($d?("#".$d->id." active=".(int)$d->is_active):"—").
       " | brand:".($b?("#".$b->id." active=".(int)$b->is_active):"—").
       " | page:".($p?("#".$p->id." active=".(int)$p->is_active):"ندارد").PHP_EOL;
}'
```
سپس پاسخِ مستقیمِ API پنل (بدونِ کشِ CDN):
```bash
for u in washing-machine/lorch vacuum-cleaner/philips; do
  echo -n "$u → "; curl -s -o /dev/null -w "%{http_code}\n" https://panel.tamironline.com/v1/catalog/devices/$u
done
```
**تفسیر:** اگر `page:ندارد` یا `active=0` بود → API درست ۴۰۴ می‌دهد و صفحه واقعاً منتشرنشده است (باگ نیست). **این ترکیب را برای سبزکردنِ تست خودسرانه منتشر نکنید** — انتشار تصمیمِ محتوایی است، نه رفعِ خطا.

---

## ۲) جدولِ نمونه‌ها (شواهد برای فرانت) — با خروجیِ همین دستور پر می‌شود
```bash
# یک نمونهٔ حتماً معتبر (۲۰۰)، یک منتشرنشده (۴۰۴)، یک قطعاً نامعتبر (۴۰۴)
for u in vacuum-cleaner/philips washing-machine/lorch no-such-device/no-such-brand; do
  code=$(curl -s -o /dev/null -w "%{http_code}" https://panel.tamironline.com/v1/catalog/devices/$u)
  body=$(curl -s https://panel.tamironline.com/v1/catalog/devices/$u | head -c 200)
  echo "----"; echo "URL:  /v1/catalog/devices/$u"; echo "HTTP: $code"; echo "BODY: $body"
done
```
قالبِ جدولِ نهایی (خروجیِ بالا را در آن بریزید یا برایم بفرستید تا قالب‌بندی کنم):

| نوع نمونه | URL API | HTTP | بخشِ غیرحساسِ پاسخ | وضعیت در پنل |
|---|---|---|---|---|
| منتشرشدهٔ واقعی | `/v1/catalog/devices/vacuum-cleaner/philips` | ۲۰۰ | `{"device":…,"brand":…,"meta_title":…}` | فعال |
| منتشرنشده | `/v1/catalog/devices/washing-machine/lorch` | ۴۰۴ | `{"message":"این صفحه‌ی ترکیبی فعال نیست."}` | صفحه ندارد/غیرفعال |
| قطعاً نامعتبر | `/v1/catalog/devices/no-such-device/no-such-brand` | ۴۰۴ | `{"message":"Not Found"}` | وجود ندارد |

> مقادیرِ HTTP/BODY را از خروجیِ دستور جایگزین کنید؛ ساختارِ ستون‌ها ثابت است.

---

## ۳) آدرسِ آزمایشیِ `seo-check-nonexistent-20260906` — منبعِ ریدایرکت
تنها منبعی که فرانت برای ریدایرکت مصرف می‌کند: `GET /v1/seo/redirects`
→ `Modules/Seo/Http/Controllers/Api/RedirectController@index` که **فقط ردیف‌های `seo_redirects` با `is_active=true`** را می‌دهد (source/target/status_code/match_type). هیچ resolverِ دیگری در بک‌اند اسلاگِ ناشناخته را به `/blog/{slug}` نگاشت نمی‌کند (نگاشت‌های `/blog/…` در کد فقط برای مقالاتِ **موجود** است: canonical/breadcrumb/تحلیلِ لینک).

تأیید روی سرور (نباید هیچ نگاشتی برای این اسلاگ بدهد):
```bash
# ۱) آیا ردیفی در جدول هست؟ (انتظار: هیچ)
/opt/alt/php84/usr/bin/php artisan tinker --execute='
echo \Modules\Seo\Models\SeoRedirect::where("source","like","%seo-check-nonexistent-20260906%")->count().PHP_EOL;'
# ۲) آیا در payloadِ APIِ فرانت هست؟ (انتظار: هیچ)
curl -s https://panel.tamironline.com/v1/seo/redirects | grep -c "seo-check-nonexistent-20260906"
```
**نتیجه برای فرانت:** اگر هر دو صفر بود → بک‌اند **هیچ نگاشتی نمی‌دهد**؛ پس ریدایرکتِ ریشه→`/blog/seo-check-nonexistent-20260906` تصمیمِ **سمتِ فرانت (Next.js resolver)** است، نه API. طبقِ خواسته، بک‌اند ریدایرکت را فقط برای نگاشتِ معتبرِ ثبت‌شده تولید می‌کند و برای اسلاگِ نامعتبر چیزی نمی‌سازد.

---

## معیارِ تحویل
- رفتارِ سه‌حالتهٔ API: **تأییدشده از کد** (جدولِ بالا) — درست است، اصلاح لازم ندارد.
- `washing-machine/lorch`: حقیقتِ رکورد با دستورِ بخش ۱ (روی سرور) — خروجی را بفرستید.
- جدولِ نمونه‌ها: با دستورِ بخش ۲ پر می‌شود.
- ریدایرکتِ اسلاگِ آزمایشی: منبعش بک‌اند نیست (بخش ۳) — مگر ردیفی دستی در `seo_redirects` باشد.
