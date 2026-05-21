# داکیومنت اتصال صفحه‌ی «درباره ما» — Next.js

این سند راهنمای **عملی** اتصال صفحه‌ی `/about` فرانت Next.js به API
لاراول است. تمام سکشن‌های A1 تا A8، با کد آماده.

> برای داکیومنت کلی API به `frontend-integration.md` مراجعه کنید.

---

## ۰) خلاصه — endpointهای لازم

صفحه‌ی About به سه endpoint نیاز دارد:

| # | Endpoint | محتوا |
|---|---|---|
| 1 | `GET /v1/pages/about` | همه‌ی سکشن‌های پویا (hero, values, steps, timeline, faq, promo) |
| 2 | `GET /v1/site/about-stats` | کارت‌های آمار (A2) |
| 3 | `GET /v1/testimonials` | نظرات مشتریان (A6) — مشترک با Home |

به‌علاوه، **`GET /v1/pages/layout`** هم برای هدر/فوتر سایت — که در root
layout فراخوانی می‌شود و در همه‌ی صفحات shared است (جزئیات: `frontend-integration.md` §۱۲).

---

## ۱) ترتیب سکشن‌ها

```
┌─────────────────────────────────────┐
│ A1. About Hero (با ویدیو آپارات)    │ ← sections.hero
├─────────────────────────────────────┤
│ A2. Stats (آمار: ۸+، ۵۰،۰۰۰+، ...) │ ← /v1/site/about-stats
├─────────────────────────────────────┤
│ A3. Values (ارزش‌های ما)            │ ← sections.values
├─────────────────────────────────────┤
│ A4. Steps Image (تصویر مراحل)       │ ← sections.steps
├─────────────────────────────────────┤
│ A5. Timeline (سال‌شمار)              │ ← sections.timeline
├─────────────────────────────────────┤
│ A6. Testimonials (نظرات مشتریان)    │ ← /v1/testimonials
├─────────────────────────────────────┤
│ A7. About FAQ (با دسته‌بندی)         │ ← sections.faq
├─────────────────────────────────────┤
│ A8. Promo Banner                    │ ← sections.promo
└─────────────────────────────────────┘
```

---

## ۲) لایه‌ی دسترسی به داده — `lib/api/about.ts`

```typescript
// frontend/src/lib/api/about.ts

export type ResponsiveImage = {
  desktop: string | null;
  mobile: string | null;
};

export type AboutHero = {
  title: string;
  subtitle: string | null;
  aparat_id: string | null;
  poster: ResponsiveImage;
  description: string | null;
};

export type AboutValue = {
  icon: string | null;
  title: string;
  description: string;
};

export type AboutSteps = {
  title: string | null;
  image: ResponsiveImage;
  alt: string | null;
};

export type AboutTimelineItem = {
  year: string;
  title: string;
  description: string | null;
};

export type AboutFaqCategory = {
  id: number;
  slug: string;
  label: string;
  items: { id: string; question: string; answer: string }[];
};

export type AboutFaq = {
  title: string | null;
  subtitle: string | null;
  category_ids: number[];
  category_ids_items: AboutFaqCategory[];
  faq_ids: string[];
  faq_ids_items: { id: string; question: string; answer: string }[];
};

export type AboutPromo = {
  title: string | null;
  subtitle: string | null;
  image: ResponsiveImage;
  link_url: string | null;
  link_label: string | null;
};

export type AboutSections = {
  hero?: AboutHero;
  values?: { title: string | null; subtitle: string | null; items: AboutValue[] };
  steps?: AboutSteps;
  timeline?: { title: string | null; items: AboutTimelineItem[] };
  faq?: AboutFaq;
  promo?: AboutPromo;
};

export type AboutStat = {
  key: string;
  value: string;
  label: string;
  tone: 'blue' | 'green' | 'amber' | 'rose' | 'violet';
};

export type Testimonial = {
  id: string;
  customer_name: string;
  topic: string;
  rating: number;
  audio_url: string | null;
  duration_seconds: number | null;
  published_at: string;
};

/** سه fetch به‌صورت موازی برای سرعت بیشتر. */
export async function getAboutPageData() {
  const base = process.env.API_BASE_URL!;
  const [pageRes, statsRes, testimonialsRes] = await Promise.all([
    fetch(`${base}/v1/pages/about`, { next: { revalidate: 300, tags: ['about', 'pages'] } }),
    fetch(`${base}/v1/site/about-stats`, { next: { revalidate: 600, tags: ['about-stats'] } }),
    fetch(`${base}/v1/testimonials?limit=12`, { next: { revalidate: 300, tags: ['testimonials'] } }),
  ]);

  const sections: AboutSections = pageRes.ok ? (await pageRes.json()).sections ?? {} : {};
  const stats: AboutStat[] = statsRes.ok ? (await statsRes.json()).data ?? [] : [];
  const testimonials: Testimonial[] = testimonialsRes.ok ? (await testimonialsRes.json()).data ?? [] : [];

  return { sections, stats, testimonials };
}
```

---

## ۳) صفحه — `app/(site)/about/page.tsx`

```typescript
import { getAboutPageData } from '@/lib/api/about';
import { AboutHero } from '@/components/about/AboutHero';
import { AboutStats } from '@/components/about/AboutStats';
import { AboutValues } from '@/components/about/AboutValues';
import { AboutSteps } from '@/components/about/AboutSteps';
import { AboutTimeline } from '@/components/about/AboutTimeline';
import { Testimonials } from '@/components/home/Testimonials';
import { AboutFaq } from '@/components/about/AboutFaq';
import { PromoBanner } from '@/components/shared/PromoBanner';

export const revalidate = 300;

export default async function AboutPage() {
  const { sections, stats, testimonials } = await getAboutPageData();

  return (
    <main>
      {sections.hero && <AboutHero data={sections.hero} />}
      {stats.length > 0 && <AboutStats items={stats} />}
      {sections.values && <AboutValues data={sections.values} />}
      {sections.steps && <AboutSteps data={sections.steps} />}
      {sections.timeline && <AboutTimeline data={sections.timeline} />}
      {testimonials.length > 0 && <Testimonials items={testimonials} />}
      {sections.faq && <AboutFaq data={sections.faq} />}
      {sections.promo && <PromoBanner data={sections.promo} />}
    </main>
  );
}
```

---

## ۴) کامپوننت‌های سکشن

### A1. About Hero — `components/about/AboutHero.tsx`

```tsx
import type { AboutHero as Data } from '@/lib/api/about';

export function AboutHero({ data }: { data: Data }) {
  return (
    <section className="section section--tinted">
      <div className="container-x grid lg:grid-cols-2 gap-8 items-center">
        <div>
          <h1 className="heading">{data.title}</h1>
          {data.subtitle && <p className="lede mt-3">{data.subtitle}</p>}
          {data.description && (
            <div className="mt-6 text-[14px] leading-loose text-[color:var(--text-soft)]">
              {data.description}
            </div>
          )}
        </div>

        {data.aparat_id && (
          <div className="rounded-2xl overflow-hidden shadow-lg aspect-video">
            <iframe
              src={`https://www.aparat.com/video/video/embed/videohash/${data.aparat_id}/vt/frame`}
              className="w-full h-full"
              allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
              referrerPolicy="strict-origin"
              loading="lazy"
            />
          </div>
        )}
      </div>
    </section>
  );
}
```

**فیلدها:**

| فیلد | استفاده |
|---|---|
| `title` | تیتر اصلی (h1) — برای SEO هم استفاده می‌شود |
| `subtitle` | زیرتیتر |
| `aparat_id` | شناسه ویدیو آپارات — اگر null باشد، iframe رندر نمی‌شود |
| `poster.desktop`/`mobile` | تصویر poster (اگر بخواهید قبل از click اول، تصویر نمایش دهید) |
| `description` | توضیح بلندتر |

---

### A2. About Stats — `components/about/AboutStats.tsx`

```tsx
import type { AboutStat } from '@/lib/api/about';

const TONE_CLASSES: Record<AboutStat['tone'], string> = {
  blue:   'bg-blue-50 text-blue-700 border-blue-200',
  green:  'bg-emerald-50 text-emerald-700 border-emerald-200',
  amber:  'bg-amber-50 text-amber-700 border-amber-200',
  rose:   'bg-rose-50 text-rose-700 border-rose-200',
  violet: 'bg-violet-50 text-violet-700 border-violet-200',
};

export function AboutStats({ items }: { items: AboutStat[] }) {
  if (!items.length) return null;
  return (
    <section className="section">
      <div className="container-x">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {items.map((s) => (
            <div
              key={s.key}
              className={`rounded-2xl border p-5 text-center ${TONE_CLASSES[s.tone] ?? TONE_CLASSES.blue}`}
            >
              <div className="text-3xl font-extrabold">{s.value}</div>
              <div className="text-xs mt-1">{s.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
```

**نکات:**
- `value` همان رشته‌ی نمایشی است (مثلاً `"۸+"` یا `"۵۰,۰۰۰+"`). فرانت **فرمت‌بندی نمی‌کند** — همان‌گونه که از سرور آمده نمایش بدهید.
- `tone` کلید رنگ است؛ مپ به کلاس CSS سمت فرانت.
- `key` برای React `key=` استفاده شود.

---

### A3. Values — `components/about/AboutValues.tsx`

```tsx
import type { AboutValue } from '@/lib/api/about';
import { iconMap } from '@/lib/icons';

type Data = { title: string | null; subtitle: string | null; items: AboutValue[] };

export function AboutValues({ data }: { data: Data }) {
  if (!data.items?.length) return null;
  return (
    <section className="section section--tinted">
      <div className="container-x">
        <div className="section-head text-center">
          {data.title && <h2 className="heading">{data.title}</h2>}
          {data.subtitle && <p className="lede mx-auto">{data.subtitle}</p>}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mt-8">
          {data.items.map((v, i) => {
            const Icon = v.icon ? iconMap[v.icon] : null;
            return (
              <div
                key={i}
                className="rounded-2xl border bg-white p-6"
                style={{ borderColor: 'var(--border)' }}
              >
                {Icon && (
                  <span className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 text-blue-600 mb-3">
                    <Icon className="h-6 w-6" strokeWidth={1.6} />
                  </span>
                )}
                <h3 className="font-bold text-[15px]">{v.title}</h3>
                <p className="text-[13px] text-[color:var(--text-soft)] leading-[2] mt-2">
                  {v.description}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
```

**نکته:** `icon` کلید Lucide به‌صورت kebab-case است (مثل `headphones`, `shield`, `award`). در `lib/icons.ts` آن را به component map کنید (مشابه `iconMap` در §۹ سند اصلی).

---

### A4. Steps Image — `components/about/AboutSteps.tsx`

```tsx
import Image from 'next/image';
import type { AboutSteps as Data } from '@/lib/api/about';

export function AboutSteps({ data }: { data: Data }) {
  if (!data.image?.desktop && !data.image?.mobile) return null;

  return (
    <section className="section">
      <div className="container-x">
        {data.title && <h2 className="heading text-center mb-6">{data.title}</h2>}

        {/* تصویر responsive: موبایل/دسکتاپ */}
        <picture className="block">
          {data.image.mobile && (
            <source media="(max-width: 768px)" srcSet={data.image.mobile} />
          )}
          {data.image.desktop && (
            <img
              src={data.image.desktop}
              alt={data.alt ?? ''}
              className="w-full h-auto rounded-2xl"
              loading="lazy"
            />
          )}
        </picture>
      </div>
    </section>
  );
}
```

**نکته:** اگر `mobile` خالی باشد، فقط `desktop` در همه‌ی viewportها استفاده می‌شود (نکته §۴.۲ از سند اصلی).

---

### A5. Timeline — `components/about/AboutTimeline.tsx`

```tsx
import type { AboutTimelineItem } from '@/lib/api/about';

type Data = { title: string | null; items: AboutTimelineItem[] };

export function AboutTimeline({ data }: { data: Data }) {
  if (!data.items?.length) return null;
  return (
    <section className="section section--tinted">
      <div className="container-x">
        {data.title && <h2 className="heading text-center mb-8">{data.title}</h2>}

        <div className="relative max-w-3xl mx-auto pr-8">
          {/* خط عمودی */}
          <div className="absolute right-3 top-0 bottom-0 w-px bg-blue-200" />

          {data.items.map((event, i) => (
            <div key={i} className="relative pb-8 last:pb-0">
              {/* نقطه‌ی روی خط */}
              <span className="absolute right-0 top-1.5 w-6 h-6 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center">
                {event.year}
              </span>
              <div className="mr-10">
                <h3 className="font-bold text-[15px]">{event.title}</h3>
                {event.description && (
                  <p className="text-[13px] text-[color:var(--text-soft)] leading-[2] mt-1">
                    {event.description}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
```

**نکته:** اگر `description` خالی باشد، فقط `year + title` نمایش داده می‌شود.

---

### A6. Testimonials

از همان کامپوننتی که در صفحه‌ی Home استفاده می‌کنید (`components/home/Testimonials.tsx`). API یکسان است:

```typescript
testimonials.map((t) => ({
  id: t.id,
  name: t.customer_name,
  topic: t.topic,
  rating: t.rating,
  audio: t.audio_url,
  duration: t.duration_seconds,
}))
```

اگر در About نمی‌خواهید audio player داشته باشید، فقط ستاره + نام + موضوع را نشان دهید.

---

### A7. About FAQ (با دسته‌بندی)

سکشن `faq` در About از **دسته‌بندی‌ها** پشتیبانی می‌کند. ادمین می‌تواند چند دسته انتخاب کند که در فرانت به‌صورت **تب** نمایش داده شوند.

```tsx
'use client';

import { useState } from 'react';
import { Plus, Minus } from 'lucide-react';
import type { AboutFaq as Data } from '@/lib/api/about';

export function AboutFaq({ data }: { data: Data }) {
  // اولویت با category_ids_items (تب)؛ در غیر این صورت faq_ids_items (لیست تخت)
  const hasCategories = data.category_ids_items?.length > 0;
  const hasFlat = data.faq_ids_items?.length > 0;
  if (!hasCategories && !hasFlat) return null;

  const tabs = hasCategories
    ? data.category_ids_items
    : [{ id: 0, slug: 'all', label: 'همه', items: data.faq_ids_items }];

  const [activeId, setActiveId] = useState<number>(tabs[0]?.id ?? 0);
  const [openIdx, setOpenIdx] = useState<number | null>(0);
  const active = tabs.find((t) => t.id === activeId) ?? tabs[0];

  return (
    <section id="faq" className="section">
      <div className="container-x">
        <div className="section-head text-center">
          {data.title && <h2 className="heading">{data.title}</h2>}
          {data.subtitle && <p className="lede mx-auto">{data.subtitle}</p>}
        </div>

        {/* تب‌ها — فقط اگر بیش از یک دسته باشد */}
        {tabs.length > 1 && (
          <div className="flex gap-2 overflow-x-auto pb-2 mb-6 no-scrollbar">
            {tabs.map((t) => (
              <button
                key={t.id}
                onClick={() => { setActiveId(t.id); setOpenIdx(0); }}
                className="shrink-0 rounded-md px-4 py-2 text-sm font-medium transition"
                style={t.id === activeId
                  ? { background: '#003d7a', color: '#fff' }
                  : { background: '#fff', color: 'var(--text-soft)', border: '1px solid var(--border-2)' }}
              >
                {t.label}
              </button>
            ))}
          </div>
        )}

        <div className="space-y-3 max-w-3xl mx-auto">
          {active.items.map((item, i) => {
            const isOpen = openIdx === i;
            return (
              <div key={item.id} className="rounded-2xl border bg-white overflow-hidden">
                <button
                  type="button"
                  onClick={() => setOpenIdx(isOpen ? null : i)}
                  className="flex w-full items-center justify-between gap-4 p-5 text-right"
                  aria-expanded={isOpen}
                >
                  <span className="text-[14.5px] font-bold">{item.question}</span>
                  <span className="flex h-8 w-8 items-center justify-center rounded-full"
                        style={{ background: isOpen ? '#003d7a' : 'var(--blue-xlight)', color: isOpen ? '#fff' : 'var(--blue)' }}>
                    {isOpen ? <Minus className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                  </span>
                </button>
                <div className="overflow-hidden transition-[max-height] duration-300"
                     style={{ maxHeight: isOpen ? 600 : 0 }}>
                  <div className="px-5 pb-5 text-[13.5px] leading-[2] text-[color:var(--text-soft)] whitespace-pre-line">
                    {item.answer}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
```

**رفتار:**

| سناریو | نمایش |
|---|---|
| ادمین فقط `category_ids` انتخاب کرده | تب‌ها (یکی به ازای هر دسته) |
| ادمین فقط `faq_ids` انتخاب کرده | یک تب «همه» با سوالات تخت |
| هر دو | تب‌ها (`category_ids` اولویت دارد) |
| هیچ‌کدام | کامپوننت رندر نمی‌شود |

---

### A8. Promo Banner — `components/shared/PromoBanner.tsx`

```tsx
import Link from 'next/link';
import type { AboutPromo as Data } from '@/lib/api/about';

export function PromoBanner({ data }: { data: Data }) {
  return (
    <section className="section">
      <div className="container-x">
        <div className="relative rounded-3xl overflow-hidden bg-gradient-to-l from-blue-600 to-blue-800 p-8 md:p-12">
          {data.image.desktop && (
            <picture>
              {data.image.mobile && (
                <source media="(max-width: 768px)" srcSet={data.image.mobile} />
              )}
              <img src={data.image.desktop} alt="" className="absolute inset-0 w-full h-full object-cover opacity-30" />
            </picture>
          )}

          <div className="relative z-10 text-white">
            {data.title && <h2 className="text-2xl md:text-3xl font-extrabold mb-3">{data.title}</h2>}
            {data.subtitle && <p className="text-sm md:text-base opacity-90 mb-6">{data.subtitle}</p>}
            {data.link_url && data.link_label && (
              <Link
                href={data.link_url}
                className="inline-block px-6 py-3 bg-white text-blue-700 rounded-lg font-bold"
              >
                {data.link_label}
              </Link>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
```

---

## ۵) SEO Metadata

از فیلدهای hero برای متادیتای صفحه استفاده کنید:

```typescript
// app/(site)/about/page.tsx
import type { Metadata } from 'next';
import { getAboutPageData } from '@/lib/api/about';

export async function generateMetadata(): Promise<Metadata> {
  const { sections } = await getAboutPageData();
  const hero = sections.hero;
  return {
    title: hero?.title ?? 'درباره تعمیرآنلاین',
    description: hero?.subtitle ?? hero?.description ?? 'تعمیرآنلاین — خدمات تعمیر لوازم خانگی در محل با گارانتی ۶ ماهه.',
    openGraph: {
      title: hero?.title ?? 'درباره تعمیرآنلاین',
      description: hero?.subtitle ?? '',
      images: hero?.poster?.desktop ? [hero.poster.desktop] : [],
    },
  };
}
```

---

## ۶) استراتژی کش

تمام endpointهای About **عمومی و کش‌پذیر** هستند:

| Endpoint | Cache-Control سرور | revalidate پیشنهادی فرانت |
|---|---|---|
| `/v1/pages/about` | `s-maxage=300` | 300 |
| `/v1/site/about-stats` | `s-maxage=600` | 600 |
| `/v1/testimonials` | `s-maxage=300` | 300 |

برای **revalidate فوری** بعد از تغییر در پنل ادمین (هنوز پیاده نشده):

```typescript
// آینده: webhook از Laravel → revalidateTag
await fetch('/api/revalidate', { method: 'POST', body: JSON.stringify({ tag: 'about' }) });
```

---

## ۷) Error handling — Fallback پیشنهادی

اگر سرور down باشد یا سکشنی منتشر نشده باشد، صفحه باید گرافیکی crash نکند:

```tsx
export default async function AboutPage() {
  const data = await getAboutPageData().catch(() => ({
    sections: {},
    stats: [],
    testimonials: [],
  }));

  // اگر هیچ داده‌ای نیست، یک fallback ثابت نشان بده
  if (!data.sections.hero && data.stats.length === 0) {
    return <AboutFallback />;
  }
  // ...
}
```

**سکشن‌های ضروری vs اختیاری:**
- **ضروری:** `hero` — اگر null، یک hero placeholder ثابت نمایش بده
- **اختیاری:** بقیه — اگر null، سکشن را اصلاً رندر نکن

---

## ۸) چک‌لیست تست بعد از deploy

- [ ] `/about` بدون خطا لود می‌شود
- [ ] ویدیو آپارات در hero نمایش داده می‌شود (در صورت تنظیم `aparat_id`)
- [ ] کارت‌های آمار با رنگ‌های صحیح ظاهر می‌شوند
- [ ] ارزش‌ها با آیکن Lucide درست رندر می‌شوند
- [ ] timeline سال‌ها را به ترتیب نمایش می‌دهد
- [ ] testimonials کارت‌های صدا را نشان می‌دهد
- [ ] FAQ — هم با دسته‌بندی (تب) و هم بدون دسته (لیست تخت) کار می‌کند
- [ ] Promo banner لینک قابل کلیک دارد
- [ ] صفحه در موبایل responsive است
- [ ] meta tag‌های SEO درست تنظیم شده‌اند
- [ ] Lighthouse score بالای ۹۰

---

## ۹) راهنمای ادمین برای پر کردن About

ادمین به `/admin/site/page-content/about` می‌رود و این کارت‌ها را می‌بیند:

| کارت | چه چیزی پر کند |
|---|---|
| Hero | `title`، `subtitle`، `aparat_id` (شناسه ویدیو)، `description` |
| Values | لیست repeater — برای هر آیتم: آیکن، تیتر، توضیح |
| Steps | `title` + آپلود تصویر دسکتاپ + موبایل |
| Timeline | لیست repeater — برای هر آیتم: سال، تیتر، توضیح |
| FAQ | انتخاب چند دسته از `/admin/site/taxonomies/faq` |
| Promo | `title`، `subtitle`، تصویر، `link_url`، `link_label` |

برای آمار: `/admin/site/about-stats` — افزودن/ویرایش هر آمار جداگانه (key، value، label، tone).

---

## ۱۰) سوال‌های احتمالی

**Q: aparat_id را چطور پیدا کنم؟**
A: URL ویدیو در آپارات معمولاً به این شکل است: `https://www.aparat.com/v/AbCdE`. مقدار `AbCdE` همان `aparat_id` است.

**Q: چه تعداد testimonial نمایش می‌دهد؟**
A: پارامتر `?limit=12` در `lib/api/about.ts` ست شده. می‌توانید تغییر دهید. حداکثر ۵۰.

**Q: اگر هیچ FAQ نتواند ادمین select کند چه می‌شود؟**
A: سکشن `faq` در پاسخ نمی‌آید (چون payload خالی است یا منتشر نشده). فرانت با شرط `{sections.faq && ...}` آن را نمایش نمی‌دهد.

**Q: تصاویر کجا host می‌شوند؟**
A: ادمین در پنل آپلود می‌کند، فایل در `storage/app/public/site/...` ذخیره می‌شود، و API URL کامل می‌فرستد (مثل `https://panel.tamironline.com/storage/site/...`). فرانت فقط آن را تو src می‌گذارد.

**Q: تب‌های FAQ بر اساس چه ترتیبی نشان داده می‌شوند؟**
A: ترتیب کلیک ادمین در فرم. ادمین در سکشن FAQ یک checkbox list دارد و ترتیب کلیک = ترتیب نمایش.
