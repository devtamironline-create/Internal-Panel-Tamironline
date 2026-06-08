# 📋 لیست اقدامات تیم فرانت — اپ مشتری

> این داک خلاصه‌ی **فقط کارهایی که فرانت باید انجام دهد** بعد از تست QA است.
> برای spec کامل API، `docs/FRONTEND_HANDOFF.md` را ببینید.
> برای جزئیات هر مشکل، `docs/FRONTEND_FEEDBACK_AFTER_QA.md` را ببینید.

**اولویت‌بندی:**
- 🔴 **مسدودکننده‌ی release** — قبل از build بعدی
- 🟡 **مهم برای production**
- 🟢 **بهبود UX**

---

## 🔴 ۱) Idempotency-Key روی هر POST/PUT/DELETE

### چرا

در تست شما، ۲ خطای سرور رخ داد و اپ ۱۰ بار retry زد → **۲۰ سفارش duplicate در DB**. این یعنی idempotency استفاده نمی‌شود.

### کاری که باید انجام دهید

```ts
// helper
function generateIdempotencyKey(): string {
  return crypto.randomUUID();
}

// در هر submit فرم:
const key = generateIdempotencyKey();  // یک بار ساخته می‌شود
// retry های شبکه‌ای از همان key استفاده می‌کنند، تا فرم بعدی

await fetch(url, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Idempotency-Key': key,           // ← روی همه‌ی نوشتارها
    'X-Device-ID': deviceId,
  },
  body: JSON.stringify(payload),
});
```

**روی این endpoint ها اجباری:**
- `POST /v1/customer/orders`
- `POST /v1/customer/orders/{id}/cancel`
- `POST /v1/customer/orders/{id}/review`
- `POST /v1/customer/addresses`
- `PUT /v1/customer/addresses/{id}`
- `DELETE /v1/customer/addresses/{id}`
- `PUT /v1/customer/profile`

**قاعده:** یک UUID per "intent" کاربر. اگر فرانت auto-retry می‌کند، **همان** UUID. وقتی response 2xx یا 4xx پایدار آمد → UUID مصرف شد، intent بعدی UUID جدید.

---

## 🔴 ۲) سیاست retry صحیح

### چرا

۱۰ بار retry روی یک خطا = ۱۰ تماس API. اگر هر کدام به side effect بخورد = ۱۰ duplicate.

### کاری که باید انجام دهید

```ts
async function retryableFetch(url: string, init: RequestInit, attempts = 3) {
  const delays = [500, 1500, 4000];  // ms
  for (let i = 0; i < attempts; i++) {
    const r = await fetch(url, init);

    // موفقیت — خروج
    if (r.ok) return r;

    // 4xx — هرگز retry، خطای کاربر است
    if (r.status >= 400 && r.status < 500) return r;

    // 5xx یا network error — backoff
    if (i < attempts - 1) await sleep(delays[i]);
  }
  return null;  // بعد از 3 retry هم نشد
}
```

**قواعد:**
- **حداکثر ۳ retry** (نه ۱۰، نه ۵)
- **فقط روی 5xx یا network error**
- **هرگز روی 4xx** (422, 409, 403, 401, ...)
- backoff: 500ms → 1.5s → 4s
- بعد از ۳ شکست: toast «خطای سرور، لطفاً بعداً تلاش کنید»

---

## 🔴 ۳) فرم آدرس — کاربر باید استان و شهر را انتخاب کند

### چرا

در تست شما کاربر آدرس بدون استان/شهر ساخت → سفارش هم بدون استان/شهر شد → پنل ادمین "—" نشان داد → **تکنسین نمی‌داند به کجا برود**.

### کاری که باید انجام دهید

```
فرم آدرس:
┌─────────────────────────────────┐
│ استان: [▼ Picker] *            │  ← required
│ شهر:   [▼ Picker] *            │  ← required (disabled تا استان انتخاب شود)
│ متن آدرس کامل:                  │  ← required (min 10 char)
│ [................................]
│ [................................]
│ کد پستی: [....] (اختیاری)      │
│ تلفن ثابت: [....] (اختیاری)    │
│ برچسب: [▼ خانه/کار/دیگر]      │
│ ☐ آدرس پیش‌فرض من باشد         │
│              [ذخیره]            │
└─────────────────────────────────┘
```

**Flow:**

```ts
// 1) لیست استان‌ها (cache 1h)
const states = await api('/v1/customer/locations/states');

// 2) وقتی کاربر استان را انتخاب کرد:
const cities = await api(`/v1/customer/locations/cities?state_id=${stateId}`);
// شهرها را در picker شهر بریز، picker را enable کن

// 3) submit
await api('/v1/customer/addresses', {
  method: 'POST',
  headers: { 'Idempotency-Key': uuid(), ... },
  body: JSON.stringify({
    province_id: 1,         // ← اجباری در سرور
    city_id: 11,            // ← اجباری در سرور
    full_address: 'تهران، آزادی، پلاک ۱۰',
    postal_code: '1234567890',  // اختیاری
    is_default: true,
  }),
});
```

اگر بدون province_id/city_id بفرستید: `422 validation_failed`.

---

## 🔴 ۴) handle کردن `code: "address_incomplete"`

### چرا

کاربر آدرس قدیمی (قبل از required شدن province/city) دارد. وقتی سفارش با آن می‌سازد، سرور 422 می‌دهد.

### پاسخ سرور

```jsonc
// POST /v1/customer/orders → 422
{
  "success": false,
  "message": "این آدرس کامل نیست. لطفاً ابتدا استان و شهر آدرس را در پروفایل تکمیل کنید.",
  "code": "address_incomplete",
  "data": {
    "error_code": "address_incomplete",
    "address_id": 1,
    "errors": { "address_id": ["استان یا شهر این آدرس ست نشده است."] }
  }
}
```

### کاری که باید انجام دهید

```ts
if (response.code === 'address_incomplete') {
  // CTA: «آدرس شما کامل نیست، تکمیل کنید»
  showModal({
    title: 'تکمیل آدرس',
    message: 'برای ثبت سفارش باید استان و شهر آدرس را مشخص کنید.',
    cta: {
      label: 'تکمیل آدرس',
      action: () => navigate(`/addresses/${response.data.address_id}/edit`),
    },
  });
  return;
}
```

---

## 🟡 ۵) فرم سفارش — فیلدهای حداقلی

### payload استاندارد

```jsonc
{
  "order_type": "repair",           // از /services/types
  "device_id": 2,                   // از /services/categories
  "brand_id": 4,                    // از /services/brands?category_id=2 (اختیاری)
  "objection_ids": [1, 5],          // از /services/objections?device_id=2 (حداکثر ۱۰)
  "problem_description": "...",     // اختیاری ولی توصیه‌شده — متن آزاد کاربر
  "scheduled_date": "1405-03-19",   // شمسی یا میلادی، تشخیص خودکار
  "scheduled_slot": "09-12",        // یکی از 4 مقدار
  "address_id": 1                   // از /addresses (که province/city دارد)
}
```

`problem_title` لازم نیست بفرستید — سرور از objection ها derive می‌کند.

### Validation روی فرم (قبل از POST)

- `device_id` و `brand_id` و `address_id` همه از picker → ID معتبر
- `objection_ids` حداقل ۱ ایراد (UX) ولی سرور 0 هم می‌پذیرد
- `scheduled_date` نباید قبل از امروز باشد
- `address` باید کامل باشد (province + city دارد) — یا فرانت قبل از submit چک کند و گزینه‌های ناقص را علامت بزند

---

## 🟡 ۶) Handle کردن همه‌ی error codes

### جدول کامل

```ts
type ErrorCode =
  | 'unauthenticated'        // 401 → logout + login
  | 'forbidden'              // 403 → "دسترسی غیرمجاز"
  | 'not_found'              // 404 → "یافت نشد" یا back
  | 'cannot_cancel'          // 409 → دکمه‌ی لغو غیرفعال
  | 'pending_review_required'// 409 → مودال اجباری نظرسنجی
  | 'already_reviewed'       // 409 → نظر قبلی در data.data
  | 'address_incomplete'     // 422 → redirect به edit address
  | 'order_not_completed'    // 422 → سفارش هنوز انجام نشده
  | 'validation_failed'      // 422 → field errors
  | 'rate_limited'           // 429 → toast + backoff
  | 'service_unavailable'    // 503 → overlay maintenance
  | 'upgrade_required'       // 426 → overlay اجباری به‌روزرسانی
  | 'server_error';          // 5xx → toast + retry محدود

function handleError(response: ApiError) {
  switch (response.code) {
    case 'unauthenticated':
      clearAuth();
      navigate('/auth/login');
      return;
    case 'address_incomplete':
      navigate(`/addresses/${response.data.address_id}/edit`);
      return;
    case 'pending_review_required':
      openReviewModal(response.data.pending_order_id);
      return;
    case 'already_reviewed':
      showReview(response.data.data);
      return;
    case 'cannot_cancel':
      disableCancelButton();
      toast(response.message);
      return;
    case 'validation_failed':
      setFormErrors(response.data.errors);
      return;
    case 'rate_limited':
      toast('لحظه‌ای صبر کنید', 'warn');
      return;
    case 'service_unavailable':
      showMaintenanceOverlay();
      return;
    default:
      toast(response.message || 'خطایی رخ داد', 'error');
  }
}
```

---

## 🟡 ۷) X-Device-ID پایدار

### چرا

برای endpoint `GET /v1/customer/auth/devices` لازم است — کاربر باید بتواند دستگاه‌های login خود را ببیند.

### کاری که باید انجام دهید

```ts
// در ابتدای اپ، یک بار:
async function getDeviceId(): Promise<string> {
  let id = await secureStorage.get('device_id');
  if (!id) {
    id = crypto.randomUUID();
    await secureStorage.set('device_id', id);
  }
  return id;
}

// در http client:
const headers = {
  'X-Device-ID': await getDeviceId(),
  ...
};
```

**نکته:** PWA → `localStorage` (به‌جای secureStorage). React Native → `react-native-keychain`. Flutter → `flutter_secure_storage`.

---

## 🟡 ۸) polling سفارش فعال — از `/version` نه GET کامل

### چرا

GET کامل سفارش حجیم است. polling هر ۲۰ ثانیه = bandwidth زیاد.

### کاری که باید انجام دهید

```ts
// در صفحه‌ی جزئیات سفارش (وقتی terminal نیست):
useEffect(() => {
  if (order.is_terminal) return;

  let lastHash = order.versionHash;
  const id = setInterval(async () => {
    const v = await api(`/v1/customer/orders/${order.id}/version`);
    if (v.data.hash !== lastHash) {
      // تغییر کرده، حالا GET کامل
      const fresh = await api(`/v1/customer/orders/${order.id}`);
      setOrder(fresh.data);
      lastHash = fresh.data.versionHash;
    }
  }, 20000); // 20s

  return () => clearInterval(id);
}, [order.id, order.is_terminal]);
```

---

## 🟡 ۹) نظرسنجی اجباری — modal close-able نباشد

### چرا

طبق spec کسب‌وکار، کاربر باید قبل از ثبت سفارش بعدی نظر دهد.

### کاری که باید انجام دهید

```ts
// در splash یا بعد از login:
const pending = await api('/v1/customer/orders/pending-reviews');
if (pending.data.length > 0) {
  // modal با dismissable=false
  showReviewModal({
    order: pending.data[0],
    onSubmit: async (rating, criteria, comment) => {
      await api(`/v1/customer/orders/${pending.data[0].order_id}/review`, {
        method: 'POST',
        headers: { 'Idempotency-Key': uuid() },
        body: JSON.stringify({ rating, criteria, comment, would_recommend: true }),
      });
      // بعد از submit، modal بسته می‌شود
    },
  });
}
```

اگر کاربر بخواهد سفارش جدید بدهد بدون نظر، سرور 409 `pending_review_required` می‌دهد و فرانت همان modal را نمایش می‌دهد.

---

## 🟡 ۱۰) Status و Bootstrap در splash

```ts
// در splash app:
const [status, bootstrap] = await Promise.all([
  api('/v1/customer/status'),
  api('/v1/customer/bootstrap', { headers: { 'If-None-Match': cachedETag } }),
]);

// اقدامات بر اساس status:
if (status.data.app_disabled) {
  showOverlay('سرویس در دسترس نیست');
  return;
}
if (status.data.maintenance.active) {
  showMaintenanceModal(status.data.maintenance.message);
}
if (compareVersion(APP_VERSION, status.data.min_version) < 0) {
  showForcedUpdateOverlay();
  return;
}
if (status.data.test_mode_active) {
  showTestModeBanner('🧪 حالت تست — OTP واقعی نیست');
}

// bootstrap (cancel reasons، time slots، service types، holidays، banners)
cacheBootstrap(bootstrap.data);
```

---

## 🟢 ۱۱) Toast/Loading در همه‌ی نوشتارها

### چرا

اگر کاربر دکمه‌ی submit را زد و response هنوز نیامده، نباید دوباره کلیک کند (حتی با idempotency key، UX بد است).

### کاری که باید انجام دهید

```ts
// در submit handler:
setLoading(true);
try {
  const r = await api('/v1/customer/orders', { ... });
  if (r.success) {
    toast('سفارش ثبت شد ✓', 'success');
    navigate(`/orders/${r.data.id}`);
  } else {
    handleError(r);
  }
} finally {
  setLoading(false);
}

// در JSX:
<button disabled={loading} onClick={handleSubmit}>
  {loading ? 'در حال ثبت...' : 'ثبت سفارش'}
</button>
```

---

## 🟢 ۱۲) date_picker شمسی

سرور هر دو فرمت شمسی و میلادی را قبول می‌کند، ولی برای UX کاربر ایرانی، **شمسی** استفاده کنید:

```ts
// از یک date picker شمسی (مثل react-multi-date-picker با persian-calendar)
<DatePicker
  calendar={persian}
  locale={persian_fa}
  value={value}
  onChange={(date) => {
    // YYYY-MM-DD شمسی
    setScheduledDate(date.format('YYYY-MM-DD'));
  }}
  minDate={new DateObject({ calendar: persian })}
/>
```

سرور خودش شمسی → میلادی تبدیل می‌کند.

---

## 📋 چک‌لیست نهایی قبل از release

- [ ] `Idempotency-Key` در همه‌ی POST/PUT/DELETE
- [ ] retry فقط روی 5xx، حداکثر ۳ بار با exponential backoff
- [ ] فرم آدرس: استان + شهر + متن همگی required در UI
- [ ] handle کردن `code: "address_incomplete"` با redirect
- [ ] handle کردن `code: "pending_review_required"` با modal اجباری
- [ ] error handler مرکزی برای همه‌ی error codes
- [ ] `X-Device-ID` پایدار در secure storage
- [ ] polling فقط با `/version`
- [ ] disable کردن submit button در loading
- [ ] date picker شمسی
- [ ] splash: status + bootstrap + pending-reviews
- [ ] overlay برای app_disabled / maintenance / upgrade_required
- [ ] yellow banner اگر `test_mode_active`

---

## سؤال؟ تماس با تیم بک‌اند

برای هر ابهام، در daily standup یا روی thread پروژه بپرسید. تمام endpointها در `docs/FRONTEND_HANDOFF.md` با نمونه‌ی request/response هستند.

با تشکر — تیم بک‌اند 🙏
