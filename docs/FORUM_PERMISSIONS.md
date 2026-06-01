# دسترسی‌های انجمن (Spatie permissions)

تمام دسترسی‌های انجمن در guard `web` تعریف شده‌اند. هر نقش می‌تواند یک یا چند مورد را داشته باشد.

## فهرست کامل

| نام دسترسی | شرح | seed |
|---|---|---|
| `view-forum` | مشاهده‌ی panel انجمن (لیست/جزئیات سوال — read-only) | ۲۰۲۶/۰۵/۲۳-۱۳۱ |
| `manage-forum-questions` | CRUD کامل سوالات + همه‌ی اختیارات زیر | ۲۰۲۶/۰۵/۲۳-۱۳۱ |
| `manage-forum-experts` | مدیریت کارشناسان (افزودن/ویرایش/حذف) | ۲۰۲۶/۰۵/۲۳-۱۳۱ |
| `moderate-forum-questions` | فقط تأیید / رد / spam / علامت‌گذاری hot/featured | ۲۰۲۶/۰۵/۲۳-۲۴۰ ✨ |
| `delete-forum-questions` | فقط حذف سوال | ۲۰۲۶/۰۵/۲۳-۲۴۰ ✨ |
| `manage-forum-answers` | پاسخ ادمین/کارشناس، accept answer، حذف پاسخ | ۲۰۲۶/۰۵/۲۳-۲۴۰ ✨ |

## نقشه‌ی Permission ↔ Endpoint

### ‌Read (پنل ادمین — لیست و جزئیات)
```
GET  /admin/site/forum/questions          → view-forum
GET  /admin/site/forum/questions/{id}     → view-forum
```

### Moderation
```
PUT  /admin/site/forum/questions/{id}/status        → moderate-forum-questions
PUT  /admin/site/forum/questions/{id}/toggle/{flag} → moderate-forum-questions
POST /admin/site/forum/questions/bulk
    └ action=approve|reject|spam|mark_*|unmark_*    → moderate-forum-questions
    └ action=delete                                  → delete-forum-questions
```

### Full edit
```
PUT  /admin/site/forum/questions/{id}     → manage-forum-questions  (شامل moderation هم می‌شود)
```

### Delete
```
DELETE /admin/site/forum/questions/{id}   → delete-forum-questions
```

### Answers
```
PUT    /admin/site/forum/questions/{q}/answers/{a}/status  → manage-forum-answers
DELETE /admin/site/forum/questions/{q}/answers/{a}         → manage-forum-answers
POST   /admin/site/forum/questions/{q}/admin-reply         → manage-forum-answers
```

### Experts
```
*  /admin/site/forum/experts/*  → manage-forum-experts
```

## الگوهای رایج نقش

نمونه‌های گردش‌کار:

| نقش | دسترسی‌های توصیه‌شده | کاربر نمونه |
|---|---|---|
| **Forum Moderator** | view-forum + moderate-forum-questions + manage-forum-answers | تیم پشتیبانی روزانه |
| **Forum Editor** | view-forum + manage-forum-questions (همه را شامل می‌شود) | مسئول محتوا |
| **Forum Cleaner** | view-forum + delete-forum-questions | فقط برای حذف اسپم/تکراری |
| **Expert Manager** | manage-forum-experts | مسئول معرفی کارشناسان |
| **Full Forum Admin** | همه + manage-site (super) | ادمین اصلی سایت |

نقش `admin` کلید پیش‌فرض همه‌ی این‌ها را در migrationها می‌گیرد.

## رفتار با fallback

تمام checkهای granular در `QuestionController` به‌صورت OR زنجیره‌ای پیاده شده‌اند:

```php
private function checkModerate(): void
{
    if (!$u || !(
        $u->can('moderate-forum-questions') ||
        $u->can('manage-forum-questions') ||   // ← parent
        $u->can('manage-site') ||              // ← super
        $u->can('manage-permissions')          // ← super
    )) {
        abort(403);
    }
}
```

یعنی کسی که `manage-forum-questions` کلی دارد، خودکار به همه‌ی اختیارات moderation/delete/answers هم دسترسی دارد.

## ساخت نقش جدید

```php
use Spatie\Permission\Models\Role;

$mod = Role::firstOrCreate(['name' => 'forum-moderator', 'guard_name' => 'web']);
$mod->syncPermissions([
    'view-forum',
    'moderate-forum-questions',
    'manage-forum-answers',
]);
```

## Deploy

```bash
php artisan migrate
# دسترسی‌های جدید روی نقش admin خودکار اعمال می‌شوند
```
