# پاسخِ بک‌اند (۲) — انتشارِ دو کامبو + جدولِ ریدایرکت + تأییدِ نسخه

**تاریخ:** ۲۰۲۶-۰۹-۰۶ · **تغییرِ مبنا:** فیلیپس و لورچ **ارائه می‌شوند** (case A، نه C) → باید منتشر شوند.

---

## ۱) انتشارِ دو کامبو (بخش ۱)
ابزارِ انتشار ساخته شد: **`crm:publish-combo {device} {brand}`** — رکوردِ ترکیبی را می‌سازد/فعال می‌کند و اگر عنوان/توضیح/متا خالی باشد محتوای پیش‌فرضِ آبرومند از نامِ دستگاه/برند می‌گذارد (محتوای دستیِ ادمین را بازنویسی نمی‌کند).

روی سرورِ پنل اجرا کنید:
```bash
/opt/alt/php84/usr/bin/php artisan crm:publish-combo vacuum-cleaner philips
/opt/alt/php84/usr/bin/php artisan crm:publish-combo wall-mounted-boiler lorch
/opt/alt/php84/usr/bin/php artisan seo:sitemap-flush          # تا سایت‌مپ فوراً به‌روز شود
```
سپس تأیید (باید تغییر کند):
```bash
# API کاتالوگ باید ۲۰۰ شود:
curl -s -o /dev/null -w "%{http_code}\n" https://panel.tamironline.com/v1/catalog/devices/vacuum-cleaner/philips
curl -s -o /dev/null -w "%{http_code}\n" https://panel.tamironline.com/v1/catalog/devices/wall-mounted-boiler/lorch
# سایت‌مپِ ترکیب‌ها باید از ۳۶۲ به ۳۶۴ برسد و این دو در آن باشند:
curl -s --compressed https://tamironline.com/sitemaps/sitemap-services-combo.xml | grep -cE "<loc>"
curl -s --compressed https://tamironline.com/sitemaps/sitemap-services-combo.xml | grep -iE "vacuum-cleaner/philips|wall-mounted-boiler/lorch"
```
> محتوای غنی‌ترِ صفحه (هیرو/عکس/FAQ) را ادمین در «مدیریت صفحاتِ دستگاه×برند» تکمیل می‌کند؛ برای ایندکس‌شدن، همین انتشار کافی است.

## ۲) جدولِ ۹ ریدایرکت (بخش ۲)
از دستورِ `seo:legacy-redirect-map` (روی سرور) گرفته می‌شود؛ فقط ردیف‌های `status = published` اعمال شوند:
```bash
/opt/alt/php84/usr/bin/php artisan seo:legacy-redirect-map
# CSV: storage/app/legacy-redirects.csv (source,destination,status,hits)
```
خروجیِ کنسول/CSV را بفرستید تا **جدولِ نهاییِ ۹ ریدایرکتِ تأییدشده** (مبدأ/مقصد/وضعیت) را قالب‌بندی و به فرانت بدهم. مقصدِ هر ردیف قبلِ اعمال، با `status=published` صحتش تضمین می‌شود (نه صفحهٔ اصلی، نه نامرتبط).

## ۳) نسخهٔ فعال (بخش ۳) — سمتِ فرانت
`/api/version` و بیلد/دیپلوی و PR #277 در **ریپوی فرانت (tamironline-new-website)** هستند، نه پنل. پس از بیلد+دیپلوی، من از این‌جا `GET /api/version` را می‌گیرم و تطبیقِ `commit` با SHAی بیلد را تأیید می‌کنم (بیس‌لاینِ فعلی: `/api/version` = ۴۰۴ → یعنی هنوز بالا نیامده).

## ۴) مبدأ vs عمومی + purge (بخش ۴)
- **کامبوها (سمتِ پنل):** پس از `publish-combo`، پاسخِ مستقیمِ API پنل ۲۰۰ می‌شود (شاهدِ «برنامه درست است»). اگر دامنهٔ عمومی هنوز قدیمی بود → **purgeِ WCDN** برای آن مسیرها.
- **صفحاتِ `/services/*` فرانت:** ابتدا پاسخِ مستقیمِ اپِ فرانت روی سرور، سپس عمومی؛ اگر اپ درست و عمومی کهنه بود → purge؛ اگر خودِ اپ کهنه بود → نسخه/کش داخلیِ فرانت. (سمتِ فرانت/زیرساخت.)

---

## خروجیِ نهایی (پس از اجرای شما)
1. **نسخهٔ فعال:** از `/api/version` (بعدِ دیپلویِ فرانت).
2. **پاسخِ API دو ترکیب:** بعدِ `publish-combo` → ۲۰۰ (تأیید می‌کنم).
3. **سایت‌مپِ تازه:** ۳۶۲ → ۳۶۴ با حضورِ دو کامبو (تأیید می‌کنم).
4. **جدولِ ۹ ریدایرکت:** از خروجیِ `seo:legacy-redirect-map` (قالب‌بندی می‌کنم).

با احترام — تیم بک‌اند (پنل)
