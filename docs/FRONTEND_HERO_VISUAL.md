# مستند فرانت — Hero Visual (۲ تصویر دسکتاپ + ۱ تصویر موبایل)

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> **این مستند جایگزین قبلی است** برای فیلد `sections.hero.image` در همه‌ی
> صفحاتی که Hero دارند (home / about / services / contact / device / brand /
> device_brand). فیلد `image` که قبلاً به‌شکل `responsive_image` بود
> (دسکتاپ + موبایل از یک محتوای واحد)، حالا به نوع جدید `hero_visual`
> ارتقا یافته که سه slot مستقل دارد.

---

## ۱) چرا تغییر کرد؟

یک نمونه نگاهی به `frontend/src/features/home/components/Hero.tsx`:

```tsx
{/* تصویر چپ دسکتاپ — صرفاً دکوراتیو */}
<Image src="/images/home/hero-desktop-left.webp" alt="" />

{/* تصویر راست دسکتاپ — صرفاً دکوراتیو */}
<Image src="/images/home/hero-desktop-right.webp" alt="" />

{/* تصویر مخصوص موبایل — banner */}
<Image src="/images/home/hero-mobile.png" alt="بنر ثبت سفارش سریع" />
```

این **سه تصویر مختلف** است نه دو نسخه‌ی crop شده‌ی یک تصویر. شکل قبلی
`responsive_image` این کاربرد را تأمین نمی‌کرد. شکل جدید سه slot دارد:

- `desktop_left` — تصویر چپ Hero در دسکتاپ
- `desktop_right` — تصویر راست Hero در دسکتاپ
- `mobile` — تصویر اختصاصی نسخه‌ی موبایل

هر کدام `{url, alt}` مستقل دارد — برای SEO و screen reader.

---

## ۲) شکل پاسخ

```jsonc
"sections": {
  "hero": {
    "enabled": true,
    "title": "...",
    "subtitle": "...",
    // ... (سایر فیلدها بدون تغییر)
    "image": {
      "desktop_left": {
        "url": "https://.../hero-desktop-left.webp",
        "alt": "تکنسین در حال تعمیر در سمت چپ"
      },
      "desktop_right": {
        "url": "https://.../hero-desktop-right.webp",
        "alt": "ابزارهای تعمیر در سمت راست"
      },
      "mobile": {
        "url": "https://.../hero-mobile.png",
        "alt": "بنر ثبت سفارش سریع"
      }
    }
  }
}
```

- هر `url` و `alt` می‌تواند `null` باشد.
- اگر یک slot خالی است، فرانت باید آن المنت را skip کند (نه `src=""`).
- بک‌اند **همیشه** این شکل را برمی‌گرداند — حتی اگر DB هنوز شکل قدیمی
  `{desktop, mobile}` را داشته باشد. تبدیل خودکار: `desktop` می‌رود در
  `desktop_left` و `desktop_right` خالی می‌ماند.

---

## ۳) منطق merge per-slot

برای صفحات entity (device/brand) که override per-entity دارند:

| Endpoint | اولویت برای هر slot |
|---|---|
| `/v1/catalog/devices/{slug}` | `device.hero_image[slot]` > `template.device.hero.image[slot]` |
| `/v1/catalog/brands/{slug}` | `brand.hero_image[slot]` > `template.brand.hero.image[slot]` |
| `/v1/catalog/devices/{d}/{b}` | `device.hero_image[slot]` > `brand.hero_image[slot]` > `template.device_brand.hero.image[slot]` |

هر slot به‌صورت مستقل merge می‌شود — مثلاً اگر برند فقط `desktop_left` ست
کرده باشد، `desktop_right` و `mobile` از template می‌آیند.

---

## ۴) Type / Schema

```ts
type HeroSlot = { url: string | null; alt: string | null };

export type HeroVisual = {
  desktop_left: HeroSlot;
  desktop_right: HeroSlot;
  mobile: HeroSlot;
};
```

Zod نمونه:

```ts
const heroSlotSchema = z.object({
  url: z.string().nullable(),
  alt: z.string().nullable(),
});

export const heroVisualSchema = z.object({
  desktop_left: heroSlotSchema,
  desktop_right: heroSlotSchema,
  mobile: heroSlotSchema,
});
```

---

## ۵) الگوی render

```tsx
type HeroProps = {
  // ...
  image?: HeroVisual | null;
};

function HeroVisuals({ image }: { image: HeroVisual | null | undefined }) {
  if (!image) return null;
  const dl = image.desktop_left;
  const dr = image.desktop_right;
  const m = image.mobile;

  return (
    <>
      {/* دسکتاپ — هر دو slot به‌عنوان decoration */}
      {dl.url && (
        <div aria-hidden={!dl.alt} className="hidden lg:flex absolute inset-y-0 right-0 ...">
          <Image src={dl.url} alt={dl.alt ?? ""} width={400} height={500} />
        </div>
      )}
      {dr.url && (
        <div aria-hidden={!dr.alt} className="hidden lg:flex absolute inset-y-0 left-0 ...">
          <Image src={dr.url} alt={dr.alt ?? ""} width={400} height={500} />
        </div>
      )}

      {/* موبایل — یک banner */}
      {m.url && (
        <div className="flex justify-center md:hidden">
          <Image src={m.url} alt={m.alt ?? ""} width={300} height={100} priority />
        </div>
      )}
    </>
  );
}
```

نکته‌ها:
- اگر `alt` خالی است (`null` یا empty) و تصویر صرفاً دکوراتیو است،
  `alt=""` + `aria-hidden="true"` بزنید تا screen reader آن را skip کند.
- اگر تصویر معنادار است (مثل banner موبایل که CTA دارد)، alt را بنویسید.

---

## ۶) تطبیق صفحات

| صفحه | path | فیلد |
|---|---|---|
| Home | `sections.hero.image` | `HeroVisual` |
| About | `sections.hero.poster` | `HeroVisual` |
| Services | `sections.hero.image` | `HeroVisual` |
| Contact | `sections.hero.image` | `HeroVisual` |
| Device | `sections.hero.image` | `HeroVisual` |
| Brand | `sections.hero.image` | `HeroVisual` |
| Device × Brand | `sections.hero.image` | `HeroVisual` |

`sections.steps.image` و `sections.layout.logo` همچنان از نوع
**responsive_image** قدیمی هستند (یک تصویر، دو crop) — تغییری نکرده‌اند.

---

## ۷) سوالات متداول

**سوال:** برای صفحات entity، آیا برند می‌تواند فقط یکی از سه slot را override کند؟
**پاسخ:** بله. هر slot مستقل از بقیه merge می‌شود.

**سوال:** اگر admin هیچ تصویری ست نکند چه می‌شود؟
**پاسخ:** هر سه slot به شکل `{url: null, alt: null}` می‌آیند. فرانت باید
هر slot را با گارد `if (slot.url)` بررسی کند و در نبود تصویر، fallback
دکوراتیو محلی نشان دهد یا چیزی render نکند.

**سوال:** alt تصاویر صرفاً دکوراتیو دسکتاپ باید چه باشد؟
**پاسخ:** برای SEO admin می‌تواند alt بنویسد ولی برای دسترسی‌پذیری بهتر
است در render `alt=""` + `aria-hidden="true"` بزنید. هیچ‌گاه alt را با
محتوای دکوراتیو پر نکنید — این noise برای screen reader است.

**سوال:** آیا shape قدیمی responsive_image در پاسخ همچنان کار می‌کند؟
**پاسخ:** نه برای `hero.image` — حتی اگر DB قدیمی باشد، بک‌اند به شکل
جدید normalize می‌کند. ولی برای `steps.image` و `layout.logo` که هنوز
responsive_image هستند، شکل قبلی (`{desktop, mobile}` با per-slot alt)
حفظ شده.

---

## ۸) Checklist مهاجرت فرانت

- [ ] Type `HeroVisual` جایگزین شکل قدیمی شود.
- [ ] جای `image.desktop.url` (دو-اسلاتی) → `image.desktop_left.url` / `image.desktop_right.url`.
- [ ] جای `image.mobile.url` → بدون تغییر (نام slot یکی است).
- [ ] گارد per-slot قبل از render هر تصویر (`if (slot.url)`).
- [ ] alt های خالی + `aria-hidden="true"` برای دکوراتیو.

---

پایان.
