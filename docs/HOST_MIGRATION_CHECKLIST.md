# چک‌لیست جابه‌جایی هاست

هر بار که پنل به سرور جدید منتقل می‌شود، همین چند مورد تکرار می‌شوند.
این فهرست از روی مشکلات واقعیِ دو جابه‌جایی گذشته نوشته شده، نه از روی
حدس.

**ترتیب مهم است** — موارد بالاتر پیش‌نیازِ پایین‌ترها هستند.

---

## ۱) مسیر PHP

مسیر باینری روی هر سرور فرق می‌کند و اسکریپت‌ها/کران‌های قدیمی با مسیر
سرور قبلی می‌شکنند.

```bash
ls -d /opt/cpanel/ea-php*/root/usr/bin/php 2>/dev/null
ls -d /opt/alt/php*/usr/bin/php 2>/dev/null
php -v | head -1
```

پروژه در عمل **PHP >= 8.4** می‌خواهد. این نکته گمراه‌کننده است چون
`composer.json` می‌گوید `"php": "^8.2"` — ولی کامپوننت‌های Symfony نصب‌شده
(`symfony/clock`، `symfony/string`، `symfony/translation` و…) خودشان
`php: >=8.4` می‌خواهند. پس ملاک قفلِ نصب‌شده است، نه فایل composer.

روی سرورهای CloudLinux معمولاً ea-php تا ۸.۳ بیشتر ندارد ولی alt-php
نسخهٔ بالاتر دارد:

```
/opt/alt/php84/usr/bin/php
```

**فقط CLI کافی نیست.** آنچه سایت و پنل را سرو می‌کند، نسخهٔ PHP همان
دامنه در cPanel → **MultiPHP Manager** است. اگر دامنه روی ۸.۲ یا ۸.۳
بماند، پنل در مرورگر بالا نمی‌آید حتی اگر artisan در ترمینال کار کند.
هر دو را روی ۸.۴ بگذارید.

بعد از انتخاب نسخه، افزونه‌ها را چک کنید:

```bash
/opt/alt/php84/usr/bin/php -m | grep -Ei "pdo_mysql|mbstring|gd|intl|zip|bcmath|curl|xml|fileinfo"
```

اگر کدام‌یک نبود، از cPanel → **Select PHP Version** → Extensions
فعالش کنید.

مسیر پیداشده را یک‌بار در متغیر بگذارید تا بقیهٔ دستورهای این چک‌لیست
کوتاه شوند:

```bash
PHP=/opt/cpanel/ea-php84/root/usr/bin/php   # مسیر واقعی خودتان
```

## ۲) مالکیت فایل‌ها و گیت

اگر با `root` در پوشه‌ای که مال کاربر `panel` است git بزنید، خطای
`dubious ownership` می‌گیرید.

```bash
sudo -u panel git -C /home/panel/public_html pull origin main
```

راه جایگزین (`git config --global --add safe.directory …`) کار می‌کند
ولی فایل‌های ساخته‌شده مالکشان `root` می‌شود و وب‌سرور — که با کاربر
`panel` اجرا می‌شود — نمی‌تواند بنویسد. اگر آن راه را رفتید، بعد از هر
pull حتماً:

```bash
chown -R panel:panel /home/panel/public_html
```

## ۳) تنظیمات `.env`

مقادیری که تقریباً همیشه با سرور عوض می‌شوند:

| کلید | چرا |
|---|---|
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | کاربر و دیتابیس جدید |
| `APP_URL` | اگر دامنه یا پروتکل عوض شده |
| `SESSION_DRIVER` | `database` بگذارید، نه `file` — دلیلش پایین‌تر |
| `REDIS_*` | اگر سرور جدید Redis ندارد |

## ۴) سیم‌لینک و فایل‌های storage

**شایع‌ترین علتِ «عکس‌ها نمی‌آید».** تصویرها از سه مسیر سرو می‌شوند و
فقط یکی به سیم‌لینک نیاز دارد، پس ممکن است بخشی از عکس‌ها بیاید و بخشی
نه.

```bash
$PHP artisan media:diagnose
```

این دستور چهار حالت خرابی سیم‌لینک را از هم تفکیک می‌کند و می‌گوید
فایل‌ها اصلاً منتقل شده‌اند یا نه. **اول این را بزنید، بعد تصمیم
بگیرید** — اگر فایل‌ها منتقل نشده باشند، ساختن سیم‌لینک هیچ کمکی نمی‌کند
و فقط گمراه‌کننده است.

اگر گفت سیم‌لینک خراب است:

```bash
rm -f public/storage && $PHP artisan storage:link
```

اگر گفت `storage/app/public` خالی است، فایل‌ها جا مانده‌اند و باید از
سرور قبلی منتقل شوند:

```bash
rsync -avz --progress old-server:/home/panel/public_html/storage/app/public/ \
    /home/panel/public_html/storage/app/public/
```

> ⚠️ این پوشه در `.gitignore` **نیست** ولی محتوایش هم در گیت نیست —
> یعنی `git pull` هیچ‌وقت برشان نمی‌گرداند. تنها راه، کپیِ مستقیم یا
> بکاپ است.

## ۵) دسترسی‌ها

```bash
chown -R panel:panel storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

اگر مالک با کاربر وب‌سرور یکی نباشد، نتیجه **۴۰۳** است نه ۴۰۴ — ظاهرش
شبیه «فایل نیست» ولی نیست.

## ۶) محدودیت آپلود

تکنسین برای بستن سفارش باید عکس دستگاه آپلود کند و عکس موبایل به‌راحتی
۵ تا ۱۲ مگابایت است.

```bash
$PHP artisan tinker --execute="echo ini_get('upload_max_filesize');"
```

`public/.user.ini` در ریپو هست و روی cPanel/LiteSpeed سقف‌ها را بالا
می‌برد، ولی هر ۳۰۰ ثانیه cache می‌شود و بعضی پیکربندی‌ها آن را نادیده
می‌گیرند. سقف واقعیِ سمت وب را از این‌جا ببینید (خروجی `upload` را
نگاه کنید):

```
GET /v1/technician/app-config
```

اگر کوچک بود، از cPanel → **MultiPHP INI Editor** دستی بالا ببرید.

## ۷) کش‌ها

```bash
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
```

**نه `config:clear`.** پاک‌کردن کش پنل را کند می‌کند؛ روی پروداکشن باید
ساخته شود. `config:cache` خودش اول پاک می‌کند.

> نکته: هر مقداری که با `env()` خوانده شود، بعد از `config:cache` مقدار
> `null` می‌گیرد. در کد همیشه از `config()` بخوانید.

## ۸) زمان‌بند

بدون این کرون، هیچ کار زمان‌بندی‌شده‌ای اجرا نمی‌شود — پخش خودکار
سفارش، یادآور مهلت SLA، پاک‌سازی لاگ‌ها.

```bash
crontab -u panel -l | grep schedule:run
```

اگر نبود، اضافه کنید (**هر دقیقه**، نه هر ۵ دقیقه — فاصلهٔ واقعی از
تنظیمات پنل خوانده می‌شود):

```
* * * * * cd /home/panel/public_html && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

تأیید سلامتش:

```bash
$PHP artisan crm:diagnose:auto-assign
```

«آخرین اجرا» باید چند ثانیه پیش باشد.

## ۹) درایور session

با درایور `file`، لاراول برای هر خواندن `LOCK_SH` و برای هر نوشتن
`LOCK_EX` می‌گیرد و هیچ‌کدام `LOCK_NB` نیستند. یعنی درخواست‌های همزمانِ
یک مرورگر روی یک فایل **پشت هم صف می‌کشند** — و پنل در هر تب چند
endpoint را به‌صورت دوره‌ای می‌زند.

علامتش: درخواست‌هایی که هیچ کاری نمی‌کنند بیش از یک ثانیه طول می‌کشند،
با دیتابیسِ چند میلی‌ثانیه‌ای.

```
SESSION_DRIVER=database
```

جدول `sessions` در مایگریشن‌ها هست. با این تغییر همهٔ کاربران یک بار
logout می‌شوند — بیرون ساعت کاری انجامش دهید.

## ۱۰) بررسی نهایی

```bash
$PHP artisan migrate --force
$PHP artisan media:diagnose            # عکس‌ها
$PHP artisan crm:diagnose:auto-assign  # زمان‌بند و پخش
```

و برای سنجش کندی، اگر لازم شد:

```
SLOW_REQUEST_MS=800
```
```bash
$PHP artisan config:cache
# چند دقیقه با پنل کار کنید
$PHP artisan perf:slow-requests
```

بعد از عیب‌یابی، `SLOW_REQUEST_MS` را بردارید و دوباره `config:cache`.

---

## اسکریپت‌های قدیمی

`deploy-ssh.sh` آی‌پیِ سرور را **هاردکد** دارد و بعد از هر جابه‌جایی
منسوخ می‌شود. پیش از استفاده، `SSH_HOST` و `SSH_PORT` را به‌روز کنید.
`deploy.sh` برای Docker است و به این مسیر (cPanel) ربطی ندارد.
