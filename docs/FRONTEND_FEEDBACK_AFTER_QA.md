# فیدبک تیم بک‌اند به فرانت — نکات حیاتی پس از تست اولیه

> **تاریخ:** 1405/03/18
> **بازنگری از:** نتایج تست POST /v1/customer/orders اولین کاربر QA
> **اولویت:** 🔴 بالا — قبل از release اپ به production

این داک نکات قطعی است که در تست اول مشخص شد. لطفاً همه را قبل از merge اپ ببندید.

---

## 1. 🔴 [حیاتی] استفاده از `Idempotency-Key` روی هر POST

### مشکل دیده‌شده

در یک سشن تست، **۲ خطای بک‌اند** رخ داد. اپ برای هر خطا **۱۰ بار retry** کرد. نتیجه: **۲۰ سفارش تکراری** در DB.

### چرا اپ نباید بدون idempotency retry کند

POST سفارش side-effect دارد (سفارش، رکورد مالی، notification به تکنسین). retry کور = سفارش‌های موازی.

### راه‌حل قطعی

```ts
// قبل از هر POST orders (یا cancel، یا review، یا addresses store/update)
const idempotencyKey = crypto.randomUUID();

const response = await fetch(`${API}/v1/customer/orders`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Idempotency-Key': idempotencyKey,    // ← همین UUID در همه retry ها
    'X-Device-ID': deviceId,
  },
  body: JSON.stringify(payload),
});
```

**قاعده‌ی طلایی**: اگر همان کاربر همان فرم را submit می‌کند، **همان** UUID استفاده شود. وقتی response 2xx رسید → UUID جدید برای submit بعدی.

اگر فرانت Idempotency-Key نفرستد، بک‌اند اجرا می‌کند (هیچ خطایی نمی‌دهد) — این فقط امنیت اضافه برای retryهاست.

### Backoff strategy

به‌جای retry فوری ۱۰بار، از exponential backoff با cap استفاده کنید:

```ts
const delays = [500, 1500, 4000];  // ms
for (const [attempt, delay] of delays.entries()) {
  const r = await postOrder(payload, idempotencyKey);
  if (r.ok) return r;
  if (r.status >= 400 && r.status < 500) return r;  // 4xx رو retry نکنید
  if (attempt < delays.length - 1) await sleep(delay);
}
// اگر همه‌ی retry ها شکست خورد → پیام «خطای سرور — لطفاً بعداً تلاش کنید»
```

برای **5xx فقط** retry کنید (سرور دارد بازیابی می‌شود). برای **4xx هرگز** retry نکنید (خطای ورودی است، با همان داده پاسخ تغییر نخواهد کرد).

---

## 2. 🔴 [حیاتی] فیلدهای address را کامل بفرستید

### مشکل دیده‌شده

کاربر تست آدرسی ساخت با فقط `full_address: "تهران ادرس"` — استان/شهر **خالی**. وقتی سفارش با این `address_id` ثبت شد، در پنل ادمین:

```
محل مراجعه
استان / شهر:   —    ← هیچی
نشانی کامل:    تهران ادرس
```

تکنسین نمی‌داند به کجا برود.

### راه‌حل (سرور الان validation اضافه کرد)

POST/PUT روی `/v1/customer/addresses` اکنون این فیلدها را **اجباری** می‌پذیرد:

| فیلد | الزامی؟ | منبع |
|---|---|---|
| `province_id` | ✅ اجباری | از `GET /v1/customer/locations/states` |
| `city_id` | ✅ اجباری (باید متعلق به province باشد) | از `GET /v1/customer/locations/cities?state_id=N` |
| `full_address` | ✅ اجباری (حداقل ۱۰ کاراکتر) | متن آزاد کاربر |
| `postal_code` | اختیاری (دقیقاً ۱۰ رقم اگر دادید) | متن آزاد |
| `phone` | اختیاری | تلفن ثابت محل |
| `label` | اختیاری | «خانه» / «محل کار» |
| `is_default` | اختیاری | boolean |

اگر بدون آن‌ها بفرستید: `422 validation_failed` با `errors.province_id`/`errors.city_id`.

### UX پیشنهادی فرم آدرس

```
[Picker استان]  ← required
[Picker شهر]    ← required (disabled تا استان انتخاب شود؛ سپس
                  GET /locations/cities?state_id=N برای پر کردن گزینه‌ها)
[textarea آدرس کامل]  ← required
[input کدپستی]   ← optional
[input تلفن ثابت] ← optional
[input برچسب: خانه / محل کار / دیگر] ← optional
[checkbox: آدرس پیش‌فرض من باشد] ← optional
```

---

## 3. 🟡 [مهم] فیلدهای فرم ثبت سفارش که حتماً باید پر شوند

### وضعیت فعلی

نمونه‌ی POST کاربر تست:
```jsonc
{
  "order_type": "repair",
  "device_id": 2,
  "brand_id": 4,
  "objection_ids": [2],
  "problem_description": "دلو نمی‌چرخد",
  "scheduled_date": "1405-03-18",
  "scheduled_slot": "12-15",
  "address_id": 1
}
```

`problem_title` نفرستاده شده → در پنل ادمین «—» نمایش داده می‌شد.

### راه‌حل سرور

اگر فرانت `problem_title` نفرستد، سرور خودش از نام ۳ ایراد اول انتخابی derive می‌کند (مثلاً «دلو نمی‌چرخد، صدای غیرعادی»). پس مشکل خاصی برای فرانت نیست، **ولی** اگر فرانت بخواهد عنوان دقیق‌تر بفرستد، می‌تواند:

```jsonc
{
  "order_type": "repair",
  "device_id": 2,
  "brand_id": 4,
  "objection_ids": [2],
  "problem_title": "ماشین لباسشویی - دلو نمی‌چرخد",   // ← اختیاری ولی توصیه‌شده
  "problem_description": "صدا می‌دهد ولی دلو حرکت نمی‌کند",
  "scheduled_date": "1405-03-18",
  "scheduled_slot": "12-15",
  "address_id": 1,
  "introduction": "از طریق دوست معرفی شدم"            // ← اختیاری، نحوه‌ی آشنایی
}
```

### چک‌لیست فرم ثبت سفارش

- [ ] **order_type** (repair/service/install) — از `/services/types`
- [ ] **device_id** — از `/services/categories`
- [ ] **brand_id** — از `/services/brands?category_id=N` (اختیاری ولی توصیه‌شده)
- [ ] **objection_ids[]** — از `/services/objections?device_id=N` (حداکثر ۱۰)
- [ ] **problem_description** — متن آزاد کاربر (حداکثر ۲۰۰۰ کاراکتر)
- [ ] **problem_title** — اختیاری، اگر کاربر summary خاصی دارد
- [ ] **scheduled_date** — شمسی یا میلادی (تشخیص خودکار سرور)
- [ ] **scheduled_slot** — یکی از `09-12 | 12-15 | 15-18 | 18-21`
- [ ] **address_id** — از `/addresses` (که حالا province/city اجباری دارد)
- [ ] **introduction** — اختیاری، «از کجا با ما آشنا شدی؟»

---

## 4. 🟡 [مهم] پاسخ‌های ۵xx و رفتار اپ

### قانون پاسخ‌های سرور

| HTTP | معنی برای اپ | اقدام صحیح |
|---|---|---|
| `200/201` | موفق | UI به‌روز شود |
| `400/422` | ورودی غلط | پیام به کاربر، **هرگز retry نکنید** |
| `401` | توکن نامعتبر | logout + redirect login |
| `403` | کاربر دیگر / دسترسی نیست | پیام «دسترسی غیرمجاز» |
| `404` | منبع نیست | پیام «یافت نشد» |
| `409 pending_review_required` | نظر معوق دارد | modal اجباری نظرسنجی |
| `409 cannot_cancel` | لغو ممکن نیست | دکمه‌ی لغو غیرفعال |
| `409 already_reviewed` | نظر قبلاً ثبت شده | data.data نظر قبلی را نمایش بده |
| `429` | rate-limit | toast «لحظه‌ای صبر کنید» + backoff |
| `503` | سرور maintenance | overlay «در دسترس نیست» |
| `5xx دیگر` | خطای داخلی | retry با backoff (حداکثر ۳ بار، نه ۱۰!) |

### نکته‌ی مهم درباره‌ی Idempotency-Key

با وجود ست‌شدن آن، اگر سرور **قبل از commit** خطا داد (5xx)، چیزی در DB نمی‌ماند ولی idempotency key هم cache نمی‌شود — این طبیعی است. retry بعدی (با همان key) از نو اجرا می‌شود.

اگر سرور **بعد از commit** خطا داد (نباید رخ دهد ولی اگر داد)، key هم cache نمی‌شود → ⚠️ retry می‌تواند duplicate بسازد. ما تراکنش جامع روی سرور گذاشتیم تا این حالت رخ ندهد، ولی فرانت هم با backoff محدود (۳ بار حداکثر) لایه‌ی دفاع دوم را اضافه کند.

---

## 5. 🟢 [توصیه] نمایش وضعیت سفارش

سرور `status`, `status_int`, `status_label`, `is_terminal` می‌دهد.

```ts
// در UI:
if (order.is_terminal) {
  // سفارش بسته شد — دکمه‌های action مخفی
} else {
  switch (order.status) {
    case 'pending':     /* «در انتظار هماهنگی» */ break;
    case 'assigned':    /* «تکنسین تعیین شد» */ break;
    case 'scheduled':   /* «زمان مراجعه ست شد» */ break;
    case 'in_progress': /* «تکنسین در راه / حین کار» */ break;
    case 'suspended':   /* «معلق» */ break;
    case 'completed':   /* «انجام شده» + دکمه نظرسنجی اگر review.required */ break;
    case 'cancelled':   /* «لغو شده» */ break;
    case 'declined':    /* «رد شده» */ break;
  }
}

// قابلیت لغو فقط در این سه وضعیت:
const canCancel = ['pending', 'assigned', 'scheduled'].includes(order.status);
```

---

## 6. 🟢 [توصیه] polling سفارش فعال

برای صفحه‌ی جزئیات سفارش که هنوز terminal نیست:

```ts
useEffect(() => {
  if (order.is_terminal) return;
  const id = setInterval(async () => {
    const v = await fetch(`/v1/customer/orders/${order.id}/version`);
    const json = await v.json();
    // فقط اگر hash فرق کرد، GET کامل بزن
    if (json.data.hash !== order.versionHash) {
      const fresh = await fetch(`/v1/customer/orders/${order.id}`);
      // ...
    }
  }, 20000); // هر 20 ثانیه
  return () => clearInterval(id);
}, [order]);
```

**نکن:** هر ۲۰ ثانیه GET کامل بزنی (response سنگین). از `/version` استفاده کن.

---

## 7. 🟢 [توصیه] X-Device-ID پایدار

`X-Device-ID` باید per-install پایدار باشد — نه هر launch تغییر کند:

```ts
// PWA
async function deviceId(): Promise<string> {
  let id = await storage.get('device_id');
  if (!id) {
    id = crypto.randomUUID();
    await storage.set('device_id', id);
  }
  return id;
}
```

این به سرور اجازه می‌دهد لیست «دستگاه‌های فعال من» در `/v1/customer/auth/devices` نمایش دهد و کاربر بتواند دستگاه‌های قدیمی را revoke کند.

---

## 8. خلاصه‌ی اقدامات قبل از release

### تیم فرانت

- [ ] استفاده از `Idempotency-Key: <uuid>` در **همه‌ی POST/PUT/DELETE**
- [ ] backoff محدود (حداکثر ۳ بار با ۵۰۰ms→۱.۵s→۴s) **فقط برای 5xx**
- [ ] **هرگز** retry روی 4xx
- [ ] فرم آدرس: استان + شهر + متن آدرس همه required در UI
- [ ] فرم سفارش: `problem_description` required در UI، `problem_title` می‌تواند اختیاری بماند چون سرور derive می‌کند
- [ ] X-Device-ID پایدار در secure storage
- [ ] polling با endpoint `/version`، نه GET کامل

### تیم بک‌اند (انجام‌شده)

- [x] transaction جامع روی POST orders (همه چیز inside, response outside)
- [x] auto-derive `problem_title` از objection ها اگر فرانت نفرستد
- [x] required کردن `province_id` + `city_id` روی POST/PUT addresses
- [x] cross-check: city.province_id باید با province_id submit شده match کند

---

با تشکر — تیم بک‌اند 🙏
