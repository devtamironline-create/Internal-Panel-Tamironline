# بک‌اند — Fallbackِ تصویرِ Hero در صفحاتِ ترکیبی (device × brand)

> این رفتار **سمتِ بک‌اند** است؛ فرانت هیچ تغییری لازم ندارد. فقط فیلدِ
> `sections.hero.image` در صفحهٔ ترکیبی دیگر خالی برنمی‌گردد.

## مسئله

صفحهٔ دستگاه (`/services/{device}`) تصویرِ Hero خود را این‌طور می‌سازد:

```
device.hero_image  →  الگوی سکشنِ «device»
```

اما صفحهٔ ترکیبی (`/services/{device}/{brand}`) وقتی یک الگوی جداگانهٔ
`device_brand` داشت، به Heroِ **همان الگوی ترکیبی** برمی‌گشت (که اغلب خالی
است) و هرگز به Heroِ صفحهٔ دستگاه نمی‌رسید → Heroِ خالی روی صفحهٔ ترکیبی،
حتی وقتی صفحهٔ دستگاه تصویر داشت.

**مثال:** کاربر واردِ صفحهٔ ترکیبیِ «لباسشویی سامسونگ» می‌شود؛ اگر تصویری در
Hero نبود، باید تصویرِ دستگاهِ «لباسشویی» نمایش داده شود.

## رفتارِ جدید (زنجیرهٔ اولویت)

هر slot (`desktop_left`, `desktop_right`, `mobile`) **مستقل** merge می‌شود:

```
۱. device.hero_image            (تصویرِ Heroِ همان دستگاه)
۲. brand.hero_image             (تصویرِ Heroِ همان برند)
۳. الگوی device_brand.hero.image (الگوی اختصاصیِ ترکیبی)
۴. الگوی device.hero.image      ← «تصویرِ صفحهٔ دستگاه» — fallbackِ نهایی
```

پس اگر همهٔ منابعِ بالاتر برای یک slot خالی باشند، همان تصویری که صفحهٔ
دستگاه نشان می‌دهد پر می‌شود. چون هر slot جداگانه است، اگر فقط بخشی خالی
باشد (مثلاً فقط `mobile`)، تنها همان بخش از صفحهٔ دستگاه گرفته می‌شود.

اگر الگوی جداگانهٔ `device_brand` **وجود نداشته باشد**، رفتار مثل قبل است
(چون در آن حالت هم از همان الگوی `device` استفاده می‌شد).

## شکلِ پاسخ (بدون تغییر)

فرانت همان ساختارِ قبلیِ `hero_visual` را می‌گیرد؛ فقط دیگر خالی نیست:

```jsonc
"sections": {
  "hero": {
    "enabled": true,
    "image": {
      "desktop_left":  { "url": "https://.../washing-desktop-left.webp",  "alt": "..." },
      "desktop_right": { "url": "https://.../washing-desktop-right.webp", "alt": "..." },
      "mobile":        { "url": "https://.../washing-mobile.webp",        "alt": "..." }
    }
  }
}
```

## محلِ کد

- `Modules/Site/Http/Controllers/Api/V1/CatalogDeviceBrandController.php`
  - `show()` — الگوی `device` را جداگانه بار می‌کند و `hero.image` آن را به
    عنوانِ fallbackِ نهایی به `buildHero()` می‌دهد.
  - `mergeHeroImage()` — آرگومانِ چهارمِ `$deviceTemplate` به‌عنوانِ آخرین
    حلقهٔ زنجیره اضافه شد.

## کش

پاسخِ کاتالوگ `Cache-Control: max-age=600, s-maxage=600` دارد؛ تا حداکثر
۱۰ دقیقه پس از افزودن تصویرِ دستگاه، Heroِ صفحهٔ ترکیبی در CDN تازه می‌شود.
