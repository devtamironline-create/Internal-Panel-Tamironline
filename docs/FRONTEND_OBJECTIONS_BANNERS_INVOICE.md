# Frontend Notes — Objections per Device + Banners + Invoice format

> تاریخ: 2026-06-08
> مخاطب: تیم فرانت
> سه موضوع جداگانه که در یک سند جمع شده تا مرور سریع‌تر باشد.

---

## ۱) ایرادات بر اساس دستگاه

### مشکل قبلی

فرانت همه‌ی ایرادات (`crm_objections`) را یکجا می‌گرفت → کاربر ایراد‌هایی می‌دید که به دستگاه انتخابی او ربط نداشتند (مثلاً «دیگ نمی‌جوشد» در صفحه‌ی ماشین لباس‌شویی).

### راه‌حل (از قبل آماده، فقط استفاده شود)

#### `GET /v1/customer/services/objections?device_id={id}`

```ts
// Request
GET /v1/customer/services/objections?device_id=4
Accept: application/json
// auth لازم نیست — public + cache 30 دقیقه‌ای روی CDN
```

```jsonc
// Response 200
{
  "success": true,
  "data": [
    { "id": 12, "slug": "no-spin",     "name": "نمی‌چرخد",            "description": null, "icon": "rotate-ccw" },
    { "id": 14, "slug": "water-leak",  "name": "نشتی آب دارد",         "description": null, "icon": "droplet" },
    { "id": 17, "slug": "noise",       "name": "صدای غیرعادی دارد",    "description": null, "icon": "volume-2" }
  ],
  "meta": { "device_id": 4, "total": 3 }
}
```

**نکات:**
- اگر `device_id` نفرستید، **همه‌ی** ایراد‌های فعال برمی‌گردد (همان رفتار قبلی).
- اگر `device_id` بفرستید، فقط ایراد‌های associated با آن دستگاه برمی‌گردد (از pivot `crm_device_objections`).
- اگر برای یک دستگاه ایرادی تعریف نشده باشد، آرایه‌ی خالی برمی‌گردد — UX پیشنهادی: «ایراد دستگاه را در توضیحات بنویسید».
- ایراد‌ها از ادمین `/admin/crm/objections` قابل‌مدیریت‌اند و per-device به دستگاه متصل می‌شوند.

### الگوی صحیح فرانت

```tsx
// در صفحه ثبت سفارش
useEffect(() => {
  if (!selectedDeviceId) {
    setObjections([]); // یا dropdown را disable کنید
    return;
  }
  fetchObjections(selectedDeviceId).then(setObjections);
}, [selectedDeviceId]);

async function fetchObjections(deviceId: number) {
  const res = await fetch(`${API_BASE}/v1/customer/services/objections?device_id=${deviceId}`);
  return (await res.json()).data;
}
```

> **مهم:** هرگاه کاربر دستگاه را عوض کرد، لیست ایراد را رفرش کنید و انتخاب‌های قبلی را reset کنید (چون id‌های قبلی به دستگاه جدید مرتبط نیستند).

---

## ۲) راهنمای استفاده از بنرها

### Endpoint

#### `GET /v1/customer/services/banners?placement={zone_slug}`

```ts
// Request
GET /v1/customer/services/banners?placement=home_hero
Accept: application/json
// auth لازم نیست — public + cache 5 دقیقه‌ای
```

```jsonc
// Response 200 — با placement
{
  "success": true,
  "data": [
    {
      "id": 7,
      "title": "تخفیف ویژه پاییز",
      "image_url": "https://cdn.tamironline.com/media/banner-7.webp",
      "link_url": "https://tamironline.com/promo/autumn",
      "placement": "home_hero",
      "active": true,
      "order": 1
    }
  ]
}
```

```jsonc
// Response 200 — بدون placement → گروه‌بندی بر اساس zone slug
{
  "success": true,
  "data": {
    "home_hero":      [ { ... } ],
    "home_secondary": [ { ... } ],
    "blog_hero":      [],
    "blog_sidebar":   [],
    "services_promo": [ { ... } ]
  }
}
```

### Zone Slugها (placement)

این لیست از DB می‌آید و قابل تغییر است، ولی Zoneهای پایه:

| Slug | محل نمایش | اندازه پیشنهادی |
|------|------------|------------------|
| `home_hero` | صفحه اصلی — بنر بزرگ بالا | 1920×700 |
| `home_secondary` | صفحه اصلی — بنر دوم | 1200×300 |
| `home_promo` | صفحه اصلی — بنر تبلیغی | 1200×300 |
| `services_promo` | صفحه‌ی خدمات — بنر تبلیغی | 1200×300 |
| `blog_hero` | بلاگ — Hero | 1200×300 |
| `blog_sidebar` | بلاگ — Sidebar | 300×250 |
| `forum_top` | انجمن — بالای صفحه | 1200×200 |
| `forum_sidebar` | انجمن — Sidebar | 300×250 |
| `global_top` | سراسری — بالای همه صفحات | 1200×60 |
| `brand_promo` | صفحه برند | — |
| `device_promo` | صفحه دستگاه | — |
| `brand_device_promo` | صفحه ترکیبی برند+دستگاه | — |
| `about_promo` | درباره ما | — |

> Slugها در `/admin/site/banner-zones` لیست کامل دارند. اگر zone جدیدی اضافه شد، فرانت بدون deploy از `GET /v1/customer/services/banners` (بدون placement) آن را می‌بیند.

### الگوی استفاده

#### الگوی ۱: گرفتن یک placement خاص (متداول)

```tsx
function HomeHeroBanner() {
  const { data } = useSWR(
    `${API_BASE}/v1/customer/services/banners?placement=home_hero`,
    fetcher,
    { revalidateOnFocus: false, refreshInterval: 5 * 60 * 1000 }
  );

  if (!data?.data?.length) return null; // اگر بنری نیست، چیزی نمایش نده

  return (
    <div className="banner-carousel">
      {data.data.map((b: Banner) => (
        <a key={b.id} href={b.link_url ?? '#'} target="_blank" rel="noopener noreferrer">
          <img src={b.image_url} alt={b.title} loading="lazy" />
        </a>
      ))}
    </div>
  );
}
```

#### الگوی ۲: pre-fetch همه و cache (برای splash)

```tsx
// در bootstrap
const allBanners = await fetch(`${API_BASE}/v1/customer/services/banners`).then(r => r.json());
// allBanners.data = { home_hero: [...], blog_hero: [...], ... }
localStorage.setItem('banners_cache', JSON.stringify({
  data: allBanners.data,
  expires: Date.now() + 5 * 60 * 1000,
}));

// در هر صفحه
const cache = JSON.parse(localStorage.getItem('banners_cache') ?? 'null');
const heroBanners = cache?.expires > Date.now() ? cache.data.home_hero : [];
```

### نکات مهم

- **`image_url` می‌تواند `null` باشد** — اگر هم `media` و هم `image_url` خالی باشد. آن آیتم را skip کنید.
- **`link_url` اختیاری است** — اگر `null` بود، بنر را غیرقابل‌کلیک نمایش دهید.
- **فقط `live` banners برمی‌گردند** — یعنی `is_published = true` + در محدوده‌ی `starts_at..ends_at` (اگر تعریف شده).
- **Cache 5 دقیقه** — اگر ادمین بنر را تغییر داد، حداکثر ۵ دقیقه طول می‌کشد به فرانت برسد.
- **بدون placement** برای splash بهتر است (یک request به‌جای N تا).
- **پیشنهاد عملکرد**: استفاده از `loading="lazy"` و `srcset` برای پاسخگویی.

### TypeScript Types

```ts
type Banner = {
  id: number;
  title: string;
  image_url: string | null;
  link_url: string | null;
  placement: string;        // zone slug
  active: boolean;
  order: number;
};

type BannersGrouped = { [zoneSlug: string]: Banner[] };

type BannersResponse =
  | { success: true; data: Banner[] }       // با placement
  | { success: true; data: BannersGrouped }; // بدون placement
```

---

## ۳) فرمت جدید پاسخ فاکتور

### چه چیزی عوض شد؟

پاسخ `GET /v1/customer/orders/{id}/invoice` الان علاوه بر فیلدهای قبلی **به‌صورت backward-compatible** فیلدهای جدید دارد:

| فیلد جدید | معادل قبلی | محتوا |
|-----------|------------|---------|
| `issued_at_jalali` | `issued_at` (ISO8601 UTC) | تاریخ شمسی فرمت‌شده (TZ: Tehran) — `"1405/03/18 23:04"` |
| `payment.paid_at_jalali` | `payment.paid_at` | همان منطق برای زمان پرداخت |
| `totals.subtotal_formatted` | `totals.subtotal` (integer) | رشته فارسی — `"۹۰٬۰۰۰ تومان"` |
| `totals.discount_formatted` | `totals.discount` | همانند بالا |
| `totals.tax_formatted` | `totals.tax` | همانند بالا |
| `totals.total_formatted` | `totals.total` | همانند بالا |
| `items[].unit_price_formatted` | `items[].unit_price` | همانند بالا |
| `items[].amount_formatted` | `items[].amount` | همانند بالا |

> ⚠️ **فیلدهای قبلی همچنان باقی هستند** — فرانت می‌تواند:
> - برای **منطق مقایسه/مرتب‌سازی**: از فیلدهای integer/ISO استفاده کند
> - برای **نمایش مستقیم به کاربر**: از فیلدهای `_formatted` و `_jalali` استفاده کند

### نمونه‌ی پاسخ (با فرمت جدید)

```jsonc
{
  "success": true,
  "data": {
    "invoice_number": "INV-2606-00005",
    "order_id": 25,
    "tracking_code": "ORD-2606-00004",
    "issued_at": "2026-06-08T19:34:38+00:00",
    "issued_at_jalali": "1405/03/18 23:04",
    "status": "issued",
    "customer": {
      "name": "محسن هاشم پور",
      "phone": "09918911126",
      "address": "..."
    },
    "technician": { "id": 1, "name": "تست تکنسی" },
    "items": [
      {
        "row": 1,
        "type": "transport",
        "description": "ایاب و ذهاب",
        "quantity": 1,
        "unit_price": 20000,
        "unit_price_formatted": "۲۰٬۰۰۰ تومان",
        "amount": 20000,
        "amount_formatted": "۲۰٬۰۰۰ تومان",
        "warranty_months": null
      },
      {
        "row": 2,
        "type": "part",
        "description": "واشر",
        "quantity": 1,
        "unit_price": 45000,
        "unit_price_formatted": "۴۵٬۰۰۰ تومان",
        "amount": 45000,
        "amount_formatted": "۴۵٬۰۰۰ تومان",
        "warranty_months": null
      }
    ],
    "totals": {
      "subtotal": 90000,
      "subtotal_formatted": "۹۰٬۰۰۰ تومان",
      "discount": 0,
      "discount_formatted": "۰ تومان",
      "discount_code": null,
      "tax_rate": 0,
      "tax": 0,
      "tax_formatted": "۰ تومان",
      "total": 90000,
      "total_formatted": "۹۰٬۰۰۰ تومان",
      "currency": "IRT"
    },
    "payment": {
      "method": null,
      "is_paid": false,
      "paid_at": null,
      "paid_at_jalali": null,
      "payment_url": "http://127.0.0.1:8000/crm/pay/INV-2606-00005"
    },
    "notes": "...",
    "pdf_url": "http://127.0.0.1:8000/crm/receipt/INV-2606-00005"
  }
}
```

### نکات

- **واحد پول API همچنان تومان** است (`currency: "IRT"`). در ادمین CRM به ریال نمایش داده می‌شود (یعنی ۱۰ برابر API). در پاسخ موبایل، تومان قطعی است.
- **`subtotal_formatted` و سایر `_formatted`** شامل پسوند `" تومان"` هستند. اگر می‌خواهید واحد را خودتان مدیریت کنید، از `subtotal` integer + `Intl.NumberFormat('fa-IR').format(subtotal)` استفاده کنید.
- **اعداد فارسی (۰..۹)** و **جداکننده هزارگان `٬`** (U+066C ARABIC THOUSANDS SEPARATOR).
- **TZ تاریخ شمسی Tehran** — همان TZ ادمین CRM، پس مطابقت دارد.

### TypeScript Types

```ts
type InvoiceItem = {
  row: number;
  type: 'service' | 'part' | 'labor' | 'transport';
  description: string;
  quantity: number;
  unit_price: number;
  unit_price_formatted: string;  // "۲۰٬۰۰۰ تومان"
  amount: number;
  amount_formatted: string;
  warranty_months: number | null;
};

type InvoiceTotals = {
  subtotal: number;
  subtotal_formatted: string;
  discount: number;
  discount_formatted: string;
  discount_code: string | null;
  tax_rate: number;
  tax: number;
  tax_formatted: string;
  total: number;
  total_formatted: string;
  currency: 'IRT';
};

type Invoice = {
  invoice_number: string | null;
  order_id: number;
  tracking_code: string;
  issued_at: string | null;            // ISO8601 UTC
  issued_at_jalali: string | null;     // "Y/m/d H:i" — Tehran
  status: 'draft' | 'issued' | 'paid' | 'cancelled';
  customer: { name: string; phone: string; address: string };
  technician: { id: number; name: string } | null;
  items: InvoiceItem[];
  totals: InvoiceTotals;
  payment: {
    method: 'online' | null;
    is_paid: boolean;
    paid_at: string | null;
    paid_at_jalali: string | null;
    payment_url: string | null;
  };
  notes: string | null;
  pdf_url: string | null;
};
```

### نمونه‌ی نمایش

```tsx
<div className="invoice-row">
  <span>{item.description}</span>
  <span>{item.quantity} عدد</span>
  <span>{item.amount_formatted}</span>
</div>

<div className="invoice-meta">
  تاریخ صدور: {invoice.issued_at_jalali}
</div>

<div className="invoice-total">
  مبلغ نهایی: <strong>{invoice.totals.total_formatted}</strong>
</div>
```

---

## خلاصه

| موضوع | اقدام فرانت |
|------|---------------|
| Objections per device | پارامتر `device_id` به request اضافه کنید + هنگام تغییر دستگاه reset کنید |
| Banners | از `placement=<zone_slug>` استفاده کنید — لیست zoneها در جدول بالا |
| Invoice format | از فیلدهای `_formatted` و `_jalali` برای نمایش مستقیم استفاده کنید — منطق مقایسه با integer/ISO ادامه دهید |

سؤالی بود اطلاع بدهید.
