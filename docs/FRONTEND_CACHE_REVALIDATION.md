# نامهٔ فرانت — پاک‌سازی/Revalidate کش سایت (On-Demand)

سلام تیم فرانت 👋

برای اینکه تغییراتِ پنل (برند، دستگاه، صفحاتِ ترکیبی، سئو، تنظیمات، FAQ و …)
**بدونِ انتظارِ ISR و بدونِ دیپلوی** سریع روی سایت دیده شوند، پنل یک
**webhookِ پاک‌سازی کش** صدا می‌زند. شما باید یک endpoint در Next پیاده کنید که
این درخواست را گرفته و `revalidatePath` / `revalidateTag` را اجرا کند.

> پنل: **سایت → پاک‌سازی کش سایت** — مدیر `URL` و `Secret` را ست می‌کند و می‌تواند
> کش را دستی (کامل یا مسیرهای خاص) پاک کند. تغییرات مهم هم خودکار این webhook را
> صدا می‌زنند.

---

## قراردادِ Webhook (که پنل صدا می‌زند)

```
POST  https://<your-site>/api/revalidate
Header:  x-revalidate-secret: <همان secretی که در پنل ست می‌شود>
Content-Type: application/json

Body:
{
  "paths": ["/services/lg", "/services/washing-machine/lg"],   // مسیرهای مشخص
  "tags":  ["brand:lg"],                                        // تگ‌ها (اختیاری)
  "all":   false                                               // اگر true → کلِ سایت
}
```
پاسخِ مورد انتظار: `200` با بدنهٔ `{ "revalidated": true }`. هر خطا → `4xx/5xx`.

---

## پیاده‌سازیِ پیشنهادی در Next (App Router)

```ts
// app/api/revalidate/route.ts
import { NextRequest, NextResponse } from "next/server";
import { revalidatePath, revalidateTag } from "next/cache";

export async function POST(req: NextRequest) {
  // ۱) احراز با secret
  const secret = req.headers.get("x-revalidate-secret");
  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ message: "unauthorized" }, { status: 401 });
  }

  const { paths = [], tags = [], all = false } = await req.json().catch(() => ({}));

  try {
    if (all) {
      // پاک‌سازی کامل: ساده‌ترین راه، revalidate لِی‌اوتِ ریشه
      revalidatePath("/", "layout");
    } else {
      for (const p of paths) if (typeof p === "string" && p.startsWith("/")) revalidatePath(p);
      for (const t of tags)  if (typeof t === "string" && t) revalidateTag(t);
    }
    return NextResponse.json({ revalidated: true });
  } catch (e) {
    return NextResponse.json({ revalidated: false, error: String(e) }, { status: 500 });
  }
}
```
- متغیر محیطی `REVALIDATE_SECRET` را برابرِ همان مقداری بگذارید که در پنل ست می‌شود.
- این endpoint **عمومی نیست**؛ فقط با secret کار می‌کند.

---

## استراتژیِ Tag (اختیاری ولی حرفه‌ای)

اگر از `fetch(..., { next: { tags: [...] } })` استفاده کنید، پاک‌سازیِ هدفمند خیلی
تمیزتر می‌شود. پیشنهاد برای تگ‌گذاریِ دادهٔ کاتالوگ/سئو:

| داده | tag پیشنهادی |
|------|--------------|
| لیست برندها | `brands` |
| یک برند | `brand:{slug}` |
| لیست دستگاه‌ها | `devices` |
| یک دستگاه | `device:{slug}` |
| صفحهٔ ترکیبی | `combo:{device}:{brand}` |
| تنظیمات سراسری | `site-settings` |
| متای سئوی یک صفحه | `seo:{type}:{slug}` |

مثال:
```ts
await fetch(`${BACKEND}/v1/catalog/brands/${slug}`, { next: { tags: [`brand:${slug}`, "brands"] } });
```
آنگاه پنل با فرستادنِ `"tags": ["brand:lg"]` فقط همان داده را تازه می‌کند.

> اگر فعلاً tag پیاده نمی‌کنید، اشکالی ندارد؛ پنل از `paths` (و `all`) استفاده می‌کند
> و `revalidatePath` کافی است.

---

## چه زمانی پنل صدا می‌زند؟
- **دستی:** مدیر از صفحهٔ «پاک‌سازی کش سایت» (کامل یا مسیرهای خاص).
- **خودکار:** هنگام فعال/غیرفعال‌سازی یا ویرایشِ برند/دستگاه/صفحهٔ ترکیبی و تغییرِ
  تنظیمات سراسری — پنل مسیرهای متأثر را `revalidate` می‌کند (fire-and-forget؛
  اگر endpoint نباشد، بی‌صدا رد می‌شود).

## کارهای فرانت ✅
- [ ] ساختِ `app/api/revalidate/route.ts` طبق بالا.
- [ ] تنظیمِ `REVALIDATE_SECRET` در env (همتای مقدارِ پنل).
- [ ] (اختیاری) tag-گذاریِ fetchها طبق جدول برای پاک‌سازیِ هدفمند.
- [ ] اعلام به ما تا مدیر `URL` (`https://<site>/api/revalidate`) و `Secret` را در پنل ست کند و «تست اتصال» بزند.

سؤالی بود بپرسید 🙏
