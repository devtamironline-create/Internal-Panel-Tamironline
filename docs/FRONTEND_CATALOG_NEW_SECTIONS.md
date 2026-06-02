# مستند فرانت — سه سکشن جدید در صفحات دستگاه / برند / ترکیبی

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> این مستند سه افزوده‌ی اخیر به پاسخ‌های `/v1/catalog/devices/{slug}`،
> `/v1/catalog/brands/{slug}` و `/v1/catalog/devices/{d}/{b}` (ترکیبی) را
> به‌صورت یکجا پوشش می‌دهد:
>
> ۱) **تصویر Hero** — دو تصویر (دسکتاپ + موبایل) با alt مجزا، با امکان override per-entity
> ۲) **سکشن ویدیوها** — لیست ویدیو با اولویت آپارات / یوتیوب / mp4 مستقیم، با override per-entity
> ۳) **سکشن سوالات انجمن** — ۵ سوال آخر مرتبط با همان دستگاه/برند/ترکیب

پیش‌نیاز: `FRONTEND_PAGES_API.md` (ساختار کلی section-based) و `FRONTEND_BANNER_ZONES_IN_PAGES.md`.

---

## ۱) تصویر Hero (`sections.hero.image`)

### چه چیزی تغییر کرد؟
- فیلد `image` به سکشن `hero` در تمام template ها اضافه شد (`device`, `brand`, `device_brand`, `home`, `services`, `about`, `contact`)
- شکل قبلی `responsive_image` که فقط `{desktop: "url", mobile: "url"}` بود، حالا برای **هر slot یک alt مجزا** دارد
- در سطح entity: ستون‌های جدید `crm_devices.hero_image` و `crm_brands.hero_image` (JSON) — هر دستگاه/برند می‌تواند تصویر اختصاصی override کند

### شکل پاسخ

```jsonc
"sections": {
  "hero": {
    "enabled": true,
    "badge": "...",
    "title": "...",
    "subtitle": "...",
    "caption": "...",
    "image": {
      "desktop": {
        "url": "https://.../desktop.webp",
        "alt": "تکنسین در حال تعمیر ماشین لباس‌شویی در آشپزخانه"
      },
      "mobile": {
        "url": "https://.../mobile.webp",
        "alt": "دست‌های تکنسین روی پنل ماشین لباس‌شویی"
      }
    },
    "cta_primary": { "label": "...", "url": "...", "icon": "..." },
    "cta_secondary": { "label": "...", "url": "...", "icon": "..." }
  }
}
```

- هم `url` و هم `alt` می‌توانند `null` باشند
- اگر هیچ تصویری ست نشده باشد: `{desktop: {url: null, alt: null}, mobile: {url: null, alt: null}}`
- بک‌اند **همیشه** این شکل را برمی‌گرداند — حتی برای داده‌ی قدیمی DB

### منطق merge per-slot

| Endpoint | اولویت برای هر slot |
|---|---|
| `/v1/catalog/devices/{slug}` | `device.hero_image[slot]` > `template.device.hero.image[slot]` |
| `/v1/catalog/brands/{slug}` | `brand.hero_image[slot]` > `template.brand.hero.image[slot]` |
| `/v1/catalog/devices/{d}/{b}` | `device.hero_image[slot]` > `brand.hero_image[slot]` > `template.device_brand.hero.image[slot]` |

هر `url` و `alt` به‌صورت **مستقل** merge می‌شود — مثلاً اگر دستگاه فقط desktop ست کرده باشد، mobile از brand یا template می‌آید.

### الگوی render امن

```tsx
type ResponsiveSlot = { url: string | null; alt: string | null };
type ResponsiveImage = { desktop: ResponsiveSlot; mobile: ResponsiveSlot };

function pickHeroImage(img: ResponsiveImage | undefined | null) {
  const desktop = img?.desktop?.url;
  const mobile = img?.mobile?.url;
  if (!desktop && !mobile) return null;
  return {
    desktopUrl: desktop ?? mobile!,
    desktopAlt: img?.desktop?.alt ?? "",
    mobileUrl: mobile ?? desktop!,
    mobileAlt: img?.mobile?.alt ?? img?.desktop?.alt ?? "",
  };
}

// رندر استاندارد — alt دسکتاپ به assistive tech می‌رود
const hero = pickHeroImage(content.sections.hero.image);
if (!hero) return null;

return (
  <picture>
    <source media="(max-width: 767px)" srcSet={hero.mobileUrl} />
    <img
      src={hero.desktopUrl}
      alt={hero.desktopAlt}
      width={1200}
      height={500}
      loading="eager"
      fetchPriority="high"
    />
  </picture>
);
```

> **هشدار:** یک المنت `<img>` فقط یک `alt` دارد — مرورگر بسته به viewport عوض نمی‌کند.
> اگر می‌خواهید alt موبایل واقعاً به screen reader برسد، الگوی **دو `<img>` با CSS hidden/visible** را استفاده کنید (`hidden md:block` و `block md:hidden`).

### Checklist

- [ ] Type `ResponsiveImage` به schema device/brand/device_brand اضافه شود.
- [ ] جای `image.desktop` (string) → `image.desktop.url`.
- [ ] alt دسکتاپ به‌عنوان attribute `alt` المنت `<img>` — نه رشته‌ی ثابت.
- [ ] گارد برای حالت هر دو slot خالی.

جزئیات کامل: `docs/FRONTEND_RESPONSIVE_IMAGE_ALT.md`

---

## ۲) سکشن ویدیوها (`sections.videos`)

### چه چیزی تغییر کرد؟
- سکشن جدید `videos` به template `device` و `brand` اضافه شد
- ادمین در page-content صفحات الگو لیست پیش‌فرض ویدیو می‌سازد
- هر دستگاه/برند می‌تواند لیست اختصاصی خود را در فرم ویرایش entity ست کند → جایگزین template می‌شود

### شکل پاسخ

```jsonc
"sections": {
  "videos": {
    "enabled": true,
    "title": "آموزش‌های ویدیویی",
    "subtitle": "نمونه ویدیوهای تعمیر",
    "items": [
      {
        "title": "نحوه‌ی تعمیر موتور لباس‌شویی",
        "aparat_id": "ABC123",     // اولویت ۱
        "youtube_id": null,        // اولویت ۲
        "video_url": null,         // اولویت ۳ (mp4 مستقیم)
        "description": "...",
        "poster_url": "https://...cover.webp"
      }
    ]
  }
}
```

`title` و `subtitle` همیشه از template می‌آیند. `items` per-entity override می‌شود.

### منطق merge

```
items = entity.videos اگر آرایه‌ی غیرخالی → otherwise: template.videos.items
```

(override کامل — append نیست)

### Embed URL ها

```ts
function pickVideoSource(item) {
  if (item.aparat_id) return { kind: "aparat", embed: `https://www.aparat.com/video/video/embed/videohash/${item.aparat_id}/vt/frame` };
  if (item.youtube_id) return { kind: "youtube", embed: `https://www.youtube.com/embed/${item.youtube_id}` };
  if (item.video_url) return { kind: "direct", embed: item.video_url };
  return null;
}
```

### الگوی render

```tsx
const v = content.sections.videos;
if (!v?.enabled || !v?.items?.length) return null;

return (
  <section>
    {v.title && <h2>{v.title}</h2>}
    {v.subtitle && <p>{v.subtitle}</p>}
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      {v.items.map((item, i) => {
        const src = pickVideoSource(item);
        if (!src) return null;
        return (
          <article key={i}>
            {src.kind === "direct" ? (
              <video src={src.embed} controls poster={item.poster_url ?? undefined} />
            ) : (
              <iframe src={src.embed} loading="lazy" allowFullScreen title={item.title ?? ""} />
            )}
            <h3>{item.title}</h3>
            {item.description && <p>{item.description}</p>}
          </article>
        );
      })}
    </div>
  </section>
);
```

### Checklist

- [ ] Type `VideosSection` به schema device/brand اضافه شود.
- [ ] گارد `enabled && items.length > 0`.
- [ ] انتخاب source بر اساس اولویت aparat → youtube → mp4.
- [ ] `loading="lazy"` روی iframe — مهم برای صفحات سنگین.

جزئیات کامل: `docs/FRONTEND_DEVICE_BRAND_VIDEOS.md`

---

## ۳) سکشن سوالات انجمن (`sections.forum_questions`)

### چه چیزی تغییر کرد؟
- سکشن جدید `forum_questions` به template `device`, `brand`, `device_brand` اضافه شد
- ۵ سوال آخر **بر اساس `device_id` / `brand_id`** خودکار از `site_forum_questions` می‌آید
- این یعنی: **سیستم دسته‌بندی انجمن از قبل بر اساس دستگاه/برند کار می‌کند** — هیچ taxonomy جدا نیست

### شکل پاسخ

```jsonc
"sections": {
  "forum_questions": {
    "enabled": true,
    "title": "سوالات اخیر",
    "subtitle": "آخرین مشکلات کاربران",
    "see_all_label": "مشاهده همه سوالات",
    "see_all_url": "/forum?device=washing-machine",
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

### منطق query

| Endpoint | فیلتر | URL «مشاهده همه» |
|---|---|---|
| `/v1/catalog/devices/{slug}` | `device_id = X`، ۵ آخر approved | `/forum?device={slug}` |
| `/v1/catalog/brands/{slug}` | `brand_id = X`، ۵ آخر approved | `/forum?brand={slug}` |
| `/v1/catalog/devices/{d}/{b}` | اول دقیق `device=d AND brand=b`؛ اگر کم بود با همان device تکمیل می‌شود | `/forum?device={d}&brand={b}` |

فقط سوالات `status = approved` با `published_at IS NOT NULL`.

### الگوی render

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

const fq = content.sections.forum_questions;
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

### Checklist

- [ ] Type `ForumQuestionsSection` به schema device/brand/device_brand اضافه شود.
- [ ] گارد `enabled && items.length > 0`.
- [ ] `resolution_status` به badge بصری تبدیل شود.
- [ ] `see_all_url` مستقیم استفاده شود (با `<Link>` Next.js).

جزئیات کامل: `docs/FRONTEND_CATALOG_FORUM_QUESTIONS.md`

---

## ۴) خلاصه‌ی تغییرات per-endpoint

### `GET /v1/catalog/devices/{slug}`

```jsonc
{
  "id": 2,
  "slug": "washing-machine",
  "label": "ماشین لباس‌شویی",
  // ...
  "sections": {
    "hero": {
      "enabled": true,
      // فیلدهای قبلی +
      "image": { "desktop": {url, alt}, "mobile": {url, alt} }  // ✨ جدید
    },
    "steps": { /* بدون تغییر */ },
    "live_activity": { /* بدون تغییر */ },
    "content": { /* بدون تغییر */ },
    "faq": { /* بدون تغییر */ },
    "brands": { /* بدون تغییر */ },
    "testimonials": { /* بدون تغییر */ },
    "videos": { enabled, title, subtitle, items: [...] },           // ✨ جدید
    "forum_questions": { enabled, title, subtitle, see_all_*, items: [...] }  // ✨ جدید
  }
}
```

### `GET /v1/catalog/brands/{slug}`

```jsonc
{
  "id": 1,
  "slug": "samsung",
  "label": "سامسونگ",
  // ...
  "sections": {
    "hero": {
      "enabled": true,
      // ...
      "image": { "desktop": {url, alt}, "mobile": {url, alt} }  // ✨ جدید
    },
    "steps": { /* بدون تغییر */ },
    "live_activity": { /* بدون تغییر */ },
    "content": { /* بدون تغییر */ },
    "faq": { /* بدون تغییر */ },
    "devices": { /* بدون تغییر */ },
    "testimonials": { /* بدون تغییر */ },
    "videos": { enabled, title, subtitle, items: [...] },           // ✨ جدید
    "forum_questions": { enabled, title, subtitle, see_all_*, items: [...] }  // ✨ جدید
  }
}
```

### `GET /v1/catalog/devices/{deviceSlug}/{brandSlug}` (ترکیبی)

```jsonc
{
  "device": { ... },
  "brand": { ... },
  "sections": {
    "hero": {
      "enabled": true,
      // ...
      "image": { "desktop": {url, alt}, "mobile": {url, alt} }  // ✨ جدید — اولویت device > brand > template
    },
    "steps": { /* بدون تغییر */ },
    "content": { /* بدون تغییر */ },
    "faq": { /* بدون تغییر */ },
    "brand_other_devices": { /* بدون تغییر */ },
    "testimonials": { /* بدون تغییر */ },
    "forum_questions": { enabled, title, subtitle, see_all_*, items: [...] }  // ✨ جدید
  }
}
```

> فعلاً سکشن `videos` در پاسخ ترکیبی نیست — اگه لازم شد بفرمایید.

---

## ۵) Cache و revalidation

تمام سه endpoint کش `Cache-Control: max-age=600, s-maxage=600` دارند. برای دیتای زنده‌تر:

- **هیچ webhook اختصاصی برای catalog فعال نیست** فعلاً
- اگر می‌خواهید فرانت سریع‌تر آپدیت شود، در Next.js:
  ```ts
  fetch(`${API}/v1/catalog/devices/${slug}`, {
    next: { revalidate: 60, tags: [`catalog:device:${slug}`] }
  });
  ```
- ادمین هنگام ویرایش entity می‌تواند manually `Cache::clear()` بزند یا webhook اضافه شود

اگر برایتان مهم است سوالات انجمن فوراً نشان داده شوند، فقط TTL کوتاه‌تر (مثلاً 60 ثانیه) کافی است.

---

## ۶) Checklist کلی مهاجرت فرانت

- [ ] Type های `ResponsiveImage`، `VideosSection`، `ForumQuestionsSection` به schema هر سه endpoint اضافه شوند.
- [ ] `sections.hero.image` → استفاده از `pickHeroImage` helper.
- [ ] `sections.videos.items` → کامپوننت `<VideoPlayer>` با سه provider (aparat/youtube/direct).
- [ ] `sections.forum_questions.items` → لیست با badge resolution status.
- [ ] مصرف هرگونه `category_shortcuts` در صفحه‌ی فروم (اگر هست) حذف شود — جایگزین: `/v1/forum/categories` (ر.ک: `FRONTEND_FORUM_CATEGORIES.md`).

---

## ۷) سوالات متداول

**سوال:** اگر برای یک دستگاه/برند هیچ ویدیو یا سوال انجمنی ست نشده باشد چه می‌شود؟
**پاسخ:** `items` به‌صورت `[]` می‌آید. فرانت باید سکشن را skip کند (`items.length > 0` گارد بزند).

**سوال:** اگر سکشن `videos` در template هم خالی باشد و entity هم چیزی ست نکرده باشد چه؟
**پاسخ:** `items: []` + `title: null` می‌آید. هیچی render نشود.

**سوال:** آیا با تغییر شکل `image` در hero، فیلدهای قدیمی `image_desktop`/`image_mobile` هنوز کار می‌کنند؟
**پاسخ:** برای hero نه — این فیلد جدید است و فقط شکل جدید را برمی‌گرداند. برای `steps` که از قبل بود، بک‌اند هنوز `image_desktop`/`image_mobile` فلت می‌دهد (سازگاری backward).

**سوال:** ترتیب `forum_questions.items` چطور است؟
**پاسخ:** `ORDER BY published_at DESC` — یعنی جدیدترین اول.

**سوال:** آیا می‌توان لیست ویدیوها را به template اضافه کرد بدون اینکه per-entity override بشود؟
**پاسخ:** بله — فقط در page-content ادمین `template.videos.items` پر شود. تا وقتی entity خالی است، همان template برمی‌گردد.

---

پایان.
