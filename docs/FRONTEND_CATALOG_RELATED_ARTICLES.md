# مستند فرانت — مقالات مرتبط + جای دکمهٔ CTA + کارت مینیمال مقاله

> نسخه ۱ | تاریخ ۱۴۰۵/۰۴/۲۵
> این نامه سه موضوعِ صفحاتِ کاتالوگِ سایت (Next.js) را پوشش می‌دهد:
> ۱) **بک‌اند آماده است** — سکشن جدید `related_articles` روی هر سه endpointِ
>    کاتالوگ (دستگاه / برند / ترکیبی) اضافه شد. فقط رندر لازم است.
> ۲) جای دکمهٔ CTA در نوارِ hero باید به سمتِ مخالفِ فعلی برود (فرانت).
> ۳) کارت‌های مقاله باید مینیمال، کوچک، جذاب و ردیفی (شبیه جدول) باشند (فرانت).

---

## ۱) سکشن جدید `related_articles` — بک‌اند آماده است

سه endpointِ کاتالوگ اکنون یک سکشن جدید `related_articles` برمی‌گردانند:

| Endpoint | منبعِ مقالات |
|---|---|
| `GET /v1/catalog/devices/{slug}` | مقالاتِ متصل به آن **دستگاه** |
| `GET /v1/catalog/brands/{slug}` | مقالاتِ متصل به آن **برند** |
| `GET /v1/catalog/devices/{d}/{b}` | مقالاتِ **دستگاه×برند**؛ اگر نبود → مقالاتِ خودِ دستگاه |

**منبع = رابطهٔ واقعیِ مقاله↔دستگاه/برند** (pivotهای `site_blog_article_devices`
و `site_blog_article_brands` با فلگِ `is_active`)، نه متن‌کاوی. یعنی همان
دستگاه/برندهایی که ادمین در صفحهٔ ویرایشِ مقاله تیک زده. فقط مقالاتِ منتشرشده
(`is_published = true` و `published_at` گذشته) و به ترتیبِ **جدیدترین**.
حداکثر ۶ آیتم.

### منطقِ ترکیبی (مهم — خواستِ کارفرما)

در صفحهٔ ترکیبی، اول مقالاتی که هم به آن دستگاه **و هم** به آن برند وصل‌اند
برمی‌گردند. **اگر هیچ مقالهٔ ترکیبی‌ای نبود، به مقالاتِ خودِ دستگاه (بدونِ
قیدِ برند) fallback می‌شود** — دقیقاً طبقِ خواسته: «اگر صفحهٔ ترکیبی مقاله
نداشت، از مقالاتِ دستگاهِ بدون‌برند استفاده کن». این کاملاً سمتِ بک‌اند
انجام می‌شود؛ فرانت فقط `items` را رندر می‌کند و لازم نیست خودش fallback بزند.

### شکل پاسخ

```jsonc
"sections": {
  // ... بقیه‌ی سکشن‌ها (forum_questions و ...)
  "related_articles": {
    "enabled": true,
    "title": "مقالات مرتبط",              // از template (یا null)
    "subtitle": "راهنماها و ترفندها",     // از template (یا null)
    "items": [
      {
        "id": 42,
        "title": "چرا ماشین لباسشویی آب نمی‌کشد؟",
        "slug": "lebasshoyi-ab-namikeshad",
        "url": "/blog/lebasshoyi-ab-namikeshad",   // آماده — مستقیم لینک کنید
        "excerpt": "خلاصهٔ کوتاه بدون HTML، حداکثر ۱۶۰ کاراکتر…",  // یا null
        "image": "https://panel.tamironline.com/storage/blog/x.jpg", // یا null
        "read_time": 5,          // دقیقه، یا null
        "published_at": "2026-05-29"   // فقط تاریخ (YYYY-MM-DD)، یا null
      }
      // حداکثر ۶ آیتم
    ]
  }
}
```

`items` می‌تواند آرایهٔ خالی باشد (هیچ مقالهٔ مرتبطی نبود) — در این حالت سکشن
را رندر نکنید.

### Type

```ts
type RelatedArticleItem = {
  id: number;
  title: string;
  slug: string;
  url: string;                 // همیشه به شکل /blog/{slug}
  excerpt: string | null;      // متنِ ساده، بدون تگ HTML، ≤۱۶۰ کاراکتر
  image: string | null;        // URL کاملِ کاور، یا null
  read_time: number | null;    // دقیقه
  published_at: string | null; // YYYY-MM-DD
};

type RelatedArticlesSection = {
  enabled: boolean;
  title: string | null;
  subtitle: string | null;
  items: RelatedArticleItem[];
};
```

`title`/`subtitle` را ادمین در `/admin/site/page-content/{device|brand|device_brand}`
می‌تواند تنظیم کند؛ اگر ست نشده باشند `null` می‌آیند — یک عنوانِ پیش‌فرضِ فرانت
(مثلاً «مقالات مرتبط») بگذارید.

---

## ۲) جای دکمهٔ CTA در نوارِ hero (فرانت)

در نوارِ hero (همان بلوکی که دکمهٔ «تعمیر پکیج» با `href="/services/wall-mounted-boiler"`
و نشان‌های اعتماد را دارد) **دکمه باید در سمتِ مخالفِ جای فعلی قرار بگیرد**.

- الان دکمه یک طرف است و نشان‌های اعتماد طرفِ دیگر → باید جابه‌جا شوند.
- در چیدمانِ RTL معمولاً با یک `flex-row-reverse` روی کانتینرِ نوار (یا
  جابه‌جاییِ ترتیبِ دو فرزند) حل می‌شود؛ از تغییرِ `order` استفاده کنید تا
  در موبایل هم درست بماند.
- فقط جای بصری عوض می‌شود؛ لینک/متنِ دکمه دست نخورد.

این صرفاً یک تغییرِ چیدمانِ فرانت است و ربطی به API ندارد.

---

## ۳) کارت‌های مقاله: مینیمال، کوچک، ردیفی (شبیه جدول)

خواستهٔ کارفرما: «کارت‌های مقالات ظاهری مینیمال و جذاب و کوچک و ردیفی (مثل
جدول باشند)». یعنی به‌جای کارت‌های بزرگِ گرید، هر مقاله یک **ردیفِ کم‌ارتفاع**
باشد — تصویرِ کوچکِ چپ/راست + عنوان + متادیتای ریز — که چند تا زیرِ هم مثل
سطرهای جدول بنشینند.

نمونهٔ رندرِ ردیفی (RTL):

```tsx
const ra = data.sections.related_articles;
if (!ra?.enabled || !ra?.items?.length) return null;

return (
  <section className="mt-10">
    <h2 className="mb-3 text-lg font-bold">{ra.title ?? "مقالات مرتبط"}</h2>
    {ra.subtitle && <p className="mb-4 text-sm text-gray-500">{ra.subtitle}</p>}

    {/* لیستِ ردیفی — هر مقاله یک سطرِ باریک، شبیه جدول */}
    <ul className="divide-y divide-gray-100 rounded-xl border border-gray-100">
      {ra.items.map((a) => (
        <li key={a.id}>
          <a
            href={a.url}
            className="flex items-center gap-3 px-3 py-2.5 transition hover:bg-gray-50"
          >
            {a.image ? (
              <img
                src={a.image}
                alt={a.title}
                loading="lazy"
                className="h-12 w-12 shrink-0 rounded-lg object-cover"
              />
            ) : (
              <span className="h-12 w-12 shrink-0 rounded-lg bg-gray-100" />
            )}

            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium text-gray-900">
                {a.title}
              </span>
              {a.excerpt && (
                <span className="mt-0.5 block truncate text-xs text-gray-500">
                  {a.excerpt}
                </span>
              )}
            </span>

            {a.read_time && (
              <span className="shrink-0 text-xs text-gray-400">
                {a.read_time} دقیقه
              </span>
            )}
          </a>
        </li>
      ))}
    </ul>
  </section>
);
```

اصولِ طراحی که کارفرما خواسته:
- **کوچک و کم‌ارتفاع** — تصویرِ ~۴۸px، پدینگِ کم، یک خط عنوان (`truncate`).
- **ردیفی/جدول‌مانند** — لیستِ عمودی با `divide-y`، نه گریدِ کارتِ بزرگ.
- **مینیمال** — بدونِ سایهٔ سنگین؛ فقط یک بوردرِ نازک و hoverِ ملایم.
- **جذاب** — عنوان bold، متادیتای ریزِ خاکستری، تصویرِ گِردگوشه.

روی موبایل همین چیدمان کار می‌کند (تمام‌عرض، هر ردیف یک لمس).

---

## ۴) Cache

پاسخِ کاتالوگ `Cache-Control: max-age=600, s-maxage=600` دارد؛ مقالاتِ مرتبط
تا ۱۰ دقیقه استیل ممکن است. اگر تازه‌تر خواستید، همان endpoint را با
`revalidate=60` در Next.js fetch کنید.

---

## ۵) Checklist

- [ ] Type `RelatedArticlesSection` به schema device/brand/device_brand اضافه شود.
- [ ] گاردِ `enabled && items.length > 0` قبل از render.
- [ ] `url` مستقیم لینک شود (`/blog/{slug}` آماده است).
- [ ] `image` و `excerpt` و `read_time` می‌توانند `null` باشند — گارد بگذارید.
- [ ] کارت‌ها ردیفی/مینیمال طبقِ بخشِ ۳.
- [ ] دکمهٔ CTA در نوارِ hero به سمتِ مخالف منتقل شود (بخشِ ۲).
- [ ] در صفحهٔ ترکیبی نیازی به fallbackِ فرانت نیست؛ بک‌اند خودش به مقالاتِ
      دستگاه برمی‌گردد.

---

پایان.
