# راهنمای مهاجرت مقالات از WordPress

این داک نحوه‌ی import مقالات وردپرس به جدول `site_blog_articles` را توضیح می‌دهد.

---

## ۱) پیش‌نیازها — env

این مقادیر را در `.env` اضافه کن:

```env
# اتصال به DB وردپرس بلاگ (می‌تواند همان WP CRM باشد یا جدا)
WP_BLOG_DB_HOST=127.0.0.1
WP_BLOG_DB_PORT=3306
WP_BLOG_DB_NAME=tamir_wp
WP_BLOG_DB_USER=tamir_readonly       # توصیه: user read-only فقط SELECT
WP_BLOG_DB_PASS=secret
WP_BLOG_DB_PREFIX=wp_                  # پیش‌فرض wp_

# URL سایت قدیمی (برای ساخت original_url روی مقالات)
WP_BLOG_SITE_URL=https://tamironline.com
```

**برای production** یک user جدید با فقط `SELECT` permission روی این جداول بساز:
- `wp_posts`، `wp_postmeta`
- `wp_users` (برای author_name)
- `wp_terms`، `wp_term_taxonomy`، `wp_term_relationships`

```sql
CREATE USER 'tamir_readonly'@'%' IDENTIFIED BY 'YOUR_STRONG_PASS';
GRANT SELECT ON tamir_wp.wp_posts        TO 'tamir_readonly'@'%';
GRANT SELECT ON tamir_wp.wp_postmeta     TO 'tamir_readonly'@'%';
GRANT SELECT ON tamir_wp.wp_users        TO 'tamir_readonly'@'%';
GRANT SELECT ON tamir_wp.wp_terms        TO 'tamir_readonly'@'%';
GRANT SELECT ON tamir_wp.wp_term_taxonomy TO 'tamir_readonly'@'%';
GRANT SELECT ON tamir_wp.wp_term_relationships TO 'tamir_readonly'@'%';
FLUSH PRIVILEGES;
```

---

## ۲) آماده‌سازی جدول

اگر هنوز migration نزده‌ای:

```bash
php artisan migrate
```

این جدول‌های زیر را تغییر می‌دهد (idempotent، nullable):
- `site_blog_articles`: افزودن `wp_id` (unique)، `author_name`، `original_url`
- `site_blog_topics`: افزودن `wp_term_id` (unique)

---

## ۳) توالی پیشنهادی برای production

### گام ۱ — فقط شمارش

```bash
php artisan blog:import-from-wp --count
```
چه تعداد پست در WP منتظر import است؟

### گام ۲ — Dry-run کامل

```bash
php artisan blog:import-from-wp
```
چه چیزی import می‌شود بدون اعمال؟ خطاها را در خروجی ببین.

### گام ۳ — تست با ۵ مقاله

```bash
php artisan blog:import-from-wp --limit=5 --apply
```
سپس در `/admin/site/blog/articles` چک کن که داده درست آمده.

### گام ۴ — تست با تصاویر

```bash
php artisan blog:import-from-wp --limit=5 --apply --download-images
```
چک کن cover و inline images درست دانلود شده‌اند.

### گام ۵ — اعمال کامل با تصاویر

```bash
php artisan blog:import-from-wp --apply --download-images
```

---

## ۴) همه‌ی گزینه‌های command

| flag | کاربرد |
|---|---|
| `--apply` | اعمال واقعی — بدون این فقط dry-run |
| `--download-images` | cover و inline images را دانلود کن و در `site_media` ذخیره کن |
| `--count` | فقط تعداد پست‌های قابل import را چاپ کن |
| `--wp-id=1234` | فقط یک پست خاص (برای debug) |
| `--since=2024-01-01` | فقط پست‌های بعد از این تاریخ |
| `--limit=50` | حداکثر ۵۰ پست (پیش‌فرض ۰ = بدون محدودیت) |
| `--status=publish,private` | پیش‌فرض فقط `publish` |
| `--force` | پست‌هایی که قبلاً import شده‌اند را دوباره به‌روز کن |

---

## ۵) نحوه‌ی mapping

| WordPress | Panel |
|---|---|
| `wp_posts.ID` | `site_blog_articles.wp_id` (idempotency key) |
| `wp_posts.post_title` | `title` |
| `wp_posts.post_name` (slug) | `slug` — اگر فارسی، transliterate به ASCII؛ اگر شکست خورد → `wp-{id}` |
| `wp_posts.post_content` | `content` — بعد از: shortcode strip → جایگزینی تصاویر inline → `HtmlSanitizer::clean` |
| `wp_posts.post_excerpt` | `excerpt` (اگر خالی، ۲۸۰ کاراکتر اول `content`) |
| `wp_posts.post_date_gmt` | `published_at` |
| `wp_posts.post_status='publish'` | `is_published=true` |
| `wp_users.display_name` | `author_name` (string، بدون FK) |
| `wp_postmeta._thumbnail_id` → `wp_posts(attachment).guid` | `cover_image` (و `cover_media_id` اگر `--download-images`) |
| `wp_postmeta._yoast_wpseo_title` یا `rank_math_title` | `meta_title` |
| `wp_postmeta._yoast_wpseo_metadesc` یا `rank_math_description` | `meta_description` |
| `category` terms | `site_blog_topics` (با `wp_term_id`) → pivot `site_blog_article_topics` |
| `post_tag` terms (با name/slug همخوان) | `crm_devices`/`crm_brands` → pivot |
| محاسبه از طول متن | `read_time_minutes` (~۲۰۰ کلمه/دقیقه) |
| `WP_BLOG_SITE_URL + slug` | `original_url` (برای ساخت 301 redirect) |

### shortcode‌های شناخته‌شده

- `[caption ...]X[/caption]` → `X` (متن داخل نگه داشته می‌شود)
- `[embed]URL[/embed]` → `<iframe src="URL">` فقط برای youtube/aparat/vimeo
- `[video src="..."]` → `<video>`
- `[gallery]`, `[su_*]`, `[contact-form]`, … → حذف کامل
- هر shortcode ناشناخته → حذف کامل

### sanitizer

`HtmlSanitizer::clean` allowlist محکم اعمال می‌کند:
- tag های متنی: `p`, `h1-h6`, `ul`, `ol`, `li`, `blockquote`, `pre`, `code`, …
- `<img>`, `<figure>`, `<figcaption>`, `<a>`, `<table>`
- `<iframe>` فقط از youtube/youtube-nocookie/aparat/vimeo/google maps/neshan
- `<script>`, `<style>`, `<object>`, `<embed>`, `<svg>` کلاً حذف می‌شوند

---

## ۶) Idempotency و re-run

- `wp_id` روی `site_blog_articles` unique است
- هر بار اجرای command بدون `--force`: فقط wp_idهای جدید
- با `--force`: همه‌ی پست‌ها update می‌شوند (slug ممکن است collision نشان دهد — handler خودکار `-2`, `-3` اضافه می‌کند)
- در صورت اجرای مجدد، تصاویر قبلاً دانلودشده دوباره دانلود نمی‌شوند (dedup با hash + filename)

---

## ۷) Rollback

اگر بخواهی همه‌ی import را پاک کنی:

```sql
-- توجه: فقط مقالات وارد شده از WP را پاک می‌کند، مقالات ساخته‌شده دستی دست‌نخورده
DELETE FROM site_blog_articles WHERE wp_id IS NOT NULL;

-- topic های ایجادشده از WP که هیچ مقاله‌ای ندارند
DELETE FROM site_blog_topics
WHERE wp_term_id IS NOT NULL
  AND id NOT IN (SELECT DISTINCT topic_id FROM site_blog_article_topics);
```

تصاویر site_media دست‌نخورده می‌مانند (احتمالاً جای دیگری استفاده می‌شوند). برای پاک‌کردن تصاویر یتیم از panel ادمین `/admin/site/media` استفاده کن.

---

## ۸) Troubleshooting

| خطا | علت | راه‌حل |
|---|---|---|
| `اتصال به WP Blog DB ناموفق` | env اشتباه یا user اجازه ندارد | `php artisan tinker` → `DB::connection('wp_blog')->getPdo()` |
| `هیچ پستی برای import پیدا نشد` | `--status` غلط یا قبلاً import شده | با `--force` یا `--status=publish,private,draft` |
| `slug collision` | چند پست WP slug مشابه دارند | command خودکار `-2`, `-3` اضافه می‌کند — هشدار در log |
| `wp_image_downloader.http_status` | تصویر در WP حذف شده یا private | بدون cover ادامه پیدا می‌کند — URL در content باقی می‌ماند |
| `صفحه‌ی مقاله 404` | `is_published=false` یا `published_at` در آینده | در `/admin/site/blog/articles/{id}/edit` بررسی کن |

---

## ۹) چه چیزی import نمی‌شود

- **کامنت‌های وردپرس** — نه از قبل auth بود (الان فقط Customer می‌تواند نظر دهد)
- **revisions** و **autosaves** — فقط آخرین نسخه
- **post types سفارشی** — فقط `post_type='post'`
- **تنظیمات widget و sidebar**
- **نظرات داخل postmeta**

برای import کامنت‌ها در صورت نیاز، command جدید با مشابه‌سازی polymorphic comment نیاز است.
