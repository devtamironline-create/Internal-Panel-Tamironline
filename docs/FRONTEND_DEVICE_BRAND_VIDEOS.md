# مستند فرانت — سکشن ویدیوها در صفحات دستگاه و برند

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> یک سکشن جدید `videos` به پاسخ `/v1/catalog/devices/{slug}` و
> `/v1/catalog/brands/{slug}` اضافه شد. منطق template/override مانند بقیه‌ی
> سکشن‌هاست: یک پیش‌فرض مشترک از الگو، با امکان override per-entity.

---

## ۱) شکل پاسخ

پاسخ هر دو endpoint دستگاه و برند حالا شامل سکشن جدید است:

```jsonc
{
  // ... بقیه‌ی سکشن‌ها (hero, steps, content, faq, testimonials, ...)
  "sections": {
    "videos": {
      "enabled": true,
      "title": "آموزش‌های ویدیویی",        // از template (یا null)
      "subtitle": "نمونه ویدیوهای تعمیر",   // از template (یا null)
      "items": [
        {
          "title": "نحوه‌ی تعمیر موتور لباس‌شویی",
          "aparat_id": "ABC123",          // اولویت ۱
          "youtube_id": null,             // اولویت ۲
          "video_url": null,              // اولویت ۳ (mp4 مستقیم)
          "description": "ویدیوی آموزشی...",
          "poster_url": "https://...cover.webp"
        },
        // ...
      ]
    }
  }
}
```

---

## ۲) منطق merge

برای هر دستگاه/برند:

```
items =
  entity.videos اگر آرایه‌ی غیرخالی باشد
  → otherwise: template.videos.items
```

این یعنی:
- ادمین در پنل `/admin/site/page-content/device` (یا `brand`) یک‌بار لیست
  ویدیوهای پیش‌فرض را تنظیم می‌کند.
- این لیست در **همه‌ی** صفحات دستگاه/برند نمایش داده می‌شود.
- اگر برای یک دستگاه/برند خاص، در پنل ویرایش آن (`/admin/crm/devices/123/edit`
  یا `/admin/crm/brands/5/edit`) لیست `videos` پر شود، آن لیست **جایگزین**
  template می‌شود (نه ضمیمه).

`title` و `subtitle` همیشه از template می‌آیند.

---

## ۳) انتخاب source ویدیو در فرانت

اولویت رندر (بسته به اینکه کدام فیلد در آیتم پر است):

```tsx
function pickVideoSource(item: VideoItem): { kind: "aparat" | "youtube" | "url"; src: string } | null {
  if (item.aparat_id) return { kind: "aparat", src: item.aparat_id };
  if (item.youtube_id) return { kind: "youtube", src: item.youtube_id };
  if (item.video_url) return { kind: "url", src: item.video_url };
  return null;
}
```

### URL embed برای Aparat
```
https://www.aparat.com/video/video/embed/videohash/{aparat_id}/vt/frame
```

### URL embed برای YouTube
```
https://www.youtube.com/embed/{youtube_id}
```

### Direct mp4
```tsx
<video src={item.video_url} controls poster={item.poster_url ?? undefined} />
```

---

## ۴) رندر امن

```tsx
const videos = data.sections.videos;
if (!videos?.enabled || !videos?.items?.length) return null;

return (
  <section>
    {videos.title && <h2>{videos.title}</h2>}
    {videos.subtitle && <p>{videos.subtitle}</p>}

    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      {videos.items.map((v, i) => {
        const source = pickVideoSource(v);
        if (!source) return null;

        return (
          <article key={i}>
            <VideoPlayer kind={source.kind} src={source.src} poster={v.poster_url} title={v.title} />
            <h3>{v.title}</h3>
            {v.description && <p>{v.description}</p>}
          </article>
        );
      })}
    </div>
  </section>
);
```

---

## ۵) Cache و revalidation

پاسخ‌های catalog همچنان `Cache-Control: max-age=600, s-maxage=600` دارند.
هنگام به‌روزرسانی Device/Brand entity یا template، باید کش فرانت با تگ
مرتبط revalidate شود. برای device/brand فعلاً webhook مرتبط فعال نیست —
TTL کافی است.

---

## ۶) Schema/Type

```ts
export type VideoItem = {
  title: string | null;
  aparat_id: string | null;
  youtube_id: string | null;
  video_url: string | null;
  description: string | null;
  poster_url: string | null;
};

export type VideosSection = {
  enabled: boolean;
  title: string | null;
  subtitle: string | null;
  items: VideoItem[];
};
```

---

## ۷) Checklist مهاجرت فرانت

- [ ] Type جدید `VideosSection` به schema device و brand اضافه شود.
- [ ] کامپوننت `<VideoPlayer>` برای سه provider (aparat/youtube/direct mp4).
- [ ] گارد `enabled === true` و `items.length > 0` قبل از render.
- [ ] iframe برای aparat/youtube با `loading="lazy"` و `allowFullScreen`.
- [ ] برای SEO: schema.org `VideoObject` JSON-LD اضافه شود اگر عنوان/توضیح/poster موجود است.

---

## ۸) سوالات متداول

**سوال:** اگر هم برای یک دستگاه ویدیو ست شده باشد و هم template، کدام برنده است؟
**پاسخ:** entity برنده است. template به‌عنوان fallback است.

**سوال:** آیا می‌توان از template به entity «اضافه» کرد (append)؟
**پاسخ:** خیر — منطق فعلی override کامل است. اگر چنین چیزی نیاز دارید، بفرمایید تا اضافه شود.

**سوال:** ترتیب نمایش ویدیوها چطور تنظیم می‌شود؟
**پاسخ:** به ترتیب درج در آرایه (در پنل ادمین، با drag-and-drop در repeater).

**سوال:** برای صفحات ترکیبی (`device_brand`) چی؟
**پاسخ:** فعلاً این سکشن فقط در `device` و `brand` فعال است. اگر برای ترکیبی هم نیاز دارید، اضافه می‌شود.

---

پایان.
