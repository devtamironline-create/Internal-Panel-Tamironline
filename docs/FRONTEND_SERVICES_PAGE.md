# مستند فرانت — صفحه‌ی خدمات (`/services`)

> این صفحه ترکیبی از دو منبع داده است:
> - **محتوای صفحه** (hero، intro، تیترهای سکشن، CTA، SEO، و انتخاب دستی) از page-content — قابل ویرایش در `/admin/site/page-content` → صفحه‌ی «خدمات (/services)»
> - **داده‌ی زنده** (دسته‌بندی دستگاه‌ها + برندها) از catalog endpoints

---

## ۱) اندپوینت‌ها

| داده | Endpoint | Auth | Cache |
|---|---|---|---|
| محتوای صفحه‌ی services | `GET /v1/pages/services` | Public | `s-maxage=300` |
| دسته‌بندی دستگاه‌ها + دستگاه‌های هر دسته | `GET /v1/catalog/device-categories` | Public | `s-maxage=600` |
| همه‌ی دستگاه‌ها (flat) | `GET /v1/catalog/devices` (با `?category={slug}` اختیاری) | Public | `s-maxage=600` |
| برندها | `GET /v1/catalog/brands` | Public | `s-maxage=600` |

---

## ۲) `GET /v1/pages/services`

محتوای editable توسط ادمین. فقط سکشن‌های منتشرشده برمی‌گردند.

```jsonc
{
  "slug": "services",
  "sections": {
    "hero": {
      "badge": "خدمات تعمیرآنلاین",
      "title": "همه‌ی خدمات تعمیر لوازم خانگی",
      "subtitle": "..."
    },
    "intro": { "html": "<p>...</p>" },
    "categories": {
      "title": "دسته‌بندی خدمات",
      "subtitle": "...",
      // اگر ادمین دستی انتخاب/مرتب کرده باشد، اینجا hydrate می‌شود.
      // اگر خالی باشد، این کلید نخواهد بود → از /v1/catalog/device-categories استفاده کن.
      "category_ids_items": [
        { "id": 1, "name": "لوازم آشپزخانه", "slug": "kitchen-appliances", "icon": "utensils-crossed", "tone": "tone-blue", "description": "..." }
      ]
    },
    "brands": {
      "title": "برندهای تحت پوشش",
      "subtitle": "...",
      "brand_ids_items": [
        { "id": 4, "name": "سامسونگ", "slug": "samsung", "logo": "https://..." }
      ]
    },
    "cta": {
      "title": "نیاز به تعمیر دارید؟",
      "subtitle": "...",
      "button_label": "ثبت سفارش",
      "button_url": "/order"
    },
    "faq": {
      "title": "سوالات متداول",
      "subtitle": "...",
      // اگر دسته انتخاب شده باشد، به‌صورت تب‌بندی‌شده hydrate می‌شود:
      "category_ids_items": [
        { "id": 1, "slug": "support", "name": "پشتیبانی", "items": [ { "id": "01J…", "question": "…", "answer": "…" } ] }
      ],
      // یا سوالات منفرد:
      "faq_ids_items": [ { "id": "01J…", "question": "…", "answer": "…" } ]
    },
    "testimonials": {
      "title": "نظر مشتریان ما",
      "subtitle": "...",
      "testimonial_ids_items": [
        { "id": "01J…", "customer_name": "علی", "topic": "…", "rating": 5, "audio_url": "https://…", "duration_seconds": 87, "published_at": "2026-05-20T…Z" }
      ]
    },
    "seo": { "meta_title": "...", "meta_description": "..." }
  }
}
```

> **نکته:** اگر ادمین سکشنی را منتشر نکرده باشد یا فیلد reference خالی باشد، آن کلید در پاسخ نخواهد بود. همیشه با `?.` و fallback handle کنید.

---

## ۳) `GET /v1/catalog/device-categories`

لیست کامل دسته‌بندی‌های فعال + دستگاه‌های هر دسته (یک call برای کل منو/گرید).

```jsonc
{
  "data": [
    {
      "id": 1,
      "name": "لوازم آشپزخانه",
      "slug": "kitchen-appliances",
      "icon": "utensils-crossed",   // lucide key ↦ resolveDeviceIcon(...)
      "tone": "tone-blue",
      "description": "...",
      "devices": [
        {
          "id": 2,
          "label": "تعمیر لباس‌شویی",
          "slug": "washing-machine",
          "href": "/devices/washing-machine",
          "icon": "washing-machine",
          "thumbnail": "https://...",
          "tone": "tone-blue"
        }
      ]
    }
  ]
}
```

---

## ۴) `GET /v1/catalog/brands`

```jsonc
{
  "data": [
    { "id": 4, "name": "سامسونگ", "slug": "samsung", "logo": "https://..." }
  ],
  "meta": { "total": 11 }
}
```
برای فیلتر برندهای یک دستگاه: `GET /v1/catalog/brands?device={deviceSlug}`.

---

## ۵) منطق ترکیب در فرانت (`services/page.tsx`)

```tsx
// app/services/page.tsx
export const revalidate = 600;

async function fetchJson<T>(path: string): Promise<T> {
  const res = await fetch(`${process.env.API_BASE_URL}${path}`, {
    next: { revalidate: 600, tags: ['services'] },
  });
  return res.json();
}

export default async function ServicesPage() {
  const [page, cats, brands] = await Promise.all([
    fetchJson<ServicesPageResponse>('/v1/pages/services'),
    fetchJson<{ data: DeviceCategory[] }>('/v1/catalog/device-categories'),
    fetchJson<{ data: Brand[] }>('/v1/catalog/brands'),
  ]);

  const s = page.sections;

  // اگر ادمین دستی انتخاب کرده بود از همان استفاده کن، وگرنه لیست کامل API
  const categories = s.categories?.category_ids_items?.length
    ? s.categories.category_ids_items
    : cats.data;

  const brandList = s.brands?.brand_ids_items?.length
    ? s.brands.brand_ids_items
    : brands.data;

  return (
    <>
      <SEO title={s.seo?.meta_title} description={s.seo?.meta_description} />

      {s.hero && (
        <Hero badge={s.hero.badge} title={s.hero.title} subtitle={s.hero.subtitle} />
      )}

      {s.intro?.html && (
        <div className="prose prose-rtl" dangerouslySetInnerHTML={{ __html: s.intro.html }} />
      )}

      {/* دسته‌بندی‌ها — گروه‌بندی‌شده */}
      <CategoriesSection
        title={s.categories?.title ?? 'دسته‌بندی خدمات'}
        subtitle={s.categories?.subtitle}
        categories={categories}
      />

      {/* برندها */}
      <BrandsSection
        title={s.brands?.title ?? 'برندهای تحت پوشش'}
        subtitle={s.brands?.subtitle}
        brands={brandList}
      />

      {/* FAQ — انتخاب از بانک؛ خالی = fixture استاتیک فرانت */}
      {(s.faq?.faq_ids_items?.length || s.faq?.category_ids_items?.length) && (
        <FaqSection
          title={s.faq.title ?? 'سوالات متداول'}
          subtitle={s.faq.subtitle}
          categories={s.faq.category_ids_items ?? []}
          items={s.faq.faq_ids_items ?? []}
        />
      )}

      {/* نظرات — انتخاب از بانک؛ خالی = fixture استاتیک */}
      {s.testimonials?.testimonial_ids_items?.length && (
        <TestimonialsSection
          title={s.testimonials.title ?? 'نظر مشتریان'}
          subtitle={s.testimonials.subtitle}
          items={s.testimonials.testimonial_ids_items}
        />
      )}

      {s.cta && (
        <CtaBanner title={s.cta.title} subtitle={s.cta.subtitle}
                   label={s.cta.button_label} url={s.cta.button_url} />
      )}
    </>
  );
}
```

> **توجه به نوع داده‌ی categories:** هنگام انتخاب دستی، آیتم‌ها فاقد `devices[]` هستند (فقط متادیتای دسته). اگر برای گرید نیاز به دستگاه‌های هر دسته دارید، **همیشه از `/v1/catalog/device-categories` استفاده کنید** و انتخاب دستی را فقط برای فیلتر/ترتیب slugها به‌کار ببرید:
> ```tsx
> const orderedSlugs = s.categories?.category_ids_items?.map(c => c.slug);
> const categories = orderedSlugs?.length
>   ? orderedSlugs.map(slug => cats.data.find(c => c.slug === slug)).filter(Boolean)
>   : cats.data;
> ```

---

## ۶) TypeScript types

```ts
export interface DeviceCategory {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  tone: string | null;
  description: string | null;
  devices?: DeviceListItem[];   // فقط در /v1/catalog/device-categories
}

export interface DeviceListItem {
  id: number;
  label: string;
  slug: string;
  href: string;
  icon: string | null;
  thumbnail: string | null;
  tone: string | null;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
}

export interface ServicesPageResponse {
  slug: 'services';
  sections: {
    hero?:       { badge?: string; title?: string; subtitle?: string };
    intro?:      { html?: string };
    categories?: { title?: string; subtitle?: string; category_ids_items?: DeviceCategory[] };
    brands?:     { title?: string; subtitle?: string; brand_ids_items?: Brand[] };
    cta?:        { title?: string; subtitle?: string; button_label?: string; button_url?: string };
    faq?: {
      title?: string;
      subtitle?: string;
      category_ids_items?: { id: number; slug: string; name: string; items: FaqItem[] }[];
      faq_ids_items?: FaqItem[];
    };
    testimonials?: {
      title?: string;
      subtitle?: string;
      testimonial_ids_items?: TestimonialItem[];
    };
    seo?:        { meta_title?: string; meta_description?: string };
  };
}

export interface FaqItem { id: string; question: string; answer: string; }

export interface TestimonialItem {
  id: string;
  customer_name: string;
  topic: string | null;
  rating: number;
  audio_url: string | null;
  duration_seconds: number | null;
  published_at: string | null;
}
```

---

## ۷) مدیریت در پنل ادمین

| چه چیزی | کجا |
|---|---|
| محتوای صفحه‌ی services (hero/intro/تیترها/CTA/SEO + انتخاب دستی) | `/admin/site/page-content` → «خدمات (/services)» |
| تعریف/ویرایش دسته‌بندی دستگاه‌ها | `/admin/crm/device-categories` |
| نسبت‌دادن دستگاه به دسته | `/admin/crm/devices/{id}/edit` → فیلد «دسته‌بندی والد» |
| تعریف/ویرایش برندها | `/admin/crm/brands` |

**انتخاب دستی vs خودکار:**
- اگر در سکشن categories یا brands هیچ موردی انتخاب نشود → فرانت همه‌ی موارد فعال را نمایش می‌دهد (پیش‌فرض).
- اگر ادمین موردی انتخاب کند → فقط همان‌ها با همان ترتیب کلیک.

---

## ۸) چک‌لیست انطباق فرانت

- [ ] سه fetch موازی: `/v1/pages/services` + `/v1/catalog/device-categories` + `/v1/catalog/brands`
- [ ] هر سکشن `/v1/pages/services` ممکن است نباشد (منتشرنشده) — با `?.` handle شود
- [ ] `category_ids_items` / `brand_ids_items` فقط در صورت انتخاب دستی موجودند → fallback به catalog
- [ ] `intro.html` با `dangerouslySetInnerHTML` (در backend sanitize شده)
- [ ] آیکن‌ها (`icon`/`iconKey`) با `resolveDeviceIcon()` به Lucide map شوند
- [ ] هر دستگاه `href` آماده دارد → مستقیم لینک کنید
