# مستند فرانت — انجمن پرسش و پاسخ (`/forum`)

> سه صفحه: لیست (`/forum`)، جزئیات سوال (`/forum/{slug}`)، ثبت سوال (`/forum/ask`).
> Backend کامل پیاده‌سازی شده — تمام endpointهای زیر فعالند.

---

## ۱) خلاصه‌ی endpointها

| داده | Endpoint | Method | Auth/Throttle | Cache |
|---|---|---|---|---|
| Page chrome `/forum` | `/v1/pages/forum` | GET | Public | s-maxage=300 |
| لیست سوالات + tab counts | `/v1/forum/questions?tab=&device=&brand=&q=&page=&limit=&sort=` | GET | 60/min | s-maxage=60 |
| جزئیات سوال | `/v1/forum/questions/{slug}` | GET | 60/min | s-maxage=120 |
| ثبت سوال جدید | `/v1/forum/questions` | POST | 3 در 10 دقیقه | — |
| ثبت پاسخ | `/v1/forum/questions/{slug}/answers` | POST | 5/min | — |
| upvote پاسخ | `/v1/forum/answers/{id}/upvote` | POST | 30/min | — |
| upvote سوال | `/v1/forum/questions/{id}/upvote` | POST | 30/min | — |
| accept پاسخ (header: `X-Author-Token`) | `/v1/forum/answers/{id}/accept` | POST | 30/min | — |
| کارشناسان برتر | `/v1/forum/experts?limit=` | GET | 60/min | s-maxage=600 |
| پاسخ‌های کارشناسی اخیر | `/v1/forum/expert-answers?limit=` | GET | 60/min | s-maxage=300 |
| داغ‌ترین مشکلات | `/v1/forum/hot-problems?limit=` | GET | 60/min | s-maxage=600 |
| آمار سوال هر دستگاه | `/v1/forum/device-stats` | GET | 60/min | s-maxage=600 |

---

## ۲) `GET /v1/pages/forum`

```jsonc
{
  "slug": "forum",
  "sections": {
    "hero": {
      "badge": "انجمن پرسش و پاسخ",
      "title": "سوال داری؟ از کارشناس‌های ما بپرس",
      "highlight": "کارشناس‌های ما",
      "subtitle": "...",
      "popular_searches_items": [
        { "text": "ارور F04 پکیج", "url": "/forum?q=ارور+F04" }
      ]
    },
    "sections_visibility": {
      "show_device_picker": true, "show_questions_list": true,
      "show_hot_problems": true, "show_app_promo": true,
      "show_top_experts": true, "show_category_shortcuts": true,
      "show_expert_answers": true, "show_final_cta": true
    },
    "experts_section": { "title": "کارشناسان برتر", "subtitle": "..." },
    "expert_answers_section": { "title": "آخرین پاسخ‌های کارشناسی", "subtitle": "..." },
    "category_shortcuts": {
      "title": "دسته‌بندی موضوعی",
      "items": [
        { "slug": "errors", "label": "ارورها و کدخطاها", "description": "...",
          "icon": "alert-circle", "tone": "rose", "href": "/forum?tag=errors" }
      ]
    },
    "final_cta": {
      "title": "...",
      "primary": { "label": "ثبت سوال جدید", "url": "/forum/ask" },
      "secondary": { "label": "تماس", "url": "tel:..." }
    },
    "sidebar_banners": { "items": [
      { "id": "warranty", "category": "warranty", "title": "...", "description": "...",
        "cta_label": "...", "cta_url": "...", "icon": "shield", "tone": "green" }
    ]},
    "seo": { "meta_title": "...", "meta_description": "..." }
  }
}
```

---

## ۳) `GET /v1/forum/questions`

پارامترها:
- `tab=latest|hot|unanswered|expert|featured`
- `device={slug}`, `brand={slug}`, `q={text}`
- `page=N&limit=M` (پیش‌فرض 5)
- `sort=newest|oldest|popular|most-answered`

```jsonc
{
  "data": [
    {
      "id": 12,
      "slug": "samsung-ww80-not-turning-on",
      "href": "/forum/samsung-ww80-not-turning-on",
      "title": "...",
      "device": { "slug": "washing-machine", "name": "ماشین لباسشویی" },
      "brand":  { "slug": "samsung", "name": "سامسونگ" },
      "model": "WW80",
      "status": "expert-answered",   // unanswered|answered|expert-answered|solved
      "answer_count": 3,
      "view_count": 1240,
      "upvotes": 12,
      "is_hot": true,
      "is_featured": false,
      "tags": ["لباسشویی", "سامسونگ"],
      "author": { "name": "علی", "avatar": null },
      "published_at": "2026-05-21T10:30:00Z"
    }
  ],
  "meta": {
    "total": 487, "page": 1, "limit": 5, "last_page": 98,
    "tab_counts": {
      "latest": 487, "hot": 42, "unanswered": 65, "expert": 180, "featured": 23
    }
  }
}
```

---

## ۴) `GET /v1/forum/questions/{slug}`

```jsonc
{
  "id": 12, "slug": "...", "href": "/forum/...",
  "title": "...", "body": "<p>...</p>",
  "device": {...}, "brand": {...}, "model": "...",
  "status": "expert-answered",
  "answer_count": 3, "view_count": 1240, "upvotes": 12,
  "is_hot": true, "is_featured": false,
  "tags": [...],
  "author": {...},
  "published_at": "...",
  "meta_title": "...",
  "meta_description": "...",
  "answers": [
    {
      "id": 101,
      "body": "<p>...</p>",
      "author": {
        "name": "علی محمدی",
        "avatar": null,
        "is_expert": true,
        "expert_title": "کارشناس لباسشویی سامسونگ",
        "expert_rating": 4.9
      },
      "upvotes": 18,
      "is_accepted": true,
      "published_at": "..."
    }
  ],
  "similar_questions": [/* up to 5 شیپ list-item */]
}
```

**نکات:** `view_count` با هر GET افزایش می‌یابد (quiet — بدون touch timestamp). `body` پاک‌سازی‌شده HTML است (با `dangerouslySetInnerHTML` رندر کنید).

---

## ۵) POST `/v1/forum/questions`

```http
POST /v1/forum/questions
Content-Type: application/json

{
  "title": "...",                    // 10–250
  "body": "...",                     // 30–50000 (HTML/markdown — backend sanitize می‌کند)
  "device_slug": "washing-machine",  // required
  "brand_slug": "samsung",           // optional
  "model": "WW80",
  "tags": ["لباسشویی", "سامسونگ"],   // ≤6
  "author_name": "علی",
  "author_email": "ali@example.com"
}
```

**پاسخ 201:**
```jsonc
{
  "id": 530,
  "slug": "530-علت-روشن-نشدن-ماشین-لباسشویی-سامسونگ",
  "status": "pending",
  "author_token": "abc123...",       // ⚠ فقط همین‌جا برمی‌گردد — برای accept پاسخ ذخیره کنید
  "message": "سوال شما دریافت شد و پس از تأیید نمایش داده می‌شود."
}
```

**فرمت slug:** `{id}-{persian-slug}` — مثلاً `530-علت-روشن-نشدن-ماشین-لباسشویی-سامسونگ`. الگوی StackOverflow:
- ID همیشه prefix است (collision-free + lookup سریع)
- بقیه از عنوان با حفظ حروف فارسی (مرز کلمات با dash جدا)
- URL در browser به‌صورت فارسی نمایش داده می‌شود؛ هنگام کپی، encode می‌شود
- اگر slug را خراب کپی کنند، می‌توانید با parse کردن `^(\d+)` در فرانت ID را استخراج کنید و یک GET به آن بزنید

**ذخیره‌ی author_token:** در `localStorage` با key `forum_token:{slug}` برای استفاده در accept پاسخ.

---

## ۶) POST `/v1/forum/questions/{slug}/answers`

```http
POST /v1/forum/questions/samsung-ww80-not-turning-on/answers
Content-Type: application/json

{ "body": "...", "author_name": "...", "author_email": "..." }
```

**پاسخ 201:** `{ id, status: "pending", message }`

---

## ۷) Upvote و Accept

### POST `/v1/forum/answers/{id}/upvote`
```jsonc
// Response
{ "upvotes": 19 }
```
- یک‌طرفه + unique روی `(answer_id, ip)`. duplicate به‌صورت silent ignore می‌شود.
- در localStorage فلگ بگذارید تا UI دوباره enable نشود.

### POST `/v1/forum/questions/{id}/upvote`
شکل مشابه.

### POST `/v1/forum/answers/{id}/accept`
```http
POST /v1/forum/answers/101/accept
X-Author-Token: abc123...
```
- فقط صاحب سوال (با توکن دریافتی هنگام POST سوال) می‌تواند.
- `is_accepted=true` می‌شود، بقیه پاسخ‌های همان سوال به false برمی‌گردند.
- `resolution_status` سوال خودکار به `solved` تغییر می‌کند.

---

## ۸) `GET /v1/forum/experts`, `/expert-answers`, `/hot-problems`, `/device-stats`

### `/v1/forum/experts?limit=8`
```jsonc
{ "data": [ { "id":1, "slug":"ali-mohammadi", "name":"علی محمدی",
  "title":"کارشناس لباسشویی", "avatar": null, "rating": 4.9, "answers_count": 1250 } ] }
```

### `/v1/forum/expert-answers?limit=6`
```jsonc
{ "data": [ { "question": { "id":12, "slug":"...", "title":"..." },
  "expert": { "id":1, "name":"علی محمدی", "title":"...", "avatar": null },
  "published_at": "...", "badge": "پاسخ کارشناسی" } ] }
```

### `/v1/forum/hot-problems?limit=10`
```jsonc
{ "data": [ { "rank":1, "title":"...", "question_count":188,
  "device": { "slug":"washing-machine", "name":"ماشین لباسشویی" },
  "slug": "..." } ] }
```

### `/v1/forum/device-stats`
```jsonc
{ "data": [ { "device": { "slug":"washing-machine", "name":"ماشین لباسشویی" },
  "question_count": 1240 } ] }
```

---

## ۹) TypeScript types

```ts
export type ResolutionStatus = "unanswered" | "answered" | "expert-answered" | "solved";

export interface ForumDeviceRef { slug: string; name: string; }
export interface ForumBrandRef  { slug: string; name: string; }

export interface ForumQuestionListItem {
  id: number; slug: string; href: string; title: string;
  device: ForumDeviceRef | null; brand: ForumBrandRef | null;
  model: string | null;
  status: ResolutionStatus;
  answer_count: number; view_count: number; upvotes: number;
  is_hot: boolean; is_featured: boolean;
  tags: string[];
  author: { name: string; avatar: string | null };
  published_at: string | null;
}

export interface ForumAnswer {
  id: number; body: string;
  author: {
    name: string; avatar: string | null; is_expert?: boolean;
    expert_title?: string; expert_rating?: number;
  };
  upvotes: number; is_accepted: boolean;
  published_at: string;
}

export interface ForumQuestionDetail extends ForumQuestionListItem {
  body: string; meta_title: string | null; meta_description: string | null;
  answers: ForumAnswer[];
  similar_questions: ForumQuestionListItem[];
}

export interface ForumListResponse {
  data: ForumQuestionListItem[];
  meta: {
    total: number; page: number; limit: number; last_page: number;
    tab_counts: Record<"latest"|"hot"|"unanswered"|"expert"|"featured", number>;
  };
}

export interface ForumStoreQuestionPayload {
  title: string; body: string;
  device_slug: string; brand_slug?: string; model?: string;
  tags?: string[]; author_name: string; author_email?: string;
}

export interface ForumStoreQuestionResponse {
  id: number; slug: string; status: "pending";
  author_token: string;   // ذخیره در localStorage
  message: string;
}
```

---

## ۱۰) نمونه‌ی `app/forum/page.tsx`

```tsx
export const revalidate = 60;

export default async function ForumIndexPage({
  searchParams,
}: { searchParams: { tab?: string; q?: string; device?: string; brand?: string; page?: string } }) {
  const tab = searchParams.tab ?? 'latest';
  const filters = new URLSearchParams({
    tab, page: searchParams.page ?? '1', limit: '5',
    ...(searchParams.q ? { q: searchParams.q } : {}),
    ...(searchParams.device ? { device: searchParams.device } : {}),
    ...(searchParams.brand ? { brand: searchParams.brand } : {}),
  });

  const [page, questions, deviceStats, experts, expertAnswers, hotProblems] = await Promise.all([
    fetchJson<ForumPageResponse>('/v1/pages/forum'),
    fetchJson<ForumListResponse>(`/v1/forum/questions?${filters}`),
    fetchJson<DeviceStatsResponse>('/v1/forum/device-stats'),
    fetchJson<ExpertsResponse>('/v1/forum/experts'),
    fetchJson<ExpertAnswersResponse>('/v1/forum/expert-answers'),
    fetchJson<HotProblemsResponse>('/v1/forum/hot-problems'),
  ]);

  const v = page.sections.sections_visibility ?? {};

  return (
    <>
      <SEO title={page.sections.seo?.meta_title} description={page.sections.seo?.meta_description} />
      <ForumHero {...page.sections.hero} />
      {v.show_device_picker !== false && <DevicePicker stats={deviceStats.data} />}
      {v.show_questions_list !== false && (
        <QuestionsList items={questions.data} meta={questions.meta} activeTab={tab} />
      )}
      {v.show_hot_problems !== false && <HotProblems items={hotProblems.data} />}
      {v.show_top_experts !== false && (
        <TopExperts title={page.sections.experts_section?.title} items={experts.data} />
      )}
      {v.show_category_shortcuts !== false && <CategoryShortcuts {...page.sections.category_shortcuts} />}
      {v.show_expert_answers !== false && (
        <ExpertAnswers title={page.sections.expert_answers_section?.title} items={expertAnswers.data} />
      )}
      {v.show_final_cta !== false && page.sections.final_cta && (
        <FinalCta {...page.sections.final_cta} />
      )}
    </>
  );
}
```

---

## ۱۱) `app/forum/[slug]/page.tsx`

```tsx
export const revalidate = 120;

export default async function QuestionPage({ params }: { params: { slug: string } }) {
  const [detail, pageData] = await Promise.all([
    fetchJson<ForumQuestionDetail | {message:string}>(`/v1/forum/questions/${params.slug}`),
    fetchJson<ForumPageResponse>('/v1/pages/forum'),
  ]);
  if ('message' in detail) notFound();

  const banners = pageData.sections.sidebar_banners?.items ?? [];

  return (
    <div className="grid lg:grid-cols-3 gap-6">
      <article className="lg:col-span-2">
        <QuestionHeader {...detail} />
        <QuestionBody body={detail.body} upvotes={detail.upvotes} questionId={detail.id} />
        <AnswersList answers={detail.answers} questionSlug={detail.slug} />
        <AnswerForm slug={detail.slug} />
      </article>
      <aside>
        <SimilarQuestionsCard items={detail.similar_questions} />
        {banners.map(b => <SidebarBannerCard key={b.id} {...b} />)}
      </aside>
    </div>
  );
}
```

---

## ۱۲) `app/forum/ask/page.tsx` — UI ساخت سوال

فیلدها مطابق ساختار POST. بعد از submit موفق:

```tsx
const res = await fetch('/v1/forum/questions', { method: 'POST', body: JSON.stringify(payload), headers: {'Content-Type': 'application/json'} });
const data: ForumStoreQuestionResponse = await res.json();

// ذخیره‌ی توکن برای accept پاسخ
localStorage.setItem(`forum_token:${data.slug}`, data.author_token);

// redirect به /forum با پیام موفقیت
router.push('/forum?submitted=1');
```

---

## ۱۳) مدیریت در پنل ادمین

| امکانات | کجا |
|---|---|
| محتوای صفحه (hero/visibility/banners/cta) | `/admin/site/page-content` → «صفحه‌ی انجمن (/forum)» |
| moderation سوالات + پاسخ‌ها | `/admin/site/forum/questions` |
| پاسخ کارشناسی به‌نام یک کارشناس | صفحه‌ی detail سوال → فرم پایین |
| CRUD کارشناسان | `/admin/site/forum/experts` |
| flag‌های hot / featured | per-row یا bulk |
| تغییر وضعیت bulk (approve/reject/spam/delete) | چک‌باکس + نوار سبز بالا |

**Permissions:**
- `view-forum` (مشاهده)
- `manage-forum-questions` (تأیید/رد/spam/feature/hot/پاسخ کارشناسی)
- `manage-forum-experts` (CRUD کارشناسان)

---

## ۱۴) Migrationهای لازم برای deploy

```
2026_05_23_130_create_forum_tables
2026_05_23_131_add_forum_permissions
```

---

## ۱۵) چک‌لیست انطباق فرانت

- [ ] ۶ fetch موازی برای index (pages/forum + questions + device-stats + experts + expert-answers + hot-problems)
- [ ] `sections_visibility.show_*` چک شود
- [ ] tab badge count از `meta.tab_counts`
- [ ] `body` سوال و پاسخ‌ها با `dangerouslySetInnerHTML` (sanitize شده)
- [ ] localStorage: `forum_token:{slug}` بعد از submit سوال (برای accept)
- [ ] localStorage: `forum_upvote:answer:{id}` تا upvote دوباره نشود
- [ ] tag chip روی Hero از `popular_searches_items` (نه از فیکسچر)
