# نامهٔ فرانت — اطلاعات پایهٔ سایت از یک منبع واحد

سلام تیم فرانت 👋

تمام **اطلاعات پایهٔ سایت** (نام، لوگو، آدرس، تلفن‌ها، ایمیل، ساعت کاری، شبکه‌های
اجتماعی، CTA سفارش) از **یک منبع واحد** خوانده می‌شود و مدیر می‌تواند آن‌ها را
بدون دخالت برنامه‌نویس از پنل ویرایش کند. هیچ‌کدام از این مقادیر را در فرانت
hard-code نکنید.

> پنل: **سایت → تنظیمات** (همان‌جا آدرس/تلفن‌ها/ایمیل/شبکه‌ها ویرایش می‌شوند).
> فرانت فقط مصرف‌کننده است.

---

## Endpoint

```
GET {BACKEND_URL}/v1/settings/global
```
- عمومی (بدون توکن)، با `Cache-Control` ۱۰ دقیقه‌ای → از `fetch(..., { next: { revalidate: 600 } })` استفاده کنید.

### نمونهٔ پاسخ
```jsonc
{
  "site_name": "تعمیرآنلاین",
  "site_tagline": "در تعمیرآنلاین رضایت مشتری یک شعار نیست",
  "site_logo_url": "https://.../logo.png",

  // 🆕 چند تلفن/تماس — لیستِ برچسب‌دار (منبعِ اصلی برای نمایش)
  "phones": [
    { "label": "تماس و پشتیبانی", "number": "021-45396", "href": "tel:02145396" },
    { "label": "فروش",            "number": "021-00000", "href": "tel:02100000" }
  ],

  // سازگاری با نسخهٔ قبل — همان تلفنِ اولِ phones
  "phone": "021-45396",
  "phone_href": "tel:02145396",
  "support_phone": null,

  "email": "support@tamironline.com",
  "address": "تهران، خیابان مطهری، …",
  "working_hours": "شنبه تا پنج‌شنبه ۹ تا ۲۲ — روزهای تعطیل ۱۰ تا ۱۸",

  "order_url": "/order",
  "order_label": "ثبت سفارش",

  "social": {
    "instagram": "https://instagram.com/tamironlinecom",
    "telegram": null, "whatsapp": null,
    "youtube": "https://youtube.com/@tamironlinecom",
    "linkedin": null, "aparat": null
  }
}
```

---

## نکتهٔ مهم: تلفن‌ها (🆕)

- **برای نمایشِ شماره‌ها از آرایهٔ `phones` استفاده کنید** و روی آن `map` بزنید
  (هر آیتم `label` + `number` + `href` آماده دارد). ممکن است **یک یا چند** شماره
  باشد؛ UI را برای لیست بسازید (نه یک شمارهٔ ثابت).
- فیلدهای `phone` / `phone_href` فقط برای **سازگاری** باقی مانده‌اند (برابرِ اولین
  آیتمِ `phones`). برای صفحات جدید از `phones` استفاده کنید.

```tsx
// lib/site-settings.ts
export async function getSiteSettings() {
  const r = await fetch(`${process.env.BACKEND_URL}/v1/settings/global`, { next: { revalidate: 600 } });
  return r.ok ? r.json() : null;
}
```
```tsx
// نمونهٔ فوتر/هدر
{settings.phones?.map((p) => (
  <a key={p.href} href={p.href} className="...">
    <span>{p.label}</span><b dir="ltr">{p.number}</b>
  </a>
))}
```

---

## کجا استفاده شود
- **هدر / فوتر:** نام سایت، لوگو، تلفن‌ها، شبکه‌های اجتماعی، CTA سفارش (`order_url`/`order_label`).
- **صفحهٔ تماس با ما:** آدرس، تلفن‌ها، ایمیل، ساعت کاری، نقشه.
- **Schema/SEO:** این مقادیر با `‎/v1/seo/settings` (Organization/LocalBusiness) هم‌خوان‌اند؛ تلفن/آدرسِ نمایشی را از همین `‎/v1/settings/global` بگیرید.

## چک‌لیست فرانت
- [ ] `getSiteSettings()` با `revalidate: 600`.
- [ ] رندرِ تلفن‌ها از آرایهٔ `phones` (پشتیبانی از چند شماره).
- [ ] آدرس / ایمیل / ساعت کاری / شبکه‌های اجتماعی از همین پاسخ (بدون hard-code).
- [ ] CTA سفارش از `order_url`/`order_label`.

تغییرِ هر مقدار در پنل، ظرفِ ~۱۰ دقیقه (یا با revalidate) روی سایت اعمال می‌شود.
سؤالی بود بپرسید 🙏
