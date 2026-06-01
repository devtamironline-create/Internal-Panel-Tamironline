# مستند فرانت — دسته‌بندی صفحه‌ی انجمن

> نسخه ۱ | تاریخ ۱۴۰۵/۰۳/۱۰
> سکشن قدیمی `category_shortcuts` (repeater دستی) **حذف شد**. جایگزین
> آن یک سکشن خودکار است که از CRM (دستگاه‌ها + برندها) می‌خواند —
> بدون نیاز به مدیریت دستی توسط ادمین.

---

## ۱) منطق

سه گرید جدا روی صفحه‌ی انجمن (`/forum`):

1. **دستگاه‌ها** — همه‌ی Device های فعال + شمارش سوال هر کدام
2. **برندها** — همه‌ی Brand های فعال + شمارش سوال
3. **ترکیبی محبوب** — top N پر از `(device_id, brand_id)` با بیشترین تعداد سوال در انجمن

شمارش‌ها از `site_forum_questions WHERE status='approved' AND published_at IS NOT NULL` می‌آید.
ترکیبی فقط ردیف‌هایی که هم device_id و هم brand_id دارند را شامل می‌شود.

---

## ۲) منابع داده

| داده | Endpoint |
|---|---|
| تنظیمات سکشن (تیتر، toggleها) | `GET /v1/pages/forum` → `sections.categories` |
| لیست واقعی (دستگاه‌ها/برندها/ترکیبی + شمارش) | `GET /v1/forum/categories` |

دلیل جدا بودن: سکشن `categories` در `/v1/pages/forum` فقط تیتر و toggle می‌دهد
(کش ۵ دقیقه)، در حالی که `/v1/forum/categories` داده‌ی زنده‌ی شمارش است
(کش ۵ دقیقه ولی می‌توانید ISR کوتاه‌تر بگذارید چون عدد ست پویاتر است).

---

## ۳) شکل `GET /v1/forum/categories`

```jsonc
{
  "devices": [
    {
      "id": 1,
      "name": "لباس‌شویی",
      "slug": "washing-machine",
      "icon": "washing-machine",
      "tone": "tone-blue",
      "thumbnail": "https://.../media/.../washing.webp",
      "questions_count": 27,
      "href": "/forum?device=washing-machine"
    }
  ],
  "brands": [
    {
      "id": 3,
      "name": "سامسونگ",
      "slug": "samsung",
      "logo": "https://.../media/.../samsung-logo.png",
      "questions_count": 14,
      "href": "/forum?brand=samsung"
    }
  ],
  "combos": [
    {
      "device": { "name": "لباس‌شویی", "slug": "washing-machine", "icon": "washing-machine", "thumbnail": "..." },
      "brand":  { "name": "سامسونگ",  "slug": "samsung", "logo": "..." },
      "label": "لباس‌شویی سامسونگ",
      "questions_count": 9,
      "href": "/forum?device=washing-machine&brand=samsung"
    }
  ]
}
```

پارامتر اختیاری: `?combos_limit=10` (پیش‌فرض ۶، حداکثر ۳۰).

---

## ۴) شکل `sections.categories` در `/v1/pages/forum`

```jsonc
"categories": {
  "show_devices": true,
  "devices_title": "دستگاه‌ها",
  "show_brands": true,
  "brands_title": "برندها",
  "show_combos": true,
  "combos_title": "ترکیبی محبوب",
  "combos_limit": 6
}
```

تا زمانی که ادمین `show_X` را در `/admin/site/page-content/forum` فعال نکند،
گرید مربوطه `false` می‌آید و فرانت باید skip کند.

---

## ۵) رندر نمونه

```tsx
const [pageData, catData] = await Promise.all([
  fetch(`${API}/v1/pages/forum`, { next: { revalidate: 300, tags: ["page:forum"] } }).then((r) => r.json()),
  fetch(`${API}/v1/forum/categories?combos_limit=8`, { next: { revalidate: 300 } }).then((r) => r.json()),
]);

const cfg = pageData.sections.categories;
if (!cfg) return null;

return (
  <section>
    {cfg.show_devices && (
      <CategoryGrid
        title={cfg.devices_title}
        items={catData.devices.map((d) => ({ key: d.slug, label: d.name, count: d.questions_count, icon: d.icon, href: d.href }))}
      />
    )}
    {cfg.show_brands && (
      <CategoryGrid
        title={cfg.brands_title}
        items={catData.brands.map((b) => ({ key: b.slug, label: b.name, count: b.questions_count, logo: b.logo, href: b.href }))}
      />
    )}
    {cfg.show_combos && catData.combos.length > 0 && (
      <CategoryGrid
        title={cfg.combos_title}
        items={catData.combos.map((c) => ({
          key: `${c.device.slug}/${c.brand.slug}`,
          label: c.label,
          count: c.questions_count,
          icon: c.device.icon,
          logo: c.brand.logo,
          href: c.href,
        }))}
      />
    )}
  </section>
);
```

---

## ۶) فیلتر سوالات در `/v1/forum/questions`

از قبل پشتیبانی می‌شود:

```
GET /v1/forum/questions?device=washing-machine&brand=samsung&page=1&limit=20
```

با هردو پارامتر، فقط سوالاتی برمی‌گردند که `device_slug` و `brand_slug` آن‌ها مطابقت دارد.

---

## ۷) Checklist

- [ ] حذف هرگونه مصرف `sections.category_shortcuts` در کد فرانت — این کلید دیگر در پاسخ وجود ندارد.
- [ ] افزودن fetch همزمان `/v1/forum/categories` به صفحه‌ی forum.
- [ ] گارد `cfg.show_X && items.length > 0` قبل از render هر گرید.
- [ ] href ها مستقیماً قابل استفاده با `<Link>`.
- [ ] برای SEO، می‌توانید عدد `questions_count` را به Schema.org تبدیل کنید (`numberOfItems`).

---

## ۸) سوالات متداول

**سوال:** اگر برندی هیچ سوالی نداشته باشد چه می‌شود؟
**پاسخ:** `questions_count: 0` می‌آید. می‌توانید این‌ها را hide کنید یا با badge «جدید» نشان دهید.

**سوال:** ترکیبی محبوب چگونه مرتب‌سازی می‌شود؟
**پاسخ:** بر اساس بیشترین `questions_count` نزولی. هیچ تنظیمی برای featured کردن دستی نیست — اگر چنین چیزی نیاز شد بفرمایید.

**سوال:** آیا cache categories با بنر/page invalidate همخوانی دارد؟
**پاسخ:** هردو `Cache-Control: max-age=300, s-maxage=300` دارند — TTL پایه ۵ دقیقه است. وقتی سوال جدیدی approve می‌شود، شمارش‌ها در ≤۵ دقیقه آپدیت می‌شوند.

---

پایان.
