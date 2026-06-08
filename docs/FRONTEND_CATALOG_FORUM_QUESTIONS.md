# مستند فرانت — سکشن سوالات انجمن در صفحات Catalog

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> در صفحات `/devices/[slug]`، `/brands/[slug]` و ترکیبی `/devices/[d]/[b]`
> یک سکشن جدید `forum_questions` به پاسخ API اضافه شد که ۵ سوال آخر
> انجمن مرتبط با همان دستگاه/برند را نمایش می‌دهد.

---

## ۱) منطق دسته‌بندی انجمن

سوالات انجمن (`site_forum_questions`) از قبل دو ستون داشتند:
- `device_id` — کلید دستگاه مرتبط
- `brand_id` — کلید برند مرتبط

این دو ستون نقش «دسته‌بندی» سوال را بازی می‌کنند. در پنل ادمین انجمن،
هنگام افزودن/ویرایش سوال این دو فیلد ست می‌شوند.

---

## ۲) شکل پاسخ

پاسخ هر سه endpoint catalog شامل سکشن جدید `forum_questions` است:

```jsonc
"sections": {
  // ... بقیه‌ی سکشن‌ها
  "forum_questions": {
    "enabled": true,
    "title": "سوالات اخیر",                  // از template (یا null)
    "subtitle": "آخرین مشکلات کاربران",     // از template (یا null)
    "see_all_label": "مشاهده همه سوالات",  // از template (یا null)
    "see_all_url": "/forum?device=washing-machine",   // خودکار از بک‌اند
    "items": [
      {
        "id": 42,
        "slug": "lebas-shoyi-ab-namikeshid",
        "title": "لباس‌شویی آب نمی‌کشد، چه باید کرد؟",
        "url": "/forum/42-lebas-shoyi-ab-namikeshid",
        "device_id": 1,
        "brand_id": 3,
        "view_count": 152,
        "upvotes_count": 4,
        "answers_count": 2,
        "resolution_status": "expert-answered",
        "published_at": "2026-05-29T14:23:00+00:00"
      }
      // حداکثر ۵ آیتم
    ]
  }
}
```

`items` می‌تواند آرایه‌ی خالی باشد (هیچ سوال منتشرشده‌ای ثبت نشده).

---

## ۳) منطق merge

| Endpoint | معیار query |
|---|---|
| `GET /v1/catalog/devices/{slug}` | `device_id = device.id`، ۵ تا آخر |
| `GET /v1/catalog/brands/{slug}` | `brand_id = brand.id`، ۵ تا آخر |
| `GET /v1/catalog/devices/{d}/{b}` | اول `device_id = d AND brand_id = b` (دقیق)، اگر کم باشد با سوالات همان device (که brand دیگر یا null دارند) تا limit پر می‌شود |

شرط `published_at` تنظیم می‌کند که فقط سوالات منتشرشده (`status = approved`) برمی‌گردند.

---

## ۴) رندر صحیح

```tsx
type ForumQuestionItem = {
  id: number;
  slug: string;
  title: string;
  url: string;
  device_id: number | null;
  brand_id: number | null;
  view_count: number;
  upvotes_count: number;
  answers_count: number;
  resolution_status: "unanswered" | "answered" | "expert-answered" | "solved";
  published_at: string | null;
};

type ForumQuestionsSection = {
  enabled: boolean;
  title: string | null;
  subtitle: string | null;
  see_all_label: string | null;
  see_all_url: string;
  items: ForumQuestionItem[];
};
```

نمونه‌ی رندر:

```tsx
const fq = data.sections.forum_questions;
if (!fq?.enabled || !fq?.items?.length) return null;

return (
  <section>
    {fq.title && <h2>{fq.title}</h2>}
    {fq.subtitle && <p>{fq.subtitle}</p>}

    <ul>
      {fq.items.map((q) => (
        <li key={q.id}>
          <a href={q.url}>{q.title}</a>
          <small>
            {q.answers_count} پاسخ · {q.view_count} بازدید
            {q.resolution_status === "expert-answered" && " · ✓ پاسخ کارشناس"}
            {q.resolution_status === "solved" && " · ✓ حل‌شده"}
          </small>
        </li>
      ))}
    </ul>

    {fq.see_all_label && (
      <a href={fq.see_all_url}>{fq.see_all_label} ←</a>
    )}
  </section>
);
```

---

## ۵) تنظیم تیتر سکشن

ادمین در `/admin/site/page-content/device` (و مشابه برای `brand`، `device_brand`)
سکشن جدید `forum_questions` می‌بیند با سه فیلد:
- `title` — تیتر سکشن (مثلاً «سوالات اخیر»)
- `subtitle` — زیرتیتر
- `see_all_label` — متن لینک «مشاهده همه»

`items` و `see_all_url` خودکار توسط بک‌اند ست می‌شوند.

---

## ۶) Cache و دیتای زنده

پاسخ catalog ها `Cache-Control: max-age=600, s-maxage=600` دارند. یعنی
سوالات تا ۱۰ دقیقه استیل ممکن است. اگر می‌خواهید سوالات «زنده‌تر» باشند،
می‌توانید این endpoint را با `revalidate=60` در Next.js fetch کنید
(کش پاسخ کلی، ولی refresh سریع‌تر).

اگر در آینده نیاز به webhook revalidation برای انجمن باشد، می‌توان
ObserverQuestion را به ping به فرانت اضافه کرد (تگ: `forum:device:{slug}`
یا `forum:brand:{slug}`). فعلاً ساده نگه داشتیم.

---

## ۷) Checklist

- [ ] Type `ForumQuestionsSection` به schema device/brand/device_brand اضافه شود.
- [ ] گارد `enabled && items.length > 0` قبل از render.
- [ ] resolution_status را به badge بصری تبدیل کنید (✓ حل‌شده، 👨‍🔧 پاسخ کارشناس).
- [ ] `see_all_url` همیشه با query string صحیح می‌آید — مستقیم لینک کنید.
- [ ] برای SEO، schema.org `QAPage` JSON-LD اختیاری.

---

پایان.
