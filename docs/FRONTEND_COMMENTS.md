# مستند فرانت — سیستم کامنت

> سیستم polymorphic — الان فقط برای مقالات (`Article`) فعال است، در آینده روی دستگاه/برند هم بدون تغییر API client روشن می‌شود (همان مدل/جدول، فقط روت‌های جدید اضافه می‌شوند).

---

## ۱) اندپوینت‌ها (مقالات)

| Endpoint | Method | Throttle | Cache |
|---|---|---|---|
| `/v1/blog/articles/{slug}/comments?page=&limit=` | GET | 60/min | s-maxage=60 |
| `/v1/blog/articles/{slug}/comments` | POST (json body) | 5/min | — |
| `/v1/comments/{id}/like` | POST (body: `{reaction:"like"|"dislike"}`) | 30/min | — |

موارد آینده (هنوز فعال نیستند): `/v1/catalog/devices/{slug}/comments`, `/v1/catalog/brands/{slug}/comments`.

---

## ۲) GET — لیست کامنت‌های یک مقاله

```jsonc
{
  "data": [
    {
      "id": 12,
      "author_name": "علی",
      "author_avatar": null,
      "is_admin_reply": false,
      "content": "متن نظر...",
      "likes": 7,
      "dislikes": 0,
      "created_at": "2026-05-21T10:30:00Z",
      "parent_id": null,
      "replies": [
        {
          "id": 18,
          "author_name": "تیم تعمیرآنلاین",
          "is_admin_reply": true,
          "content": "ممنون...",
          "likes": 2, "dislikes": 0,
          "created_at": "2026-05-21T11:00:00Z",
          "parent_id": 12
        }
      ]
    }
  ],
  "meta": { "total": 38, "page": 1, "limit": 20, "last_page": 2 }
}
```

- فقط کامنت‌های **approved** برمی‌گردند.
- `data[]` فقط top-level است (parent_id=null) و هر آیتم `replies[]` خود را دارد (یک سطح nesting در UI کافی است).
- pagination فقط روی top-level اعمال می‌شود.

---

## ۳) POST — ثبت کامنت یا reply

```http
POST /v1/blog/articles/{slug}/comments
Content-Type: application/json

{
  "author_name": "علی",
  "author_email": "ali@example.com",   // اختیاری
  "content": "متن کامنت...",
  "parent_id": 12                      // اختیاری — برای reply به یک کامنت approved
}
```

**پاسخ 201:**
```jsonc
{ "id": 42, "status": "pending", "message": "نظر شما دریافت شد و پس از تأیید نمایش داده می‌شود." }
```

- پیش‌فرض `status=pending` — تا تأیید ادمین، در GET ظاهر نمی‌شود.
- `parent_id` در صورت ارائه باید به یک کامنت **approved** روی همان مقاله اشاره کند.
- throttle: 5 درخواست در دقیقه per-IP.

**خطاهای 422 احتمالی:**
- `author_name` خالی یا بلندتر از ۸۰ کاراکتر
- `content` کوتاه‌تر از ۵ یا بلندتر از ۳۰۰۰ کاراکتر
- `parent_id` نامعتبر (وجود ندارد یا approved نیست یا مال مقاله‌ی دیگری است)

---

## ۴) POST — لایک/دیس‌لایک

```http
POST /v1/comments/{id}/like
Content-Type: application/json

{ "reaction": "like" }      // یا "dislike"
```

**پاسخ:**
```jsonc
{ "likes": 8, "dislikes": 0 }
```

- هر IP فقط یک‌بار می‌تواند روی یک کامنت reaction ثبت کند (unique constraint روی `(comment_id, ip)`).
- اگر duplicate باشد، count تغییر نمی‌کند ولی response 200 برمی‌گردد.

---

## ۵) نمونه‌ی React/Next.js

```tsx
// app/blog/[slug]/comments.tsx
'use client';
import { useState, useEffect } from 'react';

export function CommentsSection({ slug }: { slug: string }) {
  const [data, setData] = useState<CommentsResponse | null>(null);

  useEffect(() => {
    fetch(`/v1/blog/articles/${slug}/comments`).then(r => r.json()).then(setData);
  }, [slug]);

  if (!data) return <p>در حال بارگذاری...</p>;

  return (
    <section>
      <h3>کامنت‌ها ({data.meta.total})</h3>
      {data.data.map(c => <CommentThread key={c.id} comment={c} slug={slug} />)}
      <NewCommentForm slug={slug} />
    </section>
  );
}

function CommentThread({ comment, slug }: { comment: CommentNode; slug: string }) {
  return (
    <div className="comment">
      <CommentCard c={comment} />
      {comment.replies.map(r => (
        <div key={r.id} className="ml-8 mt-2">
          <CommentCard c={r} />
        </div>
      ))}
      <ReplyForm slug={slug} parentId={comment.id} />
    </div>
  );
}

async function postComment(slug: string, body: NewCommentPayload) {
  const res = await fetch(`/v1/blog/articles/${slug}/comments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
}

async function likeComment(id: number, reaction: 'like' | 'dislike') {
  const res = await fetch(`/v1/comments/${id}/like`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ reaction }),
  });
  return res.json();
}
```

---

## ۶) TypeScript types

```ts
export interface CommentNode {
  id: number;
  author_name: string;
  author_avatar: string | null;
  is_admin_reply: boolean;
  content: string;
  likes: number;
  dislikes: number;
  created_at: string;
  parent_id: number | null;
  replies?: CommentNode[];   // فقط در top-level
}

export interface CommentsResponse {
  data: CommentNode[];
  meta: { total: number; page: number; limit: number; last_page: number };
}

export interface NewCommentPayload {
  author_name: string;
  author_email?: string;
  content: string;
  parent_id?: number;
}
```

---

## ۷) UX پیشنهادی

- بعد از POST موفق، پیام موفقیت با متن «نظر شما در صف بررسی است» نمایش بده و form را reset کن.
- کامنت newly posted در UI درج نکن (چون pending است) — کاربر می‌تواند بعد از تأیید آن را در رفرش بعدی ببیند.
- لایک/دیس‌لایک optimistic update با fallback روی response واقعی.
- avatar fallback: حرف اول `author_name` در یک دایره با gradient.
- نمایش `is_admin_reply` با badge مشخص (مثلاً آبی + ✓).
- نمایش `created_at` با `dayjs(relativeTime)` به فارسی.

---

## ۸) مدیریت در پنل ادمین

| امکانات | کجا |
|---|---|
| فهرست همه کامنت‌ها با فیلتر وضعیت/نوع/owner/جستجو | `/admin/site/comments` |
| Bulk approve/reject/spam/delete | تیک‌کردن کارت‌ها → نوار شناور بالا |
| پاسخ ادمین (auto-approved، `is_admin_reply=true`) | `/admin/site/comments/{id}` |
| Thread کامل + tree | همان صفحه |
| Badge شمار pending در سایدبار | خودکار |

**Permissions:**
- `view-site-comments` (فقط مشاهده)
- `manage-site-comments` (تأیید/رد/اسپم/حذف/پاسخ)

---

## ۹) Migrationهای لازم برای deploy

```
2026_05_23_120_create_comments_tables          (site_comments + site_comment_likes)
2026_05_23_121_add_comment_permissions          (view/manage)
```

---

## ۱۰) برنامه‌ی آینده برای Device/Brand

برای فعال‌سازی کامنت روی دستگاه/برند، فقط دو تغییر لازم است:

1. trait `HasComments` را روی مدل اضافه کنید:
   ```php
   use Modules\Site\Models\Concerns\HasComments;
   class Device extends Model { use HasComments; /* ... */ }
   ```
2. روت‌های جدید در `Modules/Site/Routes/api.php`:
   ```php
   Route::get('/catalog/devices/{slug}/comments', [CommentController::class, 'indexForDevice']);
   Route::post('/catalog/devices/{slug}/comments', [CommentController::class, 'storeForDevice']);
   ```
3. متدهای متناظر در `CommentController` بسازید (5-6 خط — مشابه `indexForArticle`).

پنل ادمین و جدول کامنت بدون تغییر کار می‌کنند (polymorphic).
