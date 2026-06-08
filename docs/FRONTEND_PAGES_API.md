# مستند فرانت — صفحات Catalog (Device / Brand / Combined)

> نسخه ۲ | تاریخ ۱۴۰۵/۰۳/۰۲
> این مستند قراردادهای API و الگوی render در فرانت Next.js را برای سه نوع صفحه پوشش می‌دهد:
> - `/devices/{slug}` (Device page)
> - `/brands/{slug}` (Brand page)
> - `/devices/{deviceSlug}/{brandSlug}` (Combined Device × Brand page)

---

## ۱) اندپوینت‌ها

| نوع صفحه | URL فرانت | Endpoint backend | Auth | Cache |
|---|---|---|---|---|
| Device | `/devices/{slug}` | `GET /v1/catalog/devices/{slug}` | Bearer `INTERNAL_API_TOKEN` | `s-maxage=600` |
| Brand | `/brands/{slug}` | `GET /v1/catalog/brands/{slug}` | Bearer | `s-maxage=600` |
| Combined | `/devices/{deviceSlug}/{brandSlug}` | `GET /v1/catalog/devices/{deviceSlug}/{brandSlug}` | Bearer | `s-maxage=600` |
| Lists (Hero/Grid) | — | `GET /v1/catalog/devices` و `GET /v1/catalog/brands` | Public | `s-maxage=600` |
| Reviews per-device | — | `GET /v1/catalog/devices/{slug}/reviews?page=N&limit=M&sort=newest\|oldest\|top` | Public | `s-maxage=60` |
| POST review | — | `POST /v1/catalog/devices/{slug}/reviews` (json body) | Public + throttle 5/min | — |
| Like review | — | `POST /v1/catalog/reviews/{id}/like` | Public + throttle 30/min | — |
| Audio testimonials | — | `GET /v1/testimonials?device={slug}&limit=N` | Public | `s-maxage=300` |
| Activity stream | — | `GET /v1/activity/recent?device={slug}` | Public | `s-maxage=60` |
| Global settings | — | `GET /v1/settings/global` | Public | `s-maxage=600` |

تمام پاسخ‌های detail دارای structure section-based هستند (پایین ↓).

---

## ۲) شکل پاسخ مشترک — Sections

تمام سه endpoint detail (device, brand, combined) پاسخ به شکل زیر می‌فرستند:

```jsonc
{
  // ─── شناسه‌های اصلی ──────────────────────────────
  "id": 2,                     // فقط در device/brand
  "slug": "washing-machine",
  "label": "ماشین لباس‌شویی",
  "icon": "washing-machine",   // device-only
  "logo": "https://...",       // brand-only
  "thumbnail": "https://...",
  "tone": "tone-blue",

  // در combined endpoint:
  "device": { "id": 2, "slug": "...", "label": "...", "short_name": "...", "icon": "...", "thumbnail": "...", "tone": "..." },
  "brand":  { "id": 1, "slug": "...", "label": "...", "logo": "..." },

  // ─── SEO سراسری صفحه ────────────────────────────
  "meta_title": "...",
  "meta_description": "...",

  // ─── سکشن‌های صفحه ──────────────────────────────
  "sections": {
    "hero":          { "enabled": true, /* … fields … */ },
    "steps":         { "enabled": true, /* … */ },
    "live_activity": { "enabled": true, /* … */ },
    "content":       { "enabled": true, "html": "<p>…</p>" },
    "faq":           { "enabled": true, "items": [/* … */] },
    "brands":        { "enabled": true, "items": [/* … */] },    // فقط device page
    "devices":       { "enabled": true, "items": [/* … */] },    // فقط brand page
    "brand_other_devices": { "enabled": true, /* … */ },         // فقط combined page
    "testimonials":  { "enabled": true, "items": [/* … */] }
  }
}
```

**قانون طلایی:** هر سکشنی که `enabled: false` است را render **نکنید**. این بولی توسط ادمین per-page تنظیم می‌شود.

---

## ۳) جزئیات هر سکشن

### 3.1 `hero`

```jsonc
{
  "enabled": true,
  "badge": "سرویس تخصصی",          // متن کوتاه بالای تیتر (eyebrow)
  "title": "تعمیر لباس‌شویی",       // در combined: "تعمیر لباس‌شویی سامسونگ"
  "subtitle": "...",                // متن یک خط زیر تیتر
  "caption": "بیش از ۱۰ سال تجربه...",  // متن کوتاه‌تر زیر subtitle
  "tagline": "...",                 // فقط در brand
  "cta_primary":   { "label": "ثبت سفارش",  "url": "/order", "icon": "shopping-cart" },
  "cta_secondary": { "label": "تماس فوری",  "url": "tel:021…", "icon": "phone" }
}
```

**Component نمونه**:
```tsx
function Hero({ hero }: { hero: HeroSection }) {
  if (!hero.enabled) return null;
  return (
    <section className="hero">
      {hero.badge && <span className="eyebrow">{hero.badge}</span>}
      <h1>{hero.title}</h1>
      {hero.subtitle && <p className="subtitle">{hero.subtitle}</p>}
      {hero.caption && <p className="caption">{hero.caption}</p>}
      <div className="ctas">
        {hero.cta_primary?.label && (
          <a href={hero.cta_primary.url} className="btn-primary">
            <Icon name={hero.cta_primary.icon} />
            {hero.cta_primary.label}
          </a>
        )}
        {hero.cta_secondary?.label && (
          <a href={hero.cta_secondary.url} className="btn-secondary">
            <Icon name={hero.cta_secondary.icon} />
            {hero.cta_secondary.label}
          </a>
        )}
      </div>
    </section>
  );
}
```

### 3.2 `steps`

```jsonc
{
  "enabled": true,
  "image_desktop": "https://.../desktop.png",
  "image_mobile":  "https://.../mobile.png",
  "alt": "مراحل دریافت خدمات"
}
```

**Component نمونه** (responsive با `<picture>`):
```tsx
function Steps({ steps }: { steps: StepsSection }) {
  if (!steps.enabled || !steps.image_mobile) return null;
  return (
    <section className="steps">
      <picture>
        <source media="(min-width: 768px)" srcSet={steps.image_desktop} />
        <img src={steps.image_mobile} alt={steps.alt ?? 'مراحل خدمات'} loading="lazy" />
      </picture>
    </section>
  );
}
```

### 3.3 `live_activity`

```jsonc
{
  "enabled": true,
  "device_slug": "washing-machine",   // device-only context
  "brand_slug":  "samsung"            // combined-only
}
```

سپس component خود به `GET /v1/activity/recent?device={device_slug}` می‌زند (client-side stream).

### 3.4 `content`

```jsonc
{
  "enabled": true,
  "html": "<h2>درباره...</h2><p>…</p>"  // HTML پاکسازی‌شده در backend (TinyMCE → HtmlSanitizer)
}
```

```tsx
<div
  className="prose prose-rtl"
  dangerouslySetInnerHTML={{ __html: content.html }}
/>
```

HTML قبل از ذخیره با `Modules\CRM\Support\HtmlSanitizer` پاکسازی شده — allowlist محکم تگ/attribute، حذف script/style/iframe (به‌جز یوتیوب/آپارات/ویمیو/گوگل‌مپ/نشان)، بلاک `javascript:` در href/src، rel=`noopener noreferrer` خودکار روی target=`_blank`. **پیش از render نیاز به sanitization اضافی در فرانت نیست.**

### 3.5 `faq`

```jsonc
{
  "enabled": true,
  "items": [
    { "id": "01J…", "question": "تعمیر لباس‌شویی چقدر زمان می‌برد؟", "answer": "…" }
  ]
}
```

**Placeholderها در سوال/پاسخ خودکار جایگزین می‌شوند** (پیش از این در backend). به این معنی که اگر ادمین «{device}» نوشته باشد، در صفحه‌ی device برای washing-machine به «لباس‌شویی» تبدیل شده است. فرانت نیازی به جایگزینی ندارد.

### 3.6 `brands` (فقط در device page)

```jsonc
{
  "enabled": true,
  "items": [
    { "id": 1, "name": "ال‌جی", "slug": "lg", "logo": "https://..." }
  ]
}
```

اگر ادمین برند خاصی انتخاب نکرده باشد، **همه‌ی برندهای فعال** برمی‌گردد (fallback خودکار). فرانت تفاوتی نمی‌بیند.

### 3.7 `devices` (فقط در brand page)

```jsonc
{
  "enabled": true,
  "items": [
    { "id": 2, "label": "لباس‌شویی", "slug": "washing-machine", "href": "/devices/washing-machine", "icon": "...", "thumbnail": "...", "tone": "..." }
  ]
}
```

### 3.8 `brand_other_devices` (فقط در combined page)

```jsonc
{
  "enabled": true,
  "current_slug": "washing-machine",      // برای auto-center کارُسل و highlight کارت جاری
  "brand": { "slug": "samsung", "name": "سامسونگ" },
  "items": [
    {
      "id": 3,
      "name": "ماشین ظرفشویی",
      "shortName": "ظرفشویی",
      "slug": "dishwasher",
      "iconKey": "dishwasher",           // ↦ resolveDeviceIcon(...) در فرانت
      "thumbnail": "https://...",
      "tone": "tone-blue"
    }
  ]
}
```

**شکل items دقیقاً با prop `Device[]` در `BrandOtherDevices.tsx` سازگار است** — مستقیماً پاس دهید:

```tsx
<BrandOtherDevices
  brandName={data.brand.label}
  brandLatin={undefined /* یا متغیر دیگر */}
  brandSlug={data.brand.slug}
  currentDeviceSlug={data.sections.brand_other_devices.current_slug}
  currentDeviceName={data.device.short_name ?? data.device.label}
  devices={data.sections.brand_other_devices.items}
/>
```

### 3.9 `testimonials`

```jsonc
{
  "enabled": true,
  "items": [
    {
      "id": "01J…",
      "type": "audio",            // یا "text"
      "author_name": "علی",
      "topic": "تعمیر لباسشویی سامسونگ",
      "rating": 5,
      "audio_url": "https://…mp3",   // فقط type=audio
      "duration_seconds": 87,         // فقط type=audio
      "content": null                 // فقط type=text
    }
  ]
}
```

```tsx
{testimonials.items.map(t =>
  t.type === 'audio'
    ? <AudioTestimonial key={t.id} {...t} />
    : <TextTestimonial  key={t.id} {...t} />
)}
```

---

## ۴) Reviews (UGC نظرات کاربر)

دو endpoint مجزا:

### GET — نمایش لیست
```
GET /v1/catalog/devices/{slug}/reviews?page=1&limit=10&sort=newest|oldest|top
```

```jsonc
{
  "data": [
    {
      "id": "01J…",
      "author_name": "علی",
      "author_avatar": null,
      "is_verified": false,
      "is_expert": false,
      "rating": 5,
      "content": "…",
      "created_at": "2026-05-20T10:30:00Z",
      "likes": 12,
      "reply": null   // یا { author_name, author_avatar, is_expert, content, created_at }
    }
  ],
  "meta": { "total": 47, "page": 1, "limit": 10, "last_page": 5, "average_rating": 4.8 }
}
```

### POST — ثبت نظر جدید
```
POST /v1/catalog/devices/{slug}/reviews
Content-Type: application/json
Body: { "author_name": "...", "email": "...", "rating": 5, "content": "..." }
```
پاسخ 201:
```jsonc
{ "id": "01J…", "status": "pending", "message": "نظر شما دریافت شد و پس از بررسی نمایش داده می‌شود." }
```

### POST — لایک
```
POST /v1/catalog/reviews/{id}/like
```
پاسخ:
```jsonc
{ "likes": 13 }
```
هر IP فقط یک‌بار می‌تواند لایک کند (یونیک ایندکس backend).

---

## ۵) منطق Merge در Backend

برای هر فیلد سکشن، backend با priority زیر مقدار را انتخاب می‌کند — **فرانت همیشه یک شکل واحد دریافت می‌کند**:

| نوع صفحه | اولویت‌بندی |
|---|---|
| `/devices/{slug}` | per-device column ← template `device` |
| `/brands/{slug}` | per-brand column ← template `brand` |
| `/devices/{ds}/{bs}` | per-pair (DeviceBrandPage) ← per-device ← per-brand ← template `device_brand` ← template `device` |

برای **FAQ**:
1. union(دسته‌بندی‌های انتخابی، FAQهای منفرد) از sourceی که اول non-empty باشد
2. fallback به inline JSON قدیمی
3. fallback به template

برای **brands/devices/brand_other_devices**:
- اگر pivot خالی → fallback خودکار به همه‌ی موارد فعال (default = all)

برای **testimonials**:
1. picked از pivot (priority chain)
2. template `testimonial_ids` reference
3. fallback نهایی: همه‌ی reviewهای audio approved generic

---

## ۶) Placeholderها

این placeholderها در سوال/پاسخ FAQ و فیلدهای متنی template هنگام render در backend جایگزین می‌شوند:

| Placeholder | محتوا | در کدام صفحات |
|---|---|---|
| `{device}` | نام کوتاه دستگاه (مثلاً «لباس‌شویی») | device + combined |
| `{device_label}` | نام کامل دستگاه («ماشین لباس‌شویی») | device + combined |
| `{device_slug}` | slug دستگاه (`washing-machine`) | device + combined |
| `{brand}` | نام برند («سامسونگ») | brand + combined |
| `{brand_slug}` | slug برند (`samsung`) | brand + combined |
| `{page_title}` | تیتر صفحه | همه |

**این جایگزینی در سمت backend انجام می‌شود.** فرانت فقط مقدار نهایی را دریافت می‌کند.

---

## ۷) الگوهای سراسری (page-sections)

سه قالب سراسری در `Modules/Site/Config/page-sections.php` که توسط ادمین در `/admin/site/page-content/{slug}` ویرایش می‌شوند:

| Slug | کاربرد |
|---|---|
| `device` | پیش‌فرض صفحات `/devices/{slug}` — وقتی per-device خالی است |
| `brand` | پیش‌فرض صفحات `/brands/{slug}` |
| `device_brand` | پیش‌فرض صفحات `/devices/{ds}/{bs}` — مخصوص صفحات ترکیبی |

فرانت با این فایل کاری ندارد — فقط API را consume می‌کند.

---

## ۸) شکل response در Next.js (نمونه‌ی کامل)

```tsx
// app/devices/[slug]/page.tsx
export const revalidate = 600;

export default async function DevicePage({ params }: { params: { slug: string } }) {
  const res = await fetch(
    `${process.env.API_BASE_URL}/v1/catalog/devices/${params.slug}`,
    {
      headers: { Authorization: `Bearer ${process.env.INTERNAL_API_TOKEN}` },
      next: { revalidate: 600, tags: [`device:${params.slug}`] },
    }
  );
  if (res.status === 404) notFound();
  const data: DeviceDetailResponse = await res.json();

  return (
    <>
      <SEO title={data.meta_title} description={data.meta_description} />
      {data.sections.hero.enabled         && <Hero          {...data.sections.hero} />}
      {data.sections.steps.enabled        && <Steps         {...data.sections.steps} />}
      {data.sections.live_activity.enabled && <LiveActivity {...data.sections.live_activity} />}
      {data.sections.content.enabled      && <Content       html={data.sections.content.html} />}
      {data.sections.faq.enabled          && <Faq           items={data.sections.faq.items} />}
      {data.sections.brands.enabled       && <BrandsGrid    items={data.sections.brands.items} />}
      {data.sections.testimonials.enabled && <Testimonials  items={data.sections.testimonials.items} />}
    </>
  );
}
```

```tsx
// app/devices/[slug]/[brand]/page.tsx
export const revalidate = 600;

export default async function DeviceBrandPage({ params }: { params: { slug: string; brand: string } }) {
  const res = await fetch(
    `${process.env.API_BASE_URL}/v1/catalog/devices/${params.slug}/${params.brand}`,
    { headers: { Authorization: `Bearer ${process.env.INTERNAL_API_TOKEN}` }, next: { revalidate: 600 } }
  );
  if (res.status === 404) notFound();
  const data: DeviceBrandDetailResponse = await res.json();

  return (
    <>
      <SEO title={data.meta_title} description={data.meta_description} />
      {data.sections.hero.enabled    && <Hero {...data.sections.hero} />}
      {data.sections.steps.enabled   && <Steps {...data.sections.steps} />}
      {data.sections.live_activity.enabled && <LiveActivity {...data.sections.live_activity} />}
      {data.sections.content.enabled && <Content html={data.sections.content.html} />}
      {data.sections.faq.enabled     && <Faq items={data.sections.faq.items} />}
      {data.sections.brand_other_devices.enabled && (
        <BrandOtherDevices
          brandName={data.brand.label}
          brandSlug={data.brand.slug}
          currentDeviceSlug={data.sections.brand_other_devices.current_slug}
          currentDeviceName={data.device.short_name ?? data.device.label}
          devices={data.sections.brand_other_devices.items}
        />
      )}
      {data.sections.testimonials.enabled && <Testimonials items={data.sections.testimonials.items} />}
    </>
  );
}
```

---

## ۹) TypeScript types (پیشنهادی)

```ts
// types/catalog.ts

export type ToneClass = `tone-${'blue'|'green'|'cyan'|'sky'|'orange'|'amber'|'rose'|'violet'|'emerald'}` | null;

export interface CtaButton {
  label: string | null;
  url: string | null;
  icon: string | null;
}

export interface HeroSection {
  enabled: boolean;
  badge: string | null;
  title: string | null;
  subtitle: string | null;
  caption: string | null;
  tagline?: string | null;  // فقط brand
  cta_primary: CtaButton;
  cta_secondary: CtaButton;
}

export interface StepsSection {
  enabled: boolean;
  image_desktop: string | null;
  image_mobile: string | null;
  alt: string | null;
}

export interface LiveActivitySection {
  enabled: boolean;
  device_slug?: string;
  brand_slug?: string;
}

export interface ContentSection {
  enabled: boolean;
  html: string | null;
}

export interface FaqItem {
  id: string;
  question: string;
  answer: string;
}

export interface FaqSection {
  enabled: boolean;
  items: FaqItem[];
}

export interface BrandItem {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
}

export interface DeviceItem {
  id: number;
  label?: string;        // brand page
  name?: string;          // combined page
  shortName?: string;     // combined page
  slug: string;
  href?: string;
  iconKey?: string;       // combined page
  icon?: string;          // brand page
  thumbnail: string | null;
  tone: ToneClass;
}

export interface BrandsSection { enabled: boolean; items: BrandItem[]; }
export interface DevicesSection { enabled: boolean; items: DeviceItem[]; }

export interface BrandOtherDevicesSection {
  enabled: boolean;
  current_slug: string;
  brand: { slug: string; name: string };
  items: DeviceItem[];
}

export interface TestimonialItem {
  id: string;
  type: 'audio' | 'text';
  author_name: string | null;
  topic: string | null;
  rating: number;
  audio_url: string | null;
  duration_seconds: number | null;
  content: string | null;
}

export interface TestimonialsSection { enabled: boolean; items: TestimonialItem[]; }

// ── responses
export interface DeviceDetailResponse {
  id: number; slug: string; label: string; icon: string | null;
  thumbnail: string | null; tone: ToneClass;
  meta_title: string | null; meta_description: string | null;
  sections: {
    hero: HeroSection; steps: StepsSection; live_activity: LiveActivitySection;
    content: ContentSection; faq: FaqSection;
    brands: BrandsSection; testimonials: TestimonialsSection;
  };
}

export interface BrandDetailResponse {
  id: number; slug: string; label: string; logo: string | null;
  tone: ToneClass; bg: string | null;
  meta_title: string | null; meta_description: string | null;
  sections: {
    hero: HeroSection; steps: StepsSection; live_activity: LiveActivitySection;
    content: ContentSection; faq: FaqSection;
    devices: DevicesSection; testimonials: TestimonialsSection;
  };
}

export interface DeviceBrandDetailResponse {
  device: { id: number; slug: string; label: string; short_name: string | null; icon: string | null; thumbnail: string | null; tone: ToneClass };
  brand:  { id: number; slug: string; label: string; logo: string | null };
  meta_title: string | null; meta_description: string | null;
  sections: {
    hero: HeroSection; steps: StepsSection; live_activity: LiveActivitySection;
    content: ContentSection; faq: FaqSection;
    brand_other_devices: BrandOtherDevicesSection;
    testimonials: TestimonialsSection;
  };
}
```

---

## ۱۰) چک‌لیست انطباق فرانت

برای هر سه نوع صفحه:
- [ ] از `Authorization: Bearer ${INTERNAL_API_TOKEN}` در fetchهای detail استفاده شود
- [ ] هر سکشنی که `enabled: false` است **render نشود**
- [ ] فیلد `content.html` با `dangerouslySetInnerHTML` render شود — backend sanitize کرده است
- [ ] `iframe`های موجود در content از دامنه‌های مجاز (youtube/aparat/vimeo/google maps/neshan) هستند — قبل از render نیاز به فیلتر اضافی نیست
- [ ] `picture` با `<source media="(min-width:768px)">` برای `steps`
- [ ] لیست برندها/دستگاه‌ها در سکشن مربوطه **حتی اگر ادمین انتخاب نکرده باشد همیشه non-empty است** (fallback به همه‌ی موارد فعال)
- [ ] در combined page از prop `current_slug` در `BrandOtherDevices` برای auto-center استفاده شود

---

## ۱۱) Caching/Revalidation در Next.js

```tsx
// در هر page detail:
export const revalidate = 600;

// در fetch:
{ next: { revalidate: 600, tags: [`device:${slug}`, `brand:${slug}`, `device-brand:${ds}:${bs}`] } }
```

پس از ویرایش در پنل ادمین، backend می‌تواند با webhook `revalidateTag(...)` کش را invalidate کند (اگر این endpoint را اضافه کنید).
