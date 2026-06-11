# بنرهای اپلیکیشن مشتریان — راهنمای کامل

> تاریخ: 2026-06-11
> مخاطب: تیم فرانت (اپ موبایل) + تیم محتوا (ادمین که بنر می‌سازد)

این سند سه چیز را پوشش می‌دهد:
1. **برای ادمین**: نحوه‌ی ساخت بنر در پنل
2. **برای فرانت**: نحوه‌ی فراخوانی و نمایش بنرها در اپ
3. **برای هر دو**: ارائه‌ی درخواست‌های گسترش (zone جدید، tracking، …)

---

## ۱) معماری کلی

```
┌──────────────────────────┐        ┌────────────────────────────┐
│   پنل ادمین (وب)         │        │   اپلیکیشن مشتریان           │
│   /admin/site/banners    │        │                              │
│                          │        │                              │
│   ادمین یک بنر می‌سازد   │        │  GET /v1/customer/services/  │
│   و آن را به یک "zone"   │        │      banners?placement=<slug>│
│   اختصاص می‌دهد           │        │                              │
└────────────┬─────────────┘        └──────────────┬───────────────┘
             │                                     │
             ▼                                     ▼
        ┌─────────────────────────────────────────────┐
        │   جدول‌های دیتابیس                            │
        │   • site_banner_zones (مکان‌ها)               │
        │   • site_banners       (محتوا)                │
        │   • site_media         (تصاویر)                │
        └─────────────────────────────────────────────┘
```

**اصطلاحات:**
- **Zone (مکان)**: یک «محل قرارگیری» در اپ — مثل «بالای صفحه اصلی». ادمین چندین بنر را به یک zone اختصاص می‌دهد.
- **Banner**: یک تبلیغ مشخص — عنوان، تصویر، لینک، تاریخ شروع/پایان.
- **slug**: کلید رشته‌ای zone — فرانت با آن بنرها را می‌خواهد.

---

## ۲) Zoneهای آماده برای اپ

این ۴ zone با پیشوند `app_` از قبل seed شده‌اند:

| Slug | محل در اپ | ابعاد پیشنهادی | max | کاربرد |
|---|---|---|---|---|
| `app_home_top` | بالای صفحه اصلی (اسلایدر) | 1080×540 | ۵ | بنر هیرو، اسلایدر برندینگ |
| `app_home_promo` | پایین‌تر در صفحه اصلی | 1080×400 | ۳ | کد تخفیف، خدمت جدید |
| `app_orders_promo` | صفحه «سفارش‌های من» | 1080×300 | ۲ | معرفی خدمت در زمان مرور سفارش‌ها |
| `app_profile_promo` | صفحه پروفایل | 1080×300 | ۲ | دعوت دوستان، باشگاه مشتریان |

> پیشوند `app_` عمداً انتخاب شده تا با zoneهای وب (`home_hero`, `blog_sidebar`, …) قاطی نشود.

### اگر zone جدید نیاز دارید

تیم فرانت می‌تواند درخواست کند که zone جدید اضافه شود — کافی است slug + محل + ابعاد را اعلام کنید. ادمین از `/admin/site/banner-zones` می‌تواند خودش هم بسازد بدون نیاز به deploy.

---

## ۳) برای ادمین — ساخت بنر قدم به قدم

### پیش‌نیاز: تصویر را در رسانه‌ها آپلود کنید
1. `/admin/site/media` → دکمه «آپلود»
2. فایل را انتخاب کنید (ترجیحاً WebP، حداکثر ۲۰ مگ)
3. سیستم variantها (thumbnail, medium) را خودکار می‌سازد

### ساخت بنر
1. `/admin/site/banners` → دکمه «+ بنر جدید»
2. فرم بنر:

| فیلد | الزامی؟ | توضیح |
|---|---|---|
| **عنوان** (`title`) | ✅ | عنوان داخلی + alt تصویر |
| **زیرعنوان** (`subtitle`) | ❌ | متن کمکی (اختیاری) |
| **زون** (`zone_id`) | ✅ | یکی از zoneهای بالا را انتخاب کنید (مثلاً `app_home_top`) |
| **تصویر دسکتاپ** (`media_id`) | ✅ | از media library انتخاب کنید (یا URL مستقیم در `image_url`) |
| **تصویر موبایل** (`media_id_mobile`) | ❌ | نسخه‌ی portrait برای موبایل (اگر متفاوت می‌خواهید) |
| **لینک مقصد** (`link_url`) | ❌ | کلیک کاربر کجا برود — می‌تواند **داخلی** باشد (`/orders/new`) یا **خارجی** (`https://...`) |
| **متن دکمه** (`link_label`) | ❌ | اگر CTA دارید (مثلاً «همین حالا ثبت کن») |
| **ترتیب** (`sort_order`) | ❌ | عدد کوچک‌تر = اول لیست |
| **منتشر** (`is_published`) | ✅ | اگر off باشد، در اپ نمایش داده نمی‌شود |
| **شروع** (`starts_at`) | ❌ | از این تاریخ به بعد فعال (مثلاً برای کمپین آینده) |
| **پایان** (`ends_at`) | ❌ | بعد از این تاریخ خودکار غیرفعال |

3. ذخیره → بنر بلافاصله در اپ ظاهر می‌شود (cache ۵ دقیقه‌ای CDN).

### نکات مهم برای ادمین

- 🔴 **`is_published` + بازه‌ی زمانی هر دو باید درست باشند**؛ هر کدام false/خارج از بازه → بنر در API برنمی‌گردد.
- 🟡 ابعاد را رعایت کنید — تصویر با نسبت اشتباه crop می‌شود.
- 🟢 برای اسلایدر `app_home_top` بنرها به ترتیب `sort_order` نمایش داده می‌شوند.
- 🟢 برای کمپین زمان‌دار، `starts_at` و `ends_at` را ست کنید — لازم نیست یادتان باشد بنر را خاموش کنید.

---

## ۴) برای فرانت — فراخوانی و نمایش

### Endpoint اصلی

#### `GET /v1/customer/services/banners?placement=<slug>` (public)

```bash
GET /v1/customer/services/banners?placement=app_home_top
Accept: application/json
# auth لازم نیست + Cache-Control: public, max-age=300
```

```jsonc
{
  "success": true,
  "data": [
    {
      "id": "01HZX...ULID",
      "title": "تخفیف ویژه پاییز",
      "image_url": "https://panel.tamironline.com/storage/site/media/.../xxx.webp",
      "link_url": "/orders/new?promo=AUTUMN",
      "placement": "app_home_top",
      "active": true,
      "order": 1
    }
  ]
}
```

**ویژگی‌های پاسخ:**
- `id` — ULID رشته‌ای (نه integer) — برای key React استفاده کنید
- `image_url` — همیشه absolute URL (بعد از fix قبلی) — مستقیم در `<Image src>`
- `link_url` — می‌تواند **داخلی** (`/orders/new`) یا **خارجی** (`https://...`) باشد. فرانت باید این را تشخیص بدهد:
  - شروع با `http://` یا `https://` → لینک خارجی → `Linking.openURL` یا تب جدید
  - شروع با `/` → روت داخلی → router فرانت
- `order` — مرتب از قبل صعودی است — نیازی به sort مجدد ندارید
- اگر zone خالی است (هیچ بنر منتشر فعال نیست) → `data: []` — کاری نکنید

### حالت گروهی (همه placementها یکجا)

#### `GET /v1/customer/services/banners` (بدون placement)

برای splash اپ، همه‌ی zoneها را یکجا بخوانید (یک request به‌جای ۴ تا):

```jsonc
{
  "success": true,
  "data": {
    "app_home_top":     [{...}, {...}],
    "app_home_promo":   [{...}],
    "app_orders_promo": [],
    "app_profile_promo":[{...}],
    "home_hero":        [{...}],
    ...
  }
}
```

> این شامل **همه‌ی** zoneها (وب + اپ) است. در اپ بهتر است فقط placement خاص را بخوانید مگر در splash که کل لیست را cache می‌کنید.

### نمونه‌ی پیاده‌سازی (React Native / Expo)

```tsx
type AppBanner = {
  id: string;
  title: string;
  image_url: string;
  link_url: string | null;
  placement: string;
  active: boolean;
  order: number;
};

// hook ساده با cache 5 دقیقه‌ای
function useBanners(placement: string) {
  const [banners, setBanners] = useState<AppBanner[]>([]);

  useEffect(() => {
    let cancelled = false;
    fetch(`${API_BASE}/v1/customer/services/banners?placement=${placement}`)
      .then(r => r.json())
      .then(json => { if (!cancelled) setBanners(json.data ?? []); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [placement]);

  return banners;
}

// نمایش — اسلایدر hero
function HomeHeroSlider() {
  const banners = useBanners('app_home_top');
  if (banners.length === 0) return null;

  return (
    <Swiper autoplay autoplayTimeout={5}>
      {banners.map(b => (
        <Pressable
          key={b.id}
          onPress={() => handleBannerPress(b)}
          accessibilityLabel={b.title}
        >
          <Image
            source={{ uri: b.image_url }}
            style={{ width: '100%', aspectRatio: 1080/540 }}
            resizeMode="cover"
          />
        </Pressable>
      ))}
    </Swiper>
  );
}

// مدیریت لینک — داخلی vs خارجی
function handleBannerPress(b: AppBanner) {
  if (!b.link_url) return;
  if (/^https?:\/\//.test(b.link_url)) {
    Linking.openURL(b.link_url);
  } else {
    router.push(b.link_url);   // یا navigation.navigate بسته به stack شما
  }
}
```

### نمونه‌ی بنر تبلیغی ساده (single)

```tsx
function ProfilePromoBanner() {
  const [b] = useBanners('app_profile_promo'); // اولین بنر فعال
  if (!b) return null;

  return (
    <Pressable onPress={() => handleBannerPress(b)}>
      <Image
        source={{ uri: b.image_url }}
        style={{ width: '100%', aspectRatio: 1080/300, borderRadius: 12 }}
      />
    </Pressable>
  );
}
```

---

## ۵) بهترین رفتارها (Best Practices)

### فرانت

- ✅ **بنرها را cache کنید** — `Cache-Control: public, max-age=300` سرور می‌فرستد. اگر اپ شما SWR/React Query دارد، همین کافی است.
- ✅ **اگر `data: []` بود کاری نکنید** — هیچ skeleton/placeholder نشان ندهید. zone خالی = «بنری نباشد».
- ✅ **`accessibilityLabel`** را از `title` پر کنید — برای screen reader مهم است.
- ✅ **lazy loading** برای بنرهای پایین صفحه (`app_profile_promo`).
- ⚠️ **link_url را trust نکنید** — اگر URL خارجی است حتماً تأیید کاربر (یا `WebView` در اپ) داشته باشید.
- ⚠️ **fail-silent** — اگر API down شد، اپ نباید بشکند. صرفاً بنر نمایش نده.

### ادمین

- ✅ تصویر را در رسانه‌ها (media library) آپلود کنید، نه به‌صورت URL خارجی. تصاویر داخلی variants خودکار، CDN cache بهتر، و backup دارند.
- ✅ از `starts_at` / `ends_at` برای کمپین‌های زمان‌دار استفاده کنید.
- ✅ ابعاد دقیق را رعایت کنید — اگر بنر در نسبت اشتباه باشد crop می‌شود.
- ⚠️ تعداد بنرهای فعال هر zone را در حد `max_count` نگه دارید — اضافه‌تر کاربر را خسته می‌کند.

---

## ۶) قابلیت‌هایی که الان نداریم — اگر می‌خواهید درخواست دهید

این موارد **پیاده‌سازی نشده‌اند**؛ اگر تیم فرانت نیاز دارد، اعلام کنید تا اضافه کنیم:

| قابلیت | وضعیت | نظر |
|---|---|---|
| ستون‌های `impressions` و `clicks` در DB | ✅ موجود ولی endpoint event ندارد | اگر می‌خواهید tracking، endpointهای `POST /banners/{id}/impression` و `POST /banners/{id}/click` می‌سازیم |
| تصویر جداگانه برای **موبایل vs دسکتاپ** | ✅ DB دارد (`media_id_mobile`) | در API برای اپ فعلاً فقط `image_url` (تصویر اصلی) برمی‌گردد — اگر می‌خواهید هر دو، می‌گوییم در پاسخ هم بیاید |
| **deep link** به صفحات خاص اپ | ⚠️ نسبتاً | الان `link_url` به‌صورت `/orders/new` کار می‌کند. اگر طرحواره URI مخصوص اپ می‌خواهید (مثلاً `tamironline://orders/new`) اعلام کنید |
| **targeting** بر اساس کاربر (user-specific) | ❌ | همه‌ی بنرها برای همه‌ی کاربران هستند. اگر می‌خواهید بنر فقط به برخی کاربران نشان داده شود، نیازمند طراحی segment است |
| **A/B testing** بنر | ❌ | اگر می‌خواهید چند variant از یک بنر بسنجید، نیازمند طراحی است |
| **کمپین‌بندی** (چند بنر زیر یک کمپین) | ❌ | فعلاً هر بنر مستقل است |
| **تصویر برای حالت تیره (dark mode)** | ❌ | فعلاً یک تصویر، می‌توان فیلد `media_id_dark` اضافه کرد |

### قالب درخواست از تیم فرانت

```yaml
نام درخواست: <مثلاً tracking کلیک بنر>
چرا نیاز داریم: <مثلاً ادمین می‌خواهد بداند کدام کمپین موثرتر است>
endpoint پیشنهادی: <مثلاً POST /v1/customer/services/banners/{id}/click>
شکل request/response: <نمونه JSON>
رفتار خطا: <چه می‌شود اگر شکست بخورد>
اولویت: low/medium/high
```

---

## ۷) عیب‌یابی (Troubleshooting)

| علامت | علت احتمالی | راه حل |
|---|---|---|
| بنر در ادمین ست شده ولی در API نیست | `is_published = false` یا خارج از بازه‌ی `starts_at..ends_at` | چک کنید در ادمین «منتشر» باشد و تاریخ شروع گذشته باشد |
| `image_url` نسبی برمی‌گردد | باگ MediaUrl که قبلاً fix شد | مطمئن شوید آخرین deploy روی production هست (commit `7600cb8` به بعد) |
| تغییر بنر تا ۵ دقیقه در اپ ظاهر نمی‌شود | `Cache-Control: max-age=300` | بعد از ۵ دقیقه خودکار رفرش می‌شود؛ برای فوری، فرانت می‌تواند query param `?v={timestamp}` اضافه کند |
| `data` آبجکت برمی‌گردد نه آرایه | placement را در URL نگذاشته‌اید | حالت بدون placement گروهی برمی‌گرداند؛ برای آرایه‌ی صاف، `?placement=<slug>` بدهید |
| بنر در iOS بد crop می‌شود | تصویر portrait مناسب موبایل نیست | تصویر `media_id_mobile` جداگانه آپلود کنید (نیازمند تغییر API — درخواست دهید) |

---

## ۸) اطلاعات تکمیلی

- **Endpoint مرجع API**: `GET /v1/customer/services/banners[?placement=<slug>]`
- **مدیریت در ادمین**: `/admin/site/banners` (CRUD بنر) + `/admin/site/banner-zones` (CRUD zone)
- **مدل دیتابیس**: `site_banners` + `site_banner_zones` + `site_media`
- **Cache**: ۵ دقیقه روی CDN/proxy + Etag بعداً ممکن
- **سند فرمت‌های قبلی بنر** (وب): `docs/FRONTEND_OBJECTIONS_BANNERS_INVOICE.md` (بخش بنرها — همان قرارداد، فقط placement فرق دارد)

سؤالی بود اطلاع دهید.
