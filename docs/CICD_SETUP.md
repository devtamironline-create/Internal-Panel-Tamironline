# راه‌اندازی CI/CD خودکار (Deploy با git pull روی سرور فعلی)

با هر merge به `main`، GitHub Actions اول **تست‌ها** را اجرا می‌کند و اگر سبز
بودند، خودکار به سرور SSH می‌زند و آخرین نسخه را دیپلوی می‌کند:
**maintenance → composer → بکاپِ DB → migrate → پاک‌سازیِ کش → خروج از
maintenance → health-check → rollbackِ خودکار در صورتِ خطا**.

> امنیت: هیچ رمز/توکن/کلیدی داخلِ کد نیست و نباید در چت فرستاده شود. همه‌چیز
> از طریقِ **GitHub Secrets** خوانده می‌شود که فقط **خودت** واردشان می‌کنی.

---

## ۱) ساختِ کلیدِ SSHِ دیپلوی (روی کامپیوترِ خودت)

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f deploy_key -N ""
```

دو فایل ساخته می‌شود: `deploy_key` (خصوصی) و `deploy_key.pub` (عمومی).

## ۲) نصبِ کلیدِ عمومی روی سرور

کلیدِ عمومی را به `authorized_keys` کاربرِ `panel` اضافه کن:

```bash
ssh -p 45450 panel@45.11.185.148 'mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys' < deploy_key.pub
```

تست کن که ورود با کلید کار می‌کند:

```bash
ssh -i deploy_key -p 45450 panel@45.11.185.148 'echo OK && cd public_html && git status'
```

## ۳) افزودنِ Secrets در گیت‌هاب

مسیر: **repo → Settings → Secrets and variables → Actions → New repository secret**

| نام Secret | مقدار |
|-----------|-------|
| `SSH_HOST` | `45.11.185.148` |
| `SSH_USER` | `panel` |
| `SSH_PORT` | `45450` |
| `SSH_PRIVATE_KEY` | کلِ محتوای فایلِ `deploy_key` (خصوصی) — از `-----BEGIN` تا `-----END` |

بعد از افزودن، فایل‌های `deploy_key`/`deploy_key.pub` را از کامپیوترت پاک کن.

## ۴) (اختیاری) متغیرها — فقط اگر پیش‌فرض‌ها فرق دارند

مسیر: همان صفحه → تبِ **Variables** → New repository variable

| نام Variable | پیش‌فرض | چه زمانی تغییرش بده |
|-------------|---------|--------------------|
| `DEPLOY_ENABLED` | (خاموش) | **برای فعال‌کردنِ دیپلویِ خودکار حتماً `true` بگذار.** تا وقتی ست نشده، فقط تست‌ها اجرا می‌شوند و دیپلوی رخ نمی‌دهد (تا قبل از تنظیمِ Secretها خطای بی‌مورد نگیریم). |
| `DEPLOY_PATH` | `public_html` | اگر مسیرِ پروژه روی سرور فرق دارد |
| `PHP_BIN` | `php` | اگر باید نسخهٔ خاص باشد، مثلاً `/opt/alt/php84/usr/bin/php` |
| `COMPOSER_BIN` | `composer` | اگر composer در PATH نیست، مثلاً `/opt/cpanel/composer/bin/composer` (می‌تواند چندکلمه‌ای باشد، مثلِ `/opt/alt/php84/usr/bin/php /path/to/composer.phar`) |
| `HEALTH_URL` | `https://panel.tamironline.com/health` | اگر دامنه فرق دارد |

---

## پیش‌نیازهای سمتِ سرور (یک‌بار)

- `public_html` باید یک checkoutِ گیت باشد که `origin` روی همین ریپو و شاخهٔ
  فعالش `main` است (همین حالا هست — `git pull` دستی کار می‌کند).
- کاربرِ `panel` باید اجازهٔ اجرای `php`، `composer`، `git` را داشته باشد.
- `.env` روی سرور دست‌نخورده می‌ماند (در گیت نیست). فایلِ `public/.user.ini`
  در گیت مدیریت می‌شود و با هر دیپلوی به‌روز می‌شود.

## چطور کار می‌کند / تریگرها

- **خودکار:** هر push/merge به `main`.
- **دستی:** تبِ **Actions → CI + Deploy to production → Run workflow**.
- **rollback خودکار:** اگر composer/migrate/health شکست بخورد، کد به commit
  قبلی برمی‌گردد و سایت از maintenance خارج می‌شود. مهاجرت‌ها چون افزایشی و
  با `hasColumn` محافظت‌شده‌اند برگردانده نمی‌شوند (بی‌خطر).
- **بکاپِ DB:** قبل از هر migrate در `storage/app/backups/` (۱۰ نسخهٔ آخر).

## نکته دربارهٔ کش

`route:cache` عمداً اجرا نمی‌شود چون route `/health` یک Closure است و
سریالایز نمی‌شود؛ به‌جایش `optimize:clear` + `view:cache` اجرا می‌شود.

## اولین دیپلوی

بعد از merge شدنِ این تنظیمات به `main` و افزودنِ Secrets، یک بار از
**Actions → Run workflow** دستی اجرا کن و لاگ را نگاه کن تا از سلامتِ کاملِ
مسیر مطمئن شویم.
