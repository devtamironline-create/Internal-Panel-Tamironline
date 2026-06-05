# مستند فرانت — صفحه‌ی بلاگ (`/blog` و `/blog/{slug}`)

> ماژول بلاگ سه نوع طبقه‌بندی برای مقالات دارد: **تاپیک** (عیب‌یابی، نگهداری، ...)، **دستگاه** (لباس‌شویی، پکیج، ...) و **برند** (سامسونگ، LG، ...). یک مقاله می‌تواند به چند مورد از هر سه نوع متصل باشد.

---

## ۱) اندپوینت‌ها

| داده | Endpoint | Auth | Cache |
|---|---|---|---|
| Page chrome (`/blog`) | `GET /v1/pages/blog` | Public | `s-maxage=300` |
| تاپیک‌ها (marquee + فیلتر) | `GET /v1/blog/topics` | Public | `s-maxage=600` |
| لیست مقالات (با فیلتر + pagination) | `GET /v1/blog/articles?topic=&device=&brand=&q=&page=&limit=&sort=` | Public | `s-maxage=300` |
| جزئیات یک مقاله | `GET /v1/blog/articles/{slug}` | Public | `s-maxage=300` |
| دسته‌بندی دستگاه‌ها (در صفحه‌ی بلاگ هم استفاده می‌شود) | `GET /v1/catalog/device-categories` | Public | `s-maxage=600` |
| برندها | `GET /v1/catalog/brands` | Public | `s-maxage=600` |

---

## ۲) `GET /v1/pages/blog`

محتوای editable hero/banner/visibility:

```jsonc
{
  "slug": "blog",
  "sections": {
    "hero": {
      "badge": "مجله آموزشی",
      "title": "مجله آموزشی",
      "highlight": "تعمیر آنلاین",
      "subtitle": "مقالات آموزشی و راهنماهای تخصصی برای نگهداری، عیب‌یابی و تعمیر..."
    },
    "search": {
      "placeholder": "دنبال چه مقاله‌ای می‌گردی؟ جستجو کن...",
      "button_label": "جستجو کن"
    },
    "banner": {
      "image": { "desktop": "/images/banners/blog-banner-desktop.png", "mobile": "/images/banners/blog-banner-mobile.png" },
      "alt": "بنر ثبت سفارش سریع",
      "url": "/#order"
    },
    "sections_visibility": {
      "show_topics_marquee": true,
      "show_search": true,
      "show_banner": true,
      "show_categories": true,
      "show_brands": true
    },
    "categories_section":  { "title": "دسته‌بندی خدمات", "subtitle": "..." },
    "brands_section":      { "title": "برندهای تحت پوشش", "subtitle": "..." },
    "articles_section":    { "title": "جدیدترین مقالات", "page_size": 12 },
    "seo": { "meta_title": "...", "meta_description": "..." }
  }
}
```

---

## ۳) `GET /v1/blog/topics`

```jsonc
{
  "data": [
    {
      "id": 1,
      "slug": "troubleshooting",
      "name": "عیب‌یابی",
      "icon": "stethoscope",          // ↦ Lucide component
      "colors": {
        "bg": "#f5f3ff",
        "fg": "#6d28d9",
        "border": "#ddd6fe"
      },
      "articles_count": 14
    }
  ]
}
```

استفاده در marquee بالای صفحه + شیپ‌های badge داخل کارت‌ها.

---

## ۴) `GET /v1/blog/articles` (لیست + pagination)

```jsonc
{
  "data": [
    {
      "id": 42,
      "slug": "iran-radiator-common-problems-and-fixes",
      "href": "/blog/iran-radiator-common-problems-and-fixes",
      "title": "عیب‌یابی پکیج ایران رادیاتور | راهنمای جامع",
      "excerpt": "با راهنمای کامل عیب‌یابی پکیج...",
      "cover_image": "https://...",   // یا null → از cover_color برای SVG fallback استفاده کن
      "cover_color": "#ffe4e6",
      "read_time_minutes": 12,
      "published_at": "2026-05-15T10:30:00Z",
      "topics": [
        { "id": 2, "slug": "troubleshooting", "name": "عیب‌یابی",
          "colors": { "bg": "#f5f3ff", "fg": "#6d28d9", "border": "#ddd6fe" } }
      ],
      "tags": ["پکیج", "ایران رادیاتور", "عیب‌یابی"]   // device→brand→topic name (در دسترس)
    }
  ],
  "meta": { "total": 47, "page": 1, "limit": 12, "last_page": 4 }
}
```

**فیلترها** (همگی اختیاری، query string):
- `topic={slug}` — فقط مقالات یک تاپیک
- `device={slug}` — فقط مقالات مرتبط با یک دستگاه
- `brand={slug}` — فقط مقالات یک برند
- `q={text}` — جستجو در title و excerpt
- `page=N&limit=M` — pagination
- `sort=newest|oldest|popular` — مرتب‌سازی (پیش‌فرض: newest)

---

## ۵) `GET /v1/blog/articles/{slug}` (جزئیات)

```jsonc
{
  "id": 42,
  "slug": "...",
  "href": "/blog/...",
  "title": "...",
  "excerpt": "...",
  "cover_image": "...",
  "cover_color": "#...",
  "read_time_minutes": 12,
  "published_at": "...",
  "topics": [/* همان شیپ بالا */],
  "tags": [...],

  // اضافی در detail:
  "content": "<h2>...</h2><p>...</p>",   // HTML پاکسازی‌شده با TinyMCE sanitizer
  "meta_title": "...",
  "meta_description": "...",
  "views_count": 1024,
  "devices": [
    { "id": 5, "name": "پکیج", "slug": "boiler", "href": "/devices/boiler",
      "icon": "...", "thumbnail": "...", "tone": "..." }
  ],
  "brands": [
    { "id": 8, "name": "ایران رادیاتور", "slug": "iran-radiator", "logo": "..." }
  ]
}
```

`views_count` در هر GET به `+1` افزایش می‌یابد (بدون تاثیر روی cache header).

---

## ۶) ساختار صفحه‌ی `/blog` در Next.js

```tsx
// app/blog/page.tsx
export const revalidate = 300;

export default async function BlogIndexPage({
  searchParams,
}: { searchParams: { topic?: string; device?: string; brand?: string; q?: string; page?: string } }) {
  const page = Number(searchParams.page ?? 1);
  const filters = new URLSearchParams({
    page: String(page),
    limit: '12',
    ...(searchParams.topic ? { topic: searchParams.topic } : {}),
    ...(searchParams.device ? { device: searchParams.device } : {}),
    ...(searchParams.brand ? { brand: searchParams.brand } : {}),
    ...(searchParams.q ? { q: searchParams.q } : {}),
  });

  const [pageData, topics, articles, cats, brands] = await Promise.all([
    fetchJson<BlogPageResponse>('/v1/pages/blog'),
    fetchJson<{ data: BlogTopic[] }>('/v1/blog/topics'),
    fetchJson<{ data: ArticleListItem[]; meta: PaginationMeta }>(`/v1/blog/articles?${filters}`),
    fetchJson<{ data: DeviceCategory[] }>('/v1/catalog/device-categories'),
    fetchJson<{ data: Brand[] }>('/v1/catalog/brands'),
  ]);

  const v = pageData.sections.sections_visibility ?? {};

  return (
    <>
      <SEO title={pageData.sections.seo?.meta_title} description={pageData.sections.seo?.meta_description} />

      <Hero {...pageData.sections.hero} />

      {v.show_topics_marquee !== false && (
        <TopicsMarquee topics={topics.data} activeSlug={searchParams.topic} />
      )}

      {v.show_search !== false && (
        <SearchBar
          placeholder={pageData.sections.search?.placeholder}
          buttonLabel={pageData.sections.search?.button_label}
          defaultValue={searchParams.q}
        />
      )}

      {v.show_banner !== false && pageData.sections.banner && (
        <Banner {...pageData.sections.banner} />
      )}

      {v.show_categories !== false && (
        <DeviceCategoriesSection
          title={pageData.sections.categories_section?.title}
          subtitle={pageData.sections.categories_section?.subtitle}
          categories={cats.data}
        />
      )}

      {v.show_brands !== false && (
        <BrandsSection
          title={pageData.sections.brands_section?.title}
          subtitle={pageData.sections.brands_section?.subtitle}
          brands={brands.data}
        />
      )}

      <ArticlesGrid
        title={pageData.sections.articles_section?.title ?? 'جدیدترین مقالات'}
        items={articles.data}
        meta={articles.meta}
        currentPage={page}
      />
    </>
  );
}
```

```tsx
// app/blog/[slug]/page.tsx
export const revalidate = 300;

export default async function ArticlePage({ params }: { params: { slug: string } }) {
  const article = await fetchJson<ArticleDetailResponse>(`/v1/blog/articles/${params.slug}`);
  if (article.status === 404) notFound();

  return (
    <article>
      <SEO title={article.meta_title} description={article.meta_description} />
      <ArticleHero
        title={article.title}
        topics={article.topics}
        publishedAt={article.published_at}
        readTime={article.read_time_minutes}
        cover={article.cover_image}
      />
      <div className="prose prose-rtl"
           dangerouslySetInnerHTML={{ __html: article.content ?? '' }} />
      <RelatedDevices devices={article.devices} />
      <RelatedBrands brands={article.brands} />
    </article>
  );
}
```

---

## ۷) Cover image با fallback

```tsx
function ArticleCover({ image, color, title }: { image: string | null; color: string | null; title: string }) {
  if (image) return <img src={image} alt={title} loading="lazy" className="object-cover" />;
  // SVG placeholder با color
  return (
    <svg viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" style={{ background: color ?? '#f3f4f6' }}>
      <text x="50%" y="50%" textAnchor="middle" dominantBaseline="middle"
            fontSize="22" fontWeight="700" opacity="0.85" style={{ direction: 'rtl' }}>
        {title}
      </text>
    </svg>
  );
}
```

---

## ۸) TypeScript types

```ts
export interface BlogTopic {
  id: number;
  slug: string;
  name: string;
  icon: string | null;
  colors: { bg: string | null; fg: string | null; border: string | null };
  articles_count?: number;
}

export interface ArticleListItem {
  id: number;
  slug: string;
  href: string;
  title: string;
  excerpt: string | null;
  cover_image: string | null;
  cover_color: string | null;
  read_time_minutes: number | null;
  published_at: string | null;
  topics: BlogTopic[];
  tags: string[];
}

export interface ArticleDetailResponse extends ArticleListItem {
  content: string | null;
  meta_title: string | null;
  meta_description: string | null;
  views_count: number;
  devices: { id: number; name: string; slug: string; href: string; icon: string | null; thumbnail: string | null; tone: string | null }[];
  brands:  { id: number; name: string; slug: string; logo: string | null }[];
}

export interface BlogPageResponse {
  slug: 'blog';
  sections: {
    hero?:   { badge?: string; title?: string; highlight?: string; subtitle?: string };
    search?: { placeholder?: string; button_label?: string };
    banner?: { image?: { desktop?: string; mobile?: string }; alt?: string; url?: string };
    sections_visibility?: {
      show_topics_marquee?: boolean; show_search?: boolean; show_banner?: boolean;
      show_categories?: boolean; show_brands?: boolean;
    };
    categories_section?: { title?: string; subtitle?: string };
    brands_section?:     { title?: string; subtitle?: string };
    articles_section?:   { title?: string; page_size?: number };
    seo?: { meta_title?: string; meta_description?: string };
  };
}

export interface PaginationMeta { total: number; page: number; limit: number; last_page: number; }
```

---

## ۹) مدیریت در پنل ادمین

| چه چیزی | کجا |
|---|---|
| محتوای صفحه‌ی بلاگ (hero/سرچ/بنر/visibility/تیترها) | `/admin/site/page-content` → «صفحه‌ی بلاگ (/blog)» |
| تاپیک‌های بلاگ (عیب‌یابی، نگهداری، ...) | `/admin/site/blog/topics` |
| مقالات (CRUD کامل با TinyMCE) | `/admin/site/blog/articles` |

**Workflow ادمین:**
1. تاپیک‌ها را با badge color تعریف کن (یک‌بار)
2. مقاله ایجاد کن، تاپیک/دستگاه/برند مرتبط را انتخاب کن
3. منتشر کن
4. در صفحه‌ی بلاگ بنر/سرچ/تیترها و visibility سکشن‌ها را در page-content تنظیم کن

---

## ۱۰) چک‌لیست انطباق فرانت

- [ ] پنج fetch موازی برای `/blog`: pages/blog، blog/topics، blog/articles، catalog/device-categories، catalog/brands
- [ ] هر سکشن `sections_visibility` با `show_*` بررسی شود
- [ ] `cover_image` خالی → SVG placeholder با `cover_color`
- [ ] `published_at` با Jalalian به فارسی نمایش داده شود
- [ ] `topics[0].colors` برای badge کارت
- [ ] `tags` (آرایه‌ی ۳ تایی device→brand→topic) برای زیر کارت
- [ ] pagination با `?page=N` و `last_page` از meta
- [ ] فیلتر روی tab marquee → `?topic={slug}`
- [ ] `content` با `dangerouslySetInnerHTML` (sanitize شده در backend)
