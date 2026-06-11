# 🔴 فوری — Loop در reverse-geocode فرانت

> تاریخ: 2026-06-11
> اولویت: **بالا** — مصرف بی‌جای سهمیه نشان + احتمال 429
> مخاطب: تیم فرانت اپ مشتریان

---

## مشاهده

لاگ سرور پروداکشن نشان می‌دهد endpoint `GET /v1/customer/locations/reverse-geocode` **بدون کوچک‌ترین حرکت کاربر** درخواست‌های مکرر می‌گیرد:

```
21:17:29 → 2 درخواست همزمان
21:17:31 → 2 درخواست همزمان  ← هر ۲ ثانیه!
21:17:33 → 2 درخواست همزمان
21:17:35 → 2 درخواست همزمان
... (در ۲۳ ثانیه: ۲۶ درخواست = ~۶۷/min)
```

### چرا این مشکل است؟

| پیامد | توضیح |
|---|---|
| 💸 مصرف سهمیه نشان | هر cache miss یک round-trip ~۵۰۰ms به نشان = پولِ پلن نشان |
| 🚫 429 Too Many Requests | throttle حالا روی **۲۰/min** ست شده — کاربر بعد از ۲۰ ثانیه بلاک می‌شود |
| 📵 تجربه کاربر | اگر در حال انتخاب آدرس باشد، بعد از throttle نمی‌تواند ادامه دهد |
| 🔋 باتری موبایل | درخواست بی‌جا = تخلیه باتری |

---

## تشخیص — احتمالاً یکی از این چهار است

### علت #1: React StrictMode (دو درخواست همزمان)

```jsx
// ❌ اشتباه — در StrictMode دو بار اجرا می‌شود
useEffect(() => {
  fetch(`/v1/customer/locations/reverse-geocode?lat=${lat}&lng=${lng}`)
    .then(r => r.json())
    .then(setAddress);
}, [lat, lng]);
```

دو درخواست به سرور می‌رسد چون cleanup ندارد و request نمی‌تواند abort شود.

### علت #2: handler روی `move` نه `moveend`

```js
// ❌ اشتباه — move در هر پیکسل drag فایر می‌شود (می‌تواند 60/s باشد!)
map.on('move', () => {
  const c = map.getCenter();
  fetchReverseGeocode(c.lat, c.lng);
});

// ✅ درست — moveend فقط یک بار بعد از پایان حرکت
map.on('moveend', () => {
  const c = map.getCenter();
  fetchReverseGeocode(c.lat, c.lng);
});
```

### علت #3: بدون debounce

حتی با `moveend` اگر کاربر چندبار سریع جابه‌جا کند، هر بار یک request می‌رود. debounce لازم است.

### علت #4: State loop (شایع‌ترین در فلوی شما)

```jsx
// ❌ Loop — این الگو ~۲ثانیه‌ای ست!
function AddressPicker() {
  const [center, setCenter] = useState([35.69, 51.39]);
  const [address, setAddress] = useState(null);

  return (
    <MapComponent
      options={{ center, ... }}
      mapSetter={(map) => {
        map.on('moveend', () => {
          const c = map.getCenter();
          setCenter([c.lng, c.lat]);  // ← باعث re-render
          fetchReverseGeocode(c.lat, c.lng);
        });
      }}
    />
  );
  // re-render → MapComponent دوباره mount → moveend دوباره فایر → loop
}
```

---

## ✅ پیاده‌سازی مرجع (کپی کنید)

تمام چهار علت بالا در این کد fix شده:

```tsx
import { useEffect, useRef, useState, useCallback } from 'react';
import dynamic from 'next/dynamic';

// 🔑 نکته #1: SSR را غیرفعال کنید (SDK نشان از SSR پشتیبانی نمی‌کند)
const MapComponent = dynamic(
  () => import('@neshan-maps-platform/mapbox-gl-react').then(m => m.MapComponent),
  { ssr: false }
);

const NESHAN_WEB_KEY = process.env.NEXT_PUBLIC_NESHAN_WEB_KEY!;
const DEBOUNCE_MS = 800;     // فقط بعد از ۸۰۰ms سکوت کاربر، درخواست
const DEFAULT_CENTER: [number, number] = [51.3890, 35.6892]; // [lng, lat]

type ReverseAddress = {
  formatted_address: string | null;
  province: string | null;
  city: string | null;
  district: string | null;
  neighbourhood: string | null;
};

export function AddressMapPicker({
  onPicked,
}: {
  onPicked: (data: { lat: number; lng: number; address: ReverseAddress | null }) => void;
}) {
  // 🔑 نکته #2: map و marker در useRef — نه در state (تا re-render نشود)
  const mapRef = useRef<any>(null);
  const markerRef = useRef<any>(null);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const [address, setAddress] = useState<ReverseAddress | null>(null);
  const [loading, setLoading] = useState(false);

  // 🔑 نکته #3: stable callback (useCallback) تا map handler dependency loop نداشته باشد
  const fetchReverseGeocode = useCallback(async (lat: number, lng: number) => {
    // درخواست قبلی را cancel کن — جلوگیری از race + spam
    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;

    setLoading(true);
    try {
      const res = await fetch(
        `${API_BASE}/v1/customer/locations/reverse-geocode?lat=${lat}&lng=${lng}`,
        {
          headers: { Authorization: `Bearer ${getToken()}` },
          signal: ctrl.signal,
        }
      );

      if (res.status === 429) {
        console.warn('reverse-geocode rate-limited — debounce کافی نیست');
        return;
      }
      if (!res.ok) return;

      const json = await res.json();
      const addr = json?.data ?? null;
      setAddress(addr);
      onPicked({ lat, lng, address: addr });
    } catch (e: any) {
      if (e.name !== 'AbortError') console.error(e);
    } finally {
      setLoading(false);
    }
  }, [onPicked]);

  // 🔑 نکته #4: handler خارج از mapSetter — یک بار attach، نه روی هر render
  const attachHandlers = useCallback((map: any) => {
    mapRef.current = map;

    // marker یک بار ساخته می‌شود
    import('@neshan-maps-platform/mapbox-gl').then(({ default: nmp }) => {
      markerRef.current = new nmp.Marker()
        .setLngLat(map.getCenter())
        .addTo(map);
    });

    // ✅ moveend (نه move) + debounce
    map.on('move', () => {
      // فقط marker را همراه ببر — بدون fetch
      if (markerRef.current) markerRef.current.setLngLat(map.getCenter());
    });

    map.on('moveend', () => {
      const { lat, lng } = map.getCenter();

      // ⏱ debounce — اگر در DEBOUNCE_MS مجدد moveend بیاید، قبلی کنسل
      if (debounceTimerRef.current) clearTimeout(debounceTimerRef.current);
      debounceTimerRef.current = setTimeout(() => {
        fetchReverseGeocode(lat, lng);
      }, DEBOUNCE_MS);
    });
  }, [fetchReverseGeocode]);

  // 🔑 نکته #5: cleanup روی unmount — جلوی pending request
  useEffect(() => {
    return () => {
      abortRef.current?.abort();
      if (debounceTimerRef.current) clearTimeout(debounceTimerRef.current);
    };
  }, []);

  return (
    <div>
      <MapComponent
        style={{ width: '100%', height: 400 }}
        options={{
          mapKey: NESHAN_WEB_KEY,
          mapType: 'neshanVector',
          center: DEFAULT_CENTER,       // [lng, lat] — نه برعکس!
          zoom: 13,
          poi: true,
          traffic: false,
          isTouchPlatform: true,
        }}
        mapSetter={attachHandlers}
      />
      {loading && <p>در حال یافتن آدرس…</p>}
      {address && <p>{address.formatted_address}</p>}
    </div>
  );
}
```

---

## چک‌لیست سریع برای تیم فرانت

پیش از deploy بعدی این موارد را تأیید کنید:

- [ ] **`map.on('moveend', ...)`** استفاده شده، نه `'move'`
- [ ] **`setTimeout` debounce** حداقل ۵۰۰ms قبل از فراخوانی reverse-geocode
- [ ] **`AbortController`** درخواست قبلی را روی فراخوانی جدید cancel می‌کند
- [ ] **`map` و `marker` در `useRef`** نه `useState`
- [ ] **callback ها با `useCallback`** و dependency array درست
- [ ] **در Next.js**: `dynamic(..., { ssr: false })` برای کامپوننت نقشه
- [ ] **cleanup در `useEffect` return**: abort + clearTimeout
- [ ] **بعد از تغییر**: لاگ پروداکشن را چک کنید — `0.0X ms`های متناوب بدون حرکت کاربر **نباید** ظاهر شوند

---

## بک‌اند چه چیزی تغییر کرد

برای جلوگیری از تکرار این مشکل در آینده:

1. **Throttle سخت‌گیر شد** — از ۳۰/min به **۲۰/min** کاهش یافت. اگر فرانت bug داشت، 429 سریع‌تر بروز می‌کند.
2. **Spam detection log** — اگر همان کاربر همان مختصات را در ۵ ثانیه بیش از ۵ بار خواست، در لاگ `neshan.reverse_spam_detected` با hint اینکه «احتمالاً debounce یا effect loop ندارد» ثبت می‌شود.

```
[2026-06-11 ...] WARNING: neshan.reverse_spam_detected
  user_id: 42
  lat: 35.7219, lng: 51.3347
  count_in_5s: 5
  hint: Frontend likely missing debounce or has a state/effect loop.
```

با این لاگ، ادمین می‌تواند بدون نگاه به DevTools متوجه شود.

---

## اگر بعد از fix هنوز مشکل بود

اطلاعات زیر را برای تحلیل بفرستید:
- خروجی DevTools → Network tab → فیلتر `reverse-geocode` → screenshot timing
- آیا در dev mode هست یا production build؟ (StrictMode فقط در dev)
- آیا از `<React.StrictMode>` استفاده می‌کنید؟
- نسخه React + Next.js + پکیج `@neshan-maps-platform/mapbox-gl-react`

سؤالی بود اطلاع دهید.
