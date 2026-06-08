# مستند فرانت — تغییر شکل `responsive_image` (alt مجزا برای دسکتاپ و موبایل)

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> این یک **breaking change** در پاسخ همه‌ی فیلدهایی از نوع `responsive_image`
> در `/v1/pages/{slug}` است (hero/steps/poster و …). هدف: امکان تنظیم متن جایگزین
> (alt) متفاوت برای نسخه‌ی دسکتاپ و موبایل، حتی وقتی URL یکسان است.

---

## ۱) چرا این تغییر؟

تا قبل، فیلد `responsive_image` فقط دو URL ذخیره می‌کرد:

```jsonc
"image": { "desktop": "https://.../d.webp", "mobile": "https://.../m.webp" }
```

مشکلات:
- alt برای هیچ‌کدام ذخیره نمی‌شد — فرانت مجبور بود از روی subtitle یا یک ثابت بسازد.
- اگر crop دسکتاپ و موبایل متفاوت بود (مثلاً wide-shot vs portrait)، نمی‌شد alt متفاوت داد. این هم برای SEO ضعیف بود، هم برای دسترسی‌پذیری.

از این پس admin می‌تواند برای هر slot، URL **و** alt جداگانه تنظیم کند.

---

## ۲) شکل قبلی (deprecated)

```jsonc
"image": {
  "desktop": "https://.../desktop.webp",
  "mobile":  "https://.../mobile.webp"
}
```

پشتیبانی از خواندن این شکل **حذف نشده** — بک‌اند خودکار آن را به شکل جدید
upgrade می‌کند. ولی شما به‌عنوان فرانت نباید بر این شکل تکیه کنید.

---

## ۳) شکل جدید پاسخ

```jsonc
"image": {
  "desktop": { "url": "https://.../desktop.webp", "alt": "تکنسین در حال تعمیر ماشین لباس‌شویی در آشپزخانه" },
  "mobile":  { "url": "https://.../mobile.webp",  "alt": "دست‌های تکنسین روی پنل ماشین لباس‌شویی" }
}
```

**قراردادها:**

- هم `url` و هم `alt` می‌توانند `null` باشند.
- اگر admin هیچ آپلودی نکرده باشد، slot به‌صورت `{url: null, alt: null}` می‌آید.
- اگر admin URL گذاشت ولی alt را خالی گذاشت، فرانت باید fallback داشته باشد
  (مثلاً عنوان سکشن یا یک رشته‌ی پیش‌فرض خالی برای تصاویر دکوراتیو).
- **همیشه** ساختار `{desktop: {...}, mobile: {...}}` تضمین می‌شود — حتی برای داده‌ی قدیمی DB.

---

## ۴) منطق read امن

```tsx
type ResponsiveImage = {
  desktop: { url: string | null; alt: string | null };
  mobile:  { url: string | null; alt: string | null };
};

function pickImage(img: ResponsiveImage | undefined | null) {
  if (!img) return null;
  const desktop = img.desktop?.url;
  const mobile  = img.mobile?.url;
  if (!desktop && !mobile) return null;
  return {
    desktopUrl: desktop ?? mobile,
    desktopAlt: img.desktop?.alt ?? "",
    mobileUrl:  mobile ?? desktop,
    mobileAlt:  img.mobile?.alt ?? img.desktop?.alt ?? "",
  };
}
```

---

## ۵) رندر صحیح در فرانت

```tsx
const data = pickImage(content.steps.image);
if (!data) return null;

return (
  <picture>
    {/* mobile-first */}
    <source media="(max-width: 767px)" srcSet={data.mobileUrl} />
    <img
      src={data.desktopUrl}
      alt={data.desktopAlt}              {/* alt = desktop alt; مرورگر در حالت موبایل override نمی‌کند */}
      width={1200}
      height={500}
      loading="lazy"
    />
  </picture>
);
```

> **هشدار:** المنت `<img>` فقط یک attribute `alt` دارد — مرورگر بسته به viewport آن را عوض نمی‌کند.
> اگر می‌خواهید alt مخصوص موبایل **واقعاً** اعمال شود، باید با CSS یک `<img>` را hidden و دیگری visible کنید.
> در عمل، استفاده از `alt` دسکتاپ کافی است؛ alt موبایل برای ابزارهای SEO و parser کانتنت ارزشمند است.

### الگوی mobile-only render (دو `<img>`)

اگر برایتان مهم است که alt موبایل واقعاً به assistive tech برسد:

```tsx
<>
  <img
    className="hidden md:block"
    src={data.desktopUrl}
    alt={data.desktopAlt}
    loading="lazy"
  />
  <img
    className="block md:hidden"
    src={data.mobileUrl}
    alt={data.mobileAlt}
    loading="lazy"
  />
</>
```

این الگو دو request جدا می‌زند مگر URLها یکسان باشند که browser cache می‌کند.

---

## ۶) Schema/Type جدید برای فرانت

اگر از zod یا valibot استفاده می‌کنید:

```ts
import { z } from "zod";

const responsiveSlot = z.object({
  url: z.string().nullable(),
  alt: z.string().nullable(),
});

export const responsiveImageSchema = z.object({
  desktop: responsiveSlot,
  mobile:  responsiveSlot,
});

export type ResponsiveImage = z.infer<typeof responsiveImageSchema>;
```

برای backward compat در دوران migration، یک parser اضافی:

```ts
// در صورتی که داده‌ی legacy از جای دیگری بیاید
function parseLegacy(raw: any): ResponsiveImage {
  if (typeof raw?.desktop === "string") {
    return {
      desktop: { url: raw.desktop || null, alt: null },
      mobile:  { url: raw.mobile || null,  alt: null },
    };
  }
  return responsiveImageSchema.parse(raw);
}
```

ولی **پاسخ‌های `/v1/pages/*` همیشه شکل جدید را برمی‌گردانند** — این parser فقط برای داده‌ی legacy از منابع دیگر.

---

## ۷) لیست صفحاتی که تحت تأثیر این تغییر هستند

هر سکشنی که در `Modules/Site/Config/page-sections.php` فیلد `type: responsive_image` داشته باشد، شکل پاسخش تغییر می‌کند. در حال حاضر:

| Page slug | Section | Field |
|---|---|---|
| `home` | `steps` | `image` |
| `about` | `hero` | `poster` |
| `about` | `steps` | `image` |
| `services` | (متغیر — بسته به نسخه) | — |
| `device` | `steps` | `image` |
| `brand` | `steps` | `image` |
| `device_brand` | `steps` | `image` |
| `layout` | `header.logo` | `logo` |
| `layout` | `footer.logo` | `logo` |
| `layout` | `footer.app_download.image` | `image` |

تمام این فیلدها همان شکل جدید را بازمی‌گردانند.

---

## ۸) Checklist مهاجرت فرانت

- [ ] Type/Schema همه‌ی responsive imageها به شکل جدید (`{desktop: {url, alt}, mobile: {url, alt}}`).
- [ ] جای `content.steps.image.desktop` (string) → `content.steps.image.desktop.url`.
- [ ] استفاده از `image.desktop.alt` در attribute `alt` المنت `<img>` به‌جای رشته‌ی ثابت.
- [ ] اگر فقط یک `<img>` می‌گذارید، alt دسکتاپ کافی است (مرورگر alt را با viewport عوض نمی‌کند).
- [ ] اگر برای دسترسی‌پذیری مهم است، الگوی دو `<img>` (hidden/visible با CSS) را به کار ببرید.
- [ ] تست edge case: slot خالی (`{url: null, alt: null}`) → render کلید را skip کنید.
- [ ] تست edge case: alt خالی ولی url پر → از fallback مناسب استفاده کنید (نه `alt={undefined}`).

---

## ۹) سوالات متداول

**سوال:** اگر URL هر دو slot یکی باشد، مرورگر بنرها را دوبار دانلود می‌کند؟
**پاسخ:** خیر — request یکسان، cache hit در request دوم.

**سوال:** اگر admin alt موبایل را خالی بگذارد چه می‌شود؟
**پاسخ:** بک‌اند `null` می‌دهد. فرانت باید fallback به alt دسکتاپ یا رشته‌ی خالی داشته باشد.

**سوال:** آیا پاسخ `/v1/pages/{slug}` می‌تواند برای داده‌ی موجود همچنان شکل قدیمی برگرداند؟
**پاسخ:** نه. حتی اگر DB هنوز شکل قدیمی را ذخیره داشته باشد، بک‌اند هنگام پاسخ‌دهی به شکل جدید نرمالایز می‌کند.

**سوال:** آیا این تغییر روی فیلد `banner_zone` تأثیر دارد؟
**پاسخ:** نه. `banner_zone` ساختار خودش (`{zone, banners[], updated_at}`) را دارد.

---

پایان.
