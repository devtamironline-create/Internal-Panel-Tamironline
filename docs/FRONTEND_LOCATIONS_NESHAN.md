# Frontend — فلوی جدید آدرس: شهر ← منطقه + نقشه نشان

> تاریخ: 2026-06-11
> مخاطب: تیم فرانت (اپ مشتریان)
> دو تغییر: (۱) استان دیگر از کاربر پرسیده نمی‌شود — فلو «شهر → منطقه» شد. (۲) انتخاب موقعیت از نقشه نشان (neshan.org) به آدرس‌ها اضافه شد.

---

## ۱. فلوی جدید انتخاب موقعیت در فرم آدرس

### قبل (منسوخ)

```
استان (dropdown) → شهر (dropdown) → متن آدرس
```

### بعد (جدید)

```
شهر (dropdown) → منطقه (dropdown، اگر شهر منطقه دارد) → پین نقشه (اختیاری) → متن آدرس
```

- **استان پرسیده نمی‌شود** — سرور خودش از شهر تشخیص می‌دهد و در پاسخ `state_id`/`state_name` برمی‌گرداند.
- فقط شهرهای استان‌های سرویس‌دهی برمی‌گردند (فعلاً تهران + البرز — ادمین می‌تواند بعداً گسترش دهد، فرانت نیازی به تغییر ندارد).

### Endpointها

#### `GET /v1/customer/locations/cities` (public، cache 1h)

```jsonc
{
  "success": true,
  "data": [
    { "id": 5,  "state_id": 1,  "name": "تهران", "slug": "tehran", "has_districts": true },
    { "id": 9,  "state_id": 18, "name": "کرج",   "slug": "karaj",  "has_districts": true },
    { "id": 12, "state_id": 1,  "name": "شهریار", "slug": "shahriar", "has_districts": false }
  ]
}
```

- `has_districts` — اگر `true`، مرحله‌ی «انتخاب منطقه» را نمایش دهید؛ اگر `false`، مستقیم به نقشه/متن آدرس بروید.
- پارامتر `state_id` همچنان پذیرفته می‌شود (سازگاری عقب‌رو) ولی لازم نیست.

#### `GET /v1/customer/locations/districts?city_id=5` 🆕 (public، cache 1h)

```jsonc
{
  "success": true,
  "data": [
    { "id": 101, "city_id": 5, "name": "منطقه ۱ تهران", "slug": "tehran-district-1" },
    { "id": 102, "city_id": 5, "name": "منطقه ۲ تهران", "slug": "tehran-district-2" }
  ],
  "meta": { "city_id": 5, "total": 22 }
}
```

خطاها:
- بدون `city_id` → `422 city_id_required`
- `city_id` نامعتبر (یا خودش یک منطقه باشد) → `422 invalid_city`

#### `GET /v1/customer/locations/states` (public — تغییر رفتار)

هنوز کار می‌کند ولی حالا **فقط استان‌های سرویس‌دهی** را برمی‌گرداند (تهران + البرز). در فلوی جدید نیازی به آن ندارید.

---

## ۲. ثبت/ویرایش آدرس — payload جدید

#### `POST /v1/customer/addresses` و `PUT /v1/customer/addresses/{id}`

```jsonc
// Request — فیلدهای جدید
{
  "label": "خانه",
  "city_id": 5,                 // اجباری — شهر اصلی
  "district_id": 101,           // اختیاری — اگر شهر منطقه دارد، بفرستید
  "full_address": "خیابان ولیعصر، کوچه...، پلاک ۱۰",
  "latitude": 35.7219,          // اختیاری — از پین نقشه
  "longitude": 51.3347,         // اختیاری — همراه latitude (هر دو یا هیچ‌کدام)
  "postal_code": "1234567890",
  "phone": "02112345678",
  "is_default": true
}
```

نکات:
- **`province_id` دیگر لازم نیست** — اگر بفرستید، نادیده گرفته می‌شود؛ سرور از شهر تشخیص می‌دهد.
- `latitude`/`longitude` باید **با هم** بیایند (یکی بدون دیگری → `422`).
- اگر به اشتباه id یک «منطقه» را در `city_id` بفرستید، سرور خودش اصلاح می‌کند (منطقه → `district_id`، شهر والد → `city_id`) — ولی فلوی درست همان city + district جدا است.
- خطای `district_id` نامرتبط با شهر → `422` با پیام «منطقه‌ی انتخاب‌شده به این شهر تعلق ندارد.»

```jsonc
// Response — فیلدهای جدید
{
  "data": {
    "id": 12,
    "label": "خانه",
    "full_address": "...",
    "state_id": 1,
    "state_name": "تهران",
    "city_id": 5,
    "city_name": "تهران",
    "district_id": 101,
    "district_name": "منطقه ۱ تهران",
    "latitude": 35.7219,
    "longitude": 51.3347,
    "postal_code": "1234567890",
    "phone": "02112345678",
    "is_default": true,
    "created_at": "2026-06-11T10:00:00Z"
  }
}
```

---

## ۳. نقشه نشان (neshan.org)

### نمایش نقشه در اپ — Web SDK رسمی (Mapbox-gl)

کلید وب از تیم بک‌اند بگیرید (env `NESHAN_WEB_KEY` — مقدارش با شما به اشتراک گذاشته می‌شود؛ کلید وب public-facing است و فقط به domainهای ثبت‌شده سرویس می‌دهد).

⚠️ **دو کلید جدا هستند — قاطی نکنید:**
- کلید **Web** (نقشه) → فقط برای SDK نمایش نقشه
- کلید **وب‌سرویس** (REST) → فقط سمت سرور؛ هرگز به فرانت نمی‌رود

#### نصب — React (پکیج رسمی نشان)

```bash
# .npmrc در روت پروژه:
# @neshan-maps-platform:registry=https://npm.neshan.org

npm install @neshan-maps-platform/mapbox-gl-react @neshan-maps-platform/mapbox-gl
```

```tsx
import { MapComponent, MapTypes } from '@neshan-maps-platform/mapbox-gl-react';
import nmp_mapboxgl from '@neshan-maps-platform/mapbox-gl';
import '@neshan-maps-platform/mapbox-gl/dist/NeshanMapboxGl.css';

function AddressMapPicker({ onPicked }: { onPicked: (lat: number, lng: number) => void }) {
  const mapRef = useRef<any>(null);

  return (
    <MapComponent
      options={{
        mapKey: process.env.NEXT_PUBLIC_NESHAN_WEB_KEY!,
        mapType: MapTypes.neshanVector,
        center: [51.3890, 35.6892],   // ⚠️ mapbox = [lng, lat] — برعکس leaflet!
        zoom: 13,
        poi: true,
        traffic: false,
        isTouchPlatform: true,
      }}
      mapSetter={(map) => {
        mapRef.current = map;
        // الگوی «پین وسط صفحه»: marker ثابت در center، با حرکت نقشه آپدیت می‌شود
        const marker = new nmp_mapboxgl.Marker()
          .setLngLat(map.getCenter())
          .addTo(map);
        map.on('move', () => marker.setLngLat(map.getCenter()));
        map.on('moveend', () => {
          const { lat, lng } = map.getCenter();
          onPicked(lat, lng);   // → debounce + صدا زدن reverse-geocode
        });
      }}
    />
  );
}
```

> ⚠️ کامپوننت React نشان فعلاً از **SSR/SSG پشتیبانی نمی‌کند** — در Next.js حتماً با `dynamic(() => import(...), { ssr: false })` لود کنید.

#### نصب — Vanilla JS / CDN

```html
<link rel="stylesheet" href="https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.css" />
<script src="https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.js"></script>
```

```js
const map = new nmp_mapboxgl.Map({
  mapType: nmp_mapboxgl.Map.mapTypes.neshanVector,
  container: 'map',
  zoom: 13,
  center: [51.3890, 35.6892],   // [lng, lat]
  mapKey: NESHAN_WEB_KEY,
  poi: true,
  traffic: false,
});
new nmp_mapboxgl.Marker().setLngLat([51.3890, 35.6892]).addTo(map);
```

### تبدیل نقطه به آدرس — از طریق پروکسی بک‌اند (نه مستقیم!)

**کلید REST نشان هرگز به فرانت نمی‌رود.** بک‌اند پروکسی امن دارد:

#### `GET /v1/customer/locations/reverse-geocode?lat=35.7219&lng=51.3347` 🆕

- **Private** — نیاز به `Authorization: Bearer <token>`
- Rate limit: ۳۰ درخواست در دقیقه per user — پس debounce کنید (مثلاً فقط روی `moveend`، نه `move`)
- پاسخ ۲۴ ساعت سمت سرور cache می‌شود

```jsonc
// Response 200
{
  "success": true,
  "data": {
    "formatted_address": "تهران، ونک، خیابان ملاصدرا...",
    "province": "تهران",
    "city": "تهران",
    "district": "منطقه ۳",
    "route": "خیابان ملاصدرا"
  }
}
```

خطاها:
- `503 neshan_not_configured` — کلید هنوز ست نشده (تا قبل از تحویل کلید این را می‌گیرید)
- `503 neshan_key_misconfigured` — نوع کلید اشتباه است (مشکل سمت بک‌اند — گزارش دهید، retry فایده ندارد)
- `502 reverse_geocode_failed` — خطای موقت نشان؛ دکمه retry نمایش دهید

### عیب‌یابی کلید نشان (سمت بک‌اند/ادمین)

اگر `neshan_key_misconfigured` گرفتید، در لاگ سرور (`neshan.reverse_failed`) دلیل دقیق ثبت می‌شود:

| HTTP نشان | معنی | راه حل |
|---|---|---|
| 480 KeyNotFound | کلید نامعتبر | کلید را از پنل نشان دوباره کپی کنید |
| **483 ApiKeyTypeError** | **نوع کلید اشتباه — رایج‌ترین خطا!** کلید Web (نقشه) را در `NESHAN_SERVICE_KEY` گذاشته‌اید | در پنل نشان یک کلید از نوع **«وب‌سرویس»** بسازید |
| 484 ApiWhiteListError | IP/دامنه سرور در whitelist کلید نیست | IP سرور را به whitelist کلید اضافه کنید |
| 485 ApiServiceListError | سرویس «تبدیل نقطه به آدرس» برای کلید فعال نیست | در تنظیمات کلید این سرویس را تیک بزنید |
| 481/482 | سهمیه/نرخ تمام شده | پلن نشان را ارتقا دهید (این خطا 502 برمی‌گردد نه 503) |

### UX پیشنهادی فرم آدرس

```
۱. کاربر شهر را انتخاب می‌کند (city_id)
۲. اگر has_districts → کاربر منطقه را انتخاب می‌کند (district_id)
۳. نقشه باز می‌شود — center اولیه روی شهر انتخابی
۴. کاربر پین را جابه‌جا می‌کند → moveend → reverse-geocode → نمایش آدرس متنی زیر نقشه
۵. کاربر می‌تواند آدرس متنی را ویرایش/تکمیل کند (پلاک، واحد، ...)
۶. POST /addresses با city_id + district_id + lat/lng + full_address
```

نقشه **اختیاری** است — اگر کاربر اجازه‌ی location ندهد یا رد کند، فرم بدون مختصات هم ثبت می‌شود.

---

## خلاصه‌ی Breaking Changes

| تغییر | اقدام فرانت |
|---|---|
| `POST /addresses` دیگر `province_id` نمی‌خواهد | حذف فیلد استان از فرم |
| `GET /locations/cities` فقط شهرهای اصلی استان‌های سرویس‌دهی | dropdown استان حذف شود |
| endpoint جدید `districts` | مرحله‌ی منطقه وقتی `has_districts: true` |
| فیلدهای جدید آدرس: `district_id/district_name/latitude/longitude` | نمایش در لیست آدرس‌ها + کارت آدرس |
| پروکسی `reverse-geocode` | جایگزین صدا زدن مستقیم نشان |
