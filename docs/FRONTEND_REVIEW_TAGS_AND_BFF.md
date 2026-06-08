# Frontend Update — Review Tags + BFF Recommendation

> تاریخ: 2026-06-08
> مخاطب: تیم فرانت (Next.js / PWA / Mobile)
> دو موضوع: (۱) سیستم نظرسنجی به‌صورت تگ‌محور تغییر کرد. (۲) توصیه‌ی معماری BFF برای مخفی‌سازی بک‌اند.

---

## ۱. سیستم نظرسنجی جدید (Review Tags)

### چی عوض شد؟

قبلاً نظرسنجی فقط `rating` + `criteria` ۴تایی ثابت (`punctuality / quality / behavior / pricing`) بود.
حالا کاربر می‌تواند بعد از rating، یک‌سری **تگ نقطه قوت / نقطه ضعف** (admin-managed) انتخاب کند.

### جریان جدید UX (پیشنهادی)

```
                                                 ┌─────────────────────────────┐
[سفارش completed]  ───►  مودال نظرسنجی  ──►   │ ۱) امتیاز کلی (1..5 ستاره) │
                                                 └────────────┬────────────────┘
                                                              │
                                                              ▼
                                                ┌────────────────────────────┐
                                                │ ۲) فرانت GET reviews/tags    │
                                                │    (یک‌بار با cache 5 دقیقه)  │
                                                └────────────┬────────────────┘
                                                              │
                                                              ▼
                                       ┌──────────────────────────────────────┐
                                       │ ۳) تصمیم UX بر اساس rating          │
                                       │    1..2 → فقط cons (نقاط ضعف)        │
                                       │    3    → هر دو                       │
                                       │    4..5 → فقط pros (نقاط قوت)         │
                                       └────────────┬─────────────────────────┘
                                                    │
                                                    ▼
                              ┌─────────────────────────────────────────────┐
                              │ ۴) کاربر چند تگ (حداکثر ۸) انتخاب می‌کند    │
                              │     + متن نظر (اختیاری، max 1000 char)        │
                              └────────────┬────────────────────────────────┘
                                           │
                                           ▼
                              ┌─────────────────────────────────────────────┐
                              │ ۵) POST orders/{id}/review                   │
                              │    { rating, tag_ids[], comment }             │
                              └─────────────────────────────────────────────┘
```

> **نکته مهم سرور:** هیچ محدودیتی روی ترکیب pro/con با rating اعمال نمی‌کند.
> یعنی اگر کاربر امتیاز ۵ بدهد ولی نقطه ضعف هم انتخاب کند، سرور قبول می‌کند.
> این فقط یک قاعده UX است — می‌توانید سخت‌گیر باشید یا انعطاف بدهید.

### Endpointهای دخیل

#### `GET /v1/customer/reviews/tags`  🆕 (Public)

```jsonc
{
  "success": true,
  "data": {
    "pros": [
      { "id": 1, "slug": "pro-punctual",     "label": "وقت‌شناسی",                "icon": "clock" },
      { "id": 2, "slug": "pro-respectful",   "label": "برخورد محترمانه",           "icon": "smile" },
      { "id": 3, "slug": "pro-expert",       "label": "تخصص بالا",                 "icon": "award" },
      { "id": 4, "slug": "pro-clean",        "label": "تمیزکاری پس از کار",        "icon": "sparkles" },
      { "id": 5, "slug": "pro-fair-price",   "label": "قیمت منصفانه",              "icon": "wallet" },
      { "id": 6, "slug": "pro-clear-explain","label": "توضیح روشن فرآیند تعمیر",   "icon": "message-square" }
    ],
    "cons": [
      { "id": 7,  "slug": "con-late",         "label": "تأخیر در مراجعه",    "icon": "clock-alert" },
      { "id": 8,  "slug": "con-rude",         "label": "برخورد نامناسب",      "icon": "frown" },
      { "id": 9,  "slug": "con-low-quality",  "label": "کیفیت پایین کار",     "icon": "thumbs-down" },
      { "id": 10, "slug": "con-messy",        "label": "شلختگی محل کار",      "icon": "trash-2" },
      { "id": 11, "slug": "con-overpriced",   "label": "قیمت بالا",            "icon": "badge-dollar-sign" },
      { "id": 12, "slug": "con-poor-explain", "label": "توضیح ناکافی",        "icon": "message-square-off" }
    ]
  },
  "meta": { "total_pros": 6, "total_cons": 6 }
}
```

- **Public** است (auth لازم نیست) — `Cache-Control: public, max-age=300`
- `icon` نام آیکن lucide-react است (`<Clock />`, `<Award />` و …). اگر مطابق ندارید، fallback آزاد است.
- فقط `is_active = true` در پاسخ می‌آید.
- لیست از ادمین قابل تغییر است (`/admin/customer-app/review-tags`) — لطفاً cache کوتاه (۵ دقیقه) داشته باشید یا در splash بارگیری کنید.

#### `POST /v1/customer/orders/{id}/review`

**Request body جدید:**

```jsonc
{
  "rating": 5,                                  // اجباری، 1..5
  "tag_ids": [1, 3, 5],                         // اختیاری، حداکثر ۸ id، بدون تکرار، فقط active
  "comment": "تکنسین وقت‌شناس و کاربلد بود.",  // اختیاری، حداکثر ۱۰۰۰ کاراکتر
  "would_recommend": true                       // اختیاری
}
```

**Backward-compatible:** ارسال `criteria` قدیمی (به‌جای `tag_ids`) هنوز پشتیبانی می‌شود. ولی توصیه می‌شود به `tag_ids` مهاجرت کنید.

**Response 201:**

```jsonc
{
  "success": true,
  "message": "نظر شما ثبت شد. ممنون از همکاری.",
  "data": {
    "id": 42,
    "order_id": 1200,
    "rating": 5,
    "criteria": null,
    "comment": "...",
    "would_recommend": true,
    "status": "pending",
    "tags": [
      { "id": 1, "slug": "pro-punctual",   "label": "وقت‌شناسی",     "type": "pro", "icon": "clock" },
      { "id": 3, "slug": "pro-expert",     "label": "تخصص بالا",      "type": "pro", "icon": "award" },
      { "id": 5, "slug": "pro-fair-price", "label": "قیمت منصفانه",   "type": "pro", "icon": "wallet" }
    ],
    "submitted_at": "2026-06-08T15:00:00Z"
  }
}
```

**خطاها:**
- `403` — مالک سفارش نیست
- `409 already_reviewed` — قبلاً ثبت شده → همان نظر قبلی (با tags) برمی‌گردد، نه error
- `422 order_not_completed` — سفارش هنوز completed نیست
- `422 validation_failed` — مثلاً tag_id نامعتبر، یا بیش از ۸ تگ، یا تکراری

### کد نمونه (React/TypeScript)

```ts
// types
type ReviewTag = { id: number; slug: string; label: string; icon: string | null };
type TagsResponse = {
  data: { pros: ReviewTag[]; cons: ReviewTag[] };
  meta: { total_pros: number; total_cons: number };
};

// fetch
async function fetchReviewTags() {
  const res = await fetch(`${API_BASE}/v1/customer/reviews/tags`);
  return (await res.json()) as TagsResponse;
}

// select pool by rating
function poolForRating(rating: number, tags: TagsResponse): ReviewTag[] {
  if (rating <= 2) return tags.data.cons;
  if (rating === 3) return [...tags.data.pros, ...tags.data.cons];
  return tags.data.pros;
}

// submit
async function submitReview(orderId: number, payload: {
  rating: number;
  tag_ids: number[];
  comment?: string;
  would_recommend?: boolean;
}) {
  const res = await fetch(`${API_BASE}/v1/customer/orders/${orderId}/review`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Idempotency-Key': crypto.randomUUID(),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });
  return await res.json();
}
```

---

## ۲. توصیه‌ی معماری: استفاده از BFF

### مشکل فعلی

در حال حاضر، فرانت مستقیماً به `https://api.tamironline.com/v1/customer/*` متصل می‌شود و:

- **توکن `Bearer` در حافظه‌ی مرورگر** (localStorage / cookie) ذخیره می‌شود → آسیب‌پذیر در برابر XSS
- **URL و ساختار endpoint های واقعی بک‌اند** به‌طور کامل در DevTools دیده می‌شود
- نام domain بک‌اند برای حملات targeted (DDoS، brute-force) **public** است
- هرگونه تغییر آینده در ساختار route ها → نیاز به تغییر همزمان فرانت

### راه‌حل پیشنهادی: Backend-for-Frontend (BFF)

به‌جای call مستقیم به `api.tamironline.com`، فرانت به **API routes خود Next.js** (یا یک Node middleware جدا) call می‌کند:

```
┌─────────────────┐        ┌─────────────────────────┐        ┌──────────────────────┐
│ Browser / PWA   │ HTTPS  │ Next.js BFF Layer        │ HTTPS  │ Laravel API           │
│ (httpOnly cookie│ ─────► │ /api/customer/orders     │ ─────► │ /v1/customer/orders   │
│  session)       │        │  (proxy + token attach)  │        │  (Bearer token)        │
└─────────────────┘        └─────────────────────────┘        └──────────────────────┘
        ↑                            ↓
        │   Set-Cookie: session=<sid>; HttpOnly; Secure; SameSite=Strict
```

### مزایا

| موضوع | قبل | با BFF |
|------|-----|---------|
| ذخیره توکن | localStorage (قابل XSS) | httpOnly cookie یا Redis سرور |
| URL بک‌اند | public در DevTools | hidden — فرانت فقط `/api/*` می‌بیند |
| Rate limit | روی API مستقیم | لایه‌ی دوم در BFF + Cloudflare |
| تغییر route | همزمان فرانت + بک | فقط نگاشت BFF |
| محتوای حساس | پاسخ خام به مرورگر | امکان فیلتر در BFF |

### پیاده‌سازی پیشنهادی در Next.js (App Router)

```ts
// app/api/customer/[...path]/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';

const UPSTREAM = process.env.LARAVEL_API_BASE!; // فقط در .env سرور، نه NEXT_PUBLIC

export async function GET(req: NextRequest, { params }: { params: { path: string[] } }) {
  return proxy(req, params.path, 'GET');
}
export async function POST(req: NextRequest, { params }: { params: { path: string[] } }) {
  return proxy(req, params.path, 'POST');
}
// ... PUT/DELETE هم

async function proxy(req: NextRequest, segments: string[], method: string) {
  const session = cookies().get('session')?.value;
  const token = session ? await getTokenFromSession(session) : null;

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (req.headers.get('idempotency-key')) {
    headers['Idempotency-Key'] = req.headers.get('idempotency-key')!;
  }

  const body = ['GET', 'HEAD'].includes(method) ? undefined : await req.text();

  const upstream = await fetch(`${UPSTREAM}/v1/customer/${segments.join('/')}${req.nextUrl.search}`, {
    method, headers, body, cache: 'no-store',
  });

  const data = await upstream.text();
  const res = new NextResponse(data, { status: upstream.status });
  res.headers.set('Content-Type', upstream.headers.get('Content-Type') ?? 'application/json');

  // اگر X-Renewed-Token آمد → ذخیره در session (Redis یا DB، نه cookie مستقیم)
  const renewed = upstream.headers.get('X-Renewed-Token');
  if (renewed && session) await saveTokenForSession(session, renewed);

  return res;
}
```

### نکات

1. **توکن Bearer هرگز به مرورگر نمی‌رود.** فقط در session store سرور (Redis/DB) نگه‌داری می‌شود.
2. **cookie session** فقط یک opaque id است — httpOnly، Secure، SameSite=Strict.
3. **CSRF protection**: چون cookie داریم، باید CSRF token هم به فرانت بدهیم (مثل `_next-csrf` cookie) یا با `SameSite=Strict` و Origin check اکتفا کنید.
4. **رفرش توکن**: همان منطق فعلی `X-Renewed-Token` کار می‌کند — فقط BFF آن را در session ذخیره می‌کند نه مرورگر.
5. **Idempotency-Key**: همچنان از مرورگر می‌آید، BFF فقط pass-through می‌کند.
6. **Endpoint های Public** (status, holidays, services, reviews/tags): می‌توانند مستقیم از مرورگر call شوند (CDN) یا از BFF (یکپارچگی + cache بهتر).

### مراحل پیشنهادی مهاجرت

| مرحله | کار | اولویت |
|------|------|---------|
| ۱ | راه‌اندازی BFF skeleton در Next.js (`/api/customer/*`) | بالا |
| ۲ | انتقال auth flow (OTP, login, token storage) → session cookie | بالا |
| ۳ | انتقال سفارش‌ها، آدرس‌ها، نظرسنجی، پروفایل | متوسط |
| ۴ | جدا کردن endpoint های public (نیاز به BFF ندارند) | پایین |
| ۵ | اضافه کردن rate-limit + monitoring روی BFF | متوسط |

### چیزی که سمت بک‌اند تغییر نمی‌کند

- API contract همان است — فقط callerش عوض می‌شود.
- نام route ها، envelope، rolling token، Idempotency-Key، همه پابرجا.
- ✅ هیچ نیازی به deploy جدید بک‌اند برای فعال کردن BFF نیست.

---

## خلاصه

| موضوع | وضعیت | اقدام فرانت |
|------|---------|---------------|
| Review Tags جدید | آماده، deployable | افزودن صفحه‌ی نظرسنجی تگ‌محور با `GET reviews/tags` |
| BFF | معماری پیشنهادی | برنامه‌ریزی مهاجرت تدریجی |

سؤالی بود مطرح کنید.
