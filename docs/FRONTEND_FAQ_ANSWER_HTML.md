# Frontend — فیلدِ `answer` در FAQ حالا **HTML** است

## مشکل فعلی
الان روی سایت، پاسخِ سوالات این‌طور دیده می‌شود:

```
<p>هزینه تعمیرات <strong>لباسشویی سامسونگ</strong> بر اساس نوع دستگاه ...</p>
```

یعنی تگ‌های HTML به‌صورتِ **متنِ خام** چاپ می‌شوند. علتش این است که فرانت
هنوز `answer` را مثلِ متنِ ساده (`{faq.answer}` یا با `white-space: pre-line`)
نمایش می‌دهد، در حالی که از این به بعد **`answer` خودش HTML است** (مدیر در پنل
یک ادیتورِ کامل دارد: لینک، لیست، جدول، پررنگ، رنگ، عنوان…).

راه‌حل: `answer` را **به‌صورتِ HTML رندر کنید**، نه متن.

---

## چه چیزی عوض شد (بک‌اند)
- فیلدِ `faq.items[].answer` (و همان فیلد در `faq.categories[].items[].answer`)
  حالا یک **رشتهٔ HTML** است، نه متنِ ساده.
- این HTML در بک‌اند با یک **سَنیتایزر** پاک‌سازی می‌شود (تگ/اتریبیوت/پروتکلِ
  خطرناک مثلِ `<script>` یا `javascript:` حذف می‌شود). پس برای رندر **امن** است.
- placeholderها (`{device}`، `{brand}`، …) مثلِ قبل قبل از ارسال جایگزین شده‌اند.
- ساختار/نامِ فیلدها تغییری نکرده — فقط **محتوای** `answer` از متن به HTML تبدیل شده.

> توجه: ممکن است سوالاتِ قدیمی هنوز متنِ ساده (بدونِ تگ) باشند. رندرِ HTML برای
> آن‌ها هم درست کار می‌کند (متنِ بدونِ تگ همان‌طور نمایش داده می‌شود). فقط اگر
> قبلاً روی `pre-line` حساب می‌کردید، newlineهای ساده دیگر به خط جدید تبدیل
> نمی‌شوند — ولی پاسخ‌های جدید پاراگراف/لیستِ واقعی دارند، پس مشکلی نیست.

---

## پیاده‌سازی

### React / Next.js
```tsx
<div
  className="faq-answer prose prose-sm max-w-none"
  dangerouslySetInnerHTML={{ __html: faq.answer }}
/>
```

- چون بک‌اند از قبل sanitize کرده، `dangerouslySetInnerHTML` اینجا امن است.
- اگر می‌خواهید لایهٔ دوم محافظت داشته باشید (best practice)، می‌توانید سمتِ
  کلاینت هم با **DOMPurify** یک بار تمیز کنید — اختیاری است:
  ```tsx
  import DOMPurify from 'isomorphic-dompurify';
  <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(faq.answer) }} />
  ```

### Vue
```vue
<div class="faq-answer" v-html="faq.answer" />
```

> ❌ کاری که **نباید** بکنید:
> ```tsx
> <p>{faq.answer}</p>                 // تگ‌ها را خام چاپ می‌کند (مشکلِ فعلی)
> <p style="white-space: pre-line">…  // دیگر لازم نیست
> ```

---

## استایل‌دهی (مهم)
چون پاسخ حالا تگ‌های واقعی دارد (`<p>`, `<ul>`, `<ol>`, `<li>`, `<a>`,
`<strong>`, `<em>`, `<h2>/<h3>`, `<table>`, `<blockquote>` …)، یک **استایلِ
پایه** برای این تگ‌ها لازم است وگرنه لیست‌ها بدونِ بولت و لینک‌ها بی‌رنگ دیده می‌شوند.

ساده‌ترین راه با Tailwind Typography:
```tsx
<div className="prose prose-sm max-w-none prose-a:text-blue-600" dir="rtl"
     dangerouslySetInnerHTML={{ __html: faq.answer }} />
```

یا CSS دستی:
```css
.faq-answer { line-height: 1.9; }
.faq-answer p { margin: 0 0 .75rem; }
.faq-answer ul { list-style: disc; padding-inline-start: 1.5rem; }
.faq-answer ol { list-style: decimal; padding-inline-start: 1.5rem; }
.faq-answer a { color: #2563eb; text-decoration: underline; }
.faq-answer h2, .faq-answer h3 { font-weight: 700; margin: 1rem 0 .5rem; }
.faq-answer table { width: 100%; border-collapse: collapse; }
.faq-answer td, .faq-answer th { border: 1px solid #e5e7eb; padding: .4rem .6rem; }
```

نکات RTL:
- container را `dir="rtl"` بگذارید.
- لینک‌ها از پنل ممکن است `target="_blank"` داشته باشند؛ بک‌اند `rel` امن
  می‌گذارد، ولی شما هم می‌توانید برای اطمینان `rel="noopener"` تزریق کنید.

---

## SEO (اختیاری ولی توصیه‌شده)
اگر FAQ را به‌صورتِ JSON-LD (`FAQPage` schema) هم خروجی می‌دهید، آنجا باید
**متنِ خام بدونِ تگ** بگذارید (نه HTML). تگ‌ها را حذف کنید:
```ts
const plain = faq.answer.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
```
از `plain` در `acceptedAnswer.text` استفاده کنید، ولی در UI همان `faq.answer`
(HTML) را رندر کنید.

---

## کجاها اعمال شود
هرجا `faq.items[].answer` را نمایش می‌دهید — هر سه endpointِ صفحه:
- صفحهٔ دستگاه (`device`)
- صفحهٔ برند (`brand`)
- صفحهٔ ترکیبیِ device×brand

و چه از `faq.items` (لیستِ مسطح) بخوانید چه از `faq.categories[].items` (تب‌ها)،
فیلدِ `answer` در همه یکسان و HTML است.

---

## چک‌لیست
- [ ] `answer` با `dangerouslySetInnerHTML` / `v-html` رندر شود (نه `{answer}`).
- [ ] استایلِ پایه برای `p/ul/ol/a/strong/h2/h3/table` اضافه شود (`prose` یا CSS).
- [ ] container `dir="rtl"`.
- [ ] (اختیاری) DOMPurify سمتِ کلاینت به‌عنوانِ لایهٔ دوم.
- [ ] (اختیاری) در JSON-LD از نسخهٔ بدونِ تگ استفاده شود.
