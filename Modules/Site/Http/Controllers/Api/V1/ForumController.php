<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Expert;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\Forum\Report;
use Modules\Site\Support\MediaUrl;

/**
 * API عمومی انجمن — لیست/جزئیات/ثبت سوال و پاسخ + upvote/accept + اطلاعات
 * جانبی (کارشناسان، پاسخ‌های کارشناسی، داغ‌ترین مشکلات، آمار دستگاه‌ها).
 *
 * sanitization: body سوال/پاسخ با HtmlSanitizer.
 * moderation: همه‌ی POSTها در status=pending شروع می‌شوند تا ادمین تأیید کند.
 */
class ForumController extends Controller
{
    // ─── Questions ────────────────────────────────────────────────

    /**
     * GET /v1/forum/categories
     *
     * دسته‌بندی صفحه‌ی انجمن — همگی auto-driven از CRM + شمارش انجمن.
     * شامل سه گرید:
     *   - devices: لیست دستگاه‌های فعال با questions_count و href
     *   - brands : لیست برندهای فعال با questions_count و href
     *   - combos : top N (device, brand) با بیشترین سوال
     *
     * فرانت تیتر هر گرید و show toggle را از /v1/pages/forum
     * (سکشن categories) می‌خواند. این endpoint فقط داده‌ی خام را برمی‌گرداند.
     */
    public function categories(Request $request): JsonResponse
    {
        $combosLimit = max(1, min((int) $request->query('combos_limit', 6), 30));

        // count سوالات منتشرشده گروه‌بندی شده بر اساس device_id و brand_id
        $byDevice = DB::table('site_forum_questions')
            ->selectRaw('device_id, COUNT(*) as c')
            ->where('status', Question::STATUS_APPROVED)
            ->whereNotNull('published_at')
            ->whereNotNull('device_id')
            ->groupBy('device_id')
            ->pluck('c', 'device_id');

        $byBrand = DB::table('site_forum_questions')
            ->selectRaw('brand_id, COUNT(*) as c')
            ->where('status', Question::STATUS_APPROVED)
            ->whereNotNull('published_at')
            ->whereNotNull('brand_id')
            ->groupBy('brand_id')
            ->pluck('c', 'brand_id');

        $devices = Device::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'tone', 'thumbnail'])
            ->map(fn (Device $d) => [
                'id' => (int) $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'icon' => $d->icon,
                'tone' => $d->tone,
                'thumbnail' => MediaUrl::resolve($d->thumbnail),
                'questions_count' => (int) ($byDevice[$d->id] ?? 0),
                'href' => '/forum?device='.$d->slug,
            ])
            ->values();

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo'])
            ->map(fn (Brand $b) => [
                'id' => (int) $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'logo' => MediaUrl::resolve($b->logo),
                'questions_count' => (int) ($byBrand[$b->id] ?? 0),
                'href' => '/forum?brand='.$b->slug,
            ])
            ->values();

        // ترکیبی محبوب: top N (device_id, brand_id) با بیشترین سوال
        $comboRows = DB::table('site_forum_questions')
            ->selectRaw('device_id, brand_id, COUNT(*) as c')
            ->where('status', Question::STATUS_APPROVED)
            ->whereNotNull('published_at')
            ->whereNotNull('device_id')
            ->whereNotNull('brand_id')
            ->groupBy('device_id', 'brand_id')
            ->orderByDesc('c')
            ->limit($combosLimit)
            ->get();

        $deviceLookup = Device::query()->whereIn('id', $comboRows->pluck('device_id')->unique())
            ->get(['id', 'name', 'slug', 'icon', 'thumbnail'])->keyBy('id');
        $brandLookup = Brand::query()->whereIn('id', $comboRows->pluck('brand_id')->unique())
            ->get(['id', 'name', 'slug', 'logo'])->keyBy('id');

        $combos = $comboRows->map(function ($row) use ($deviceLookup, $brandLookup) {
            $d = $deviceLookup->get($row->device_id);
            $b = $brandLookup->get($row->brand_id);
            if (! $d || ! $b) {
                return null;
            }

            return [
                'device' => [
                    'name' => $d->name, 'slug' => $d->slug,
                    'icon' => $d->icon, 'thumbnail' => MediaUrl::resolve($d->thumbnail),
                ],
                'brand' => [
                    'name' => $b->name, 'slug' => $b->slug,
                    'logo' => MediaUrl::resolve($b->logo),
                ],
                'label' => $d->name.' '.$b->name,
                'questions_count' => (int) $row->c,
                'href' => '/forum?device='.$d->slug.'&brand='.$b->slug,
            ];
        })->filter()->values();

        return response()
            ->json([
                'devices' => $devices,
                'brands' => $brands,
                'combos' => $combos,
            ])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * GET /v1/forum/questions?tab=&device=&brand=&q=&page=&limit=&sort=
     */
    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'latest');
        $page = max(1, (int) $request->query('page', 1));
        $limit = max(1, min((int) $request->query('limit', 5), 50));

        $base = Question::query()->approved()
            ->with(['device:id,name,slug', 'brand:id,name,slug']);

        $this->applyFilters($base, $request);
        $base = $this->applyTab($base, $tab);
        $this->applySort($base, $request->query('sort', 'newest'));

        $total = (clone $base)->count();
        $items = $base->forPage($page, $limit)->get();

        return response()->json([
            'data' => $items->map(fn (Question $q) => $this->shapeListItem($q))->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $total > 0 ? (int) ceil($total / $limit) : 1,
                'tab_counts' => $this->tabCounts($request),
            ],
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /**
     * GET /v1/forum/questions/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $question = Question::query()->approved()
            ->where('slug', $slug)
            ->with(['device:id,name,slug', 'brand:id,name,slug', 'approvedAnswers.expert'])
            ->first();

        if (! $question) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $question->incrementQuietly('view_count');

        $similar = Question::query()->approved()
            ->where('id', '!=', $question->id)
            ->where(function ($q) use ($question) {
                if ($question->device_id) {
                    $q->where('device_id', $question->device_id);
                }
            })
            ->orderByDesc('upvotes_count')->orderByDesc('answers_count')
            ->limit(5)
            ->with(['device:id,name,slug'])
            ->get();

        return response()->json(array_merge($this->shapeListItem($question), [
            'body' => $question->body,
            'meta_title' => $question->title,
            'meta_description' => Str::limit(strip_tags($question->body), 160),
            'answers' => $question->approvedAnswers
                ->sortByDesc(fn (Answer $a) => $a->is_accepted ? PHP_INT_MAX : $a->upvotes_count)
                ->values()
                ->map(fn (Answer $a) => $this->shapeAnswer($a))
                ->all(),
            'similar_questions' => $similar->map(fn (Question $q) => $this->shapeListItem($q))->values(),
        ]))->header('Cache-Control', 'public, max-age=120, s-maxage=120');
    }

    /**
     * POST /v1/forum/questions
     */
    public function store(Request $request): JsonResponse
    {
        // از این پس فقط مشتری authenticated می‌تواند سوال ثبت کند.
        // (Sanctum middleware در routes جداگانه auth الزامی می‌کند.)
        $customer = $request->user();
        if (! $customer instanceof \Modules\CRM\Models\Customer) {
            return response()->json(['ok' => false, 'message' => 'برای ثبت سوال باید وارد شوید.'], 401);
        }
        if (empty($customer->last_name)) {
            return response()->json([
                'ok' => false,
                'message' => 'ابتدا نام خانوادگی خود را در پروفایل تکمیل کنید.',
                'needs_profile' => true,
            ], 422);
        }

        // banlist check
        if (\Modules\Site\Models\Forum\BanlistEntry::isBanned($request->ip(), $customer->email, $customer->mobile)) {
            return response()->json(['ok' => false, 'message' => 'دسترسی شما مسدود شده است.'], 403);
        }

        $data = $request->validate([
            'title' => 'required|string|min:10|max:250',
            'body' => 'required|string|min:30|max:50000',
            'device_slug' => 'nullable|string|exists:crm_devices,slug',
            'brand_slug' => 'nullable|string|exists:crm_brands,slug',
            'model' => 'nullable|string|max:80',
            'tags' => 'nullable|array|max:6',
            'tags.*' => 'string|max:30',
        ]);

        $device = ! empty($data['device_slug']) ? Device::where('slug', $data['device_slug'])->first(['id']) : null;
        $brand = ! empty($data['brand_slug']) ? Brand::where('slug', $data['brand_slug'])->first(['id']) : null;
        $token = Str::random(40);

        // الگوی {id}-{persian-slug} — insert با slug موقت، سپس update با id واقعی
        $question = Question::create([
            'slug' => 'tmp-'.bin2hex(random_bytes(6)),
            'title' => trim($data['title']),
            'body' => HtmlSanitizer::clean($data['body']) ?? trim($data['body']),
            'model' => $data['model'] ?? null,
            'tags' => $data['tags'] ?? null,
            'device_id' => $device?->id,
            'brand_id' => $brand?->id,
            'customer_id' => $customer->id,
            'author_name' => $customer->full_name,
            'author_email' => $customer->email,
            'author_phone' => $customer->mobile,
            'author_token' => $token,
            'status' => Question::STATUS_PENDING,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
        $question->slug = self::buildQuestionSlug((int) $question->id, $data['title']);
        $question->save();

        return response()->json([
            'id' => (int) $question->id,
            'slug' => $question->slug,
            'status' => $question->status,
            'author_token' => $token, // فقط همینجا برمی‌گردد — برای accept پاسخ
            'message' => 'سوال شما دریافت شد و پس از تأیید نمایش داده می‌شود.',
        ], 201);
    }

    // ─── Answers ──────────────────────────────────────────────────

    /**
     * POST /v1/forum/questions/{slug}/answers
     */
    public function storeAnswer(Request $request, string $slug): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof \Modules\CRM\Models\Customer) {
            return response()->json(['ok' => false, 'message' => 'برای ثبت پاسخ باید وارد شوید.'], 401);
        }
        if (empty($customer->last_name)) {
            return response()->json([
                'ok' => false,
                'message' => 'ابتدا نام خانوادگی خود را در پروفایل تکمیل کنید.',
                'needs_profile' => true,
            ], 422);
        }

        if (\Modules\Site\Models\Forum\BanlistEntry::isBanned($request->ip(), $customer->email, $customer->mobile)) {
            return response()->json(['ok' => false, 'message' => 'دسترسی شما مسدود شده است.'], 403);
        }

        $question = Question::query()->approved()->where('slug', $slug)->first(['id']);
        if (! $question) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $data = $request->validate([
            'body' => 'required|string|min:30|max:50000',
        ]);

        $answer = Answer::create([
            'question_id' => $question->id,
            'body' => HtmlSanitizer::clean($data['body']) ?? trim($data['body']),
            'customer_id' => $customer->id,
            'author_name' => $customer->full_name,
            'author_email' => $customer->email,
            'author_phone' => $customer->mobile,
            'status' => Answer::STATUS_PENDING,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'id' => (int) $answer->id,
            'status' => $answer->status,
            'message' => 'پاسخ شما دریافت شد و پس از تأیید نمایش داده می‌شود.',
        ], 201);
    }

    /**
     * POST /v1/forum/answers/{id}/upvote
     */
    public function upvoteAnswer(Request $request, int $id): JsonResponse
    {
        $answer = Answer::query()->approved()->find($id);
        if (! $answer) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        $this->incrementUpvote('site_forum_answer_upvotes', 'answer_id', $answer, $request->ip());

        return response()->json(['upvotes' => (int) $answer->upvotes_count]);
    }

    /**
     * POST /v1/forum/questions/{id}/upvote
     */
    public function upvoteQuestion(Request $request, int $id): JsonResponse
    {
        $question = Question::query()->approved()->find($id);
        if (! $question) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        $this->incrementUpvote('site_forum_question_upvotes', 'question_id', $question, $request->ip());

        return response()->json(['upvotes' => (int) $question->upvotes_count]);
    }

    /**
     * POST /v1/forum/answers/{id}/accept
     * Header: X-Author-Token = توکن صاحب سوال (از response POST سوال)
     */
    public function acceptAnswer(Request $request, int $id): JsonResponse
    {
        $answer = Answer::query()->approved()->find($id);
        if (! $answer) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $question = $answer->question;
        $token = $request->header('X-Author-Token', $request->input('author_token'));
        if (! $token || ! hash_equals((string) $question->author_token, (string) $token)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($question, $answer) {
            Answer::where('question_id', $question->id)->update(['is_accepted' => false, 'accepted_at' => null]);
            $answer->update(['is_accepted' => true, 'accepted_at' => now()]);
            $question->recomputeResolution();
        });

        return response()->json(['ok' => true, 'is_accepted' => true]);
    }

    // ─── Insights (هیولا برای صفحه‌ی index) ────────────────────────

    /**
     * GET /v1/forum/experts?limit=
     */
    public function experts(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 8), 50));
        $rows = Expert::query()->active()->ranked()->limit($limit)
            ->get(['id', 'slug', 'name', 'title', 'avatar', 'rating', 'answers_count']);

        return response()->json([
            'data' => $rows->map(fn (Expert $e) => [
                'id' => (int) $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'title' => $e->title,
                'avatar' => MediaUrl::resolve($e->avatar),
                'rating' => (float) $e->rating,
                'answers_count' => (int) $e->answers_count,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/forum/expert-answers?limit=
     */
    public function expertAnswers(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 6), 30));
        $rows = Answer::query()->approved()
            ->where('is_expert_reply', true)
            ->with(['question:id,slug,title', 'expert:id,slug,name,title,avatar'])
            ->latest('approved_at')->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Answer $a) => [
                'question' => $a->question ? [
                    'id' => (int) $a->question->id,
                    'slug' => $a->question->slug,
                    'title' => $a->question->title,
                ] : null,
                'expert' => $a->expert ? [
                    'id' => (int) $a->expert->id,
                    'slug' => $a->expert->slug,
                    'name' => $a->expert->name,
                    'title' => $a->expert->title,
                    'avatar' => MediaUrl::resolve($a->expert->avatar),
                ] : ['name' => $a->author_name],
                'published_at' => ($a->approved_at ?? $a->created_at)?->utc()->toIso8601ZuluString(),
                'badge' => 'پاسخ کارشناسی',
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * GET /v1/forum/hot-problems?limit=
     * تاپ‌ترین (پر-سوال‌ترین) موضوعات.
     */
    public function hotProblems(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 10), 30));

        $rows = Question::query()->approved()->where('is_hot', true)
            ->orderByDesc('view_count')->orderByDesc('upvotes_count')
            ->limit($limit)
            ->with(['device:id,name,slug'])
            ->get(['id', 'slug', 'title', 'device_id', 'view_count']);

        $data = [];
        $rank = 1;
        foreach ($rows as $q) {
            $data[] = [
                'rank' => $rank++,
                'title' => $q->title,
                'question_count' => (int) $q->view_count,
                'device' => $q->device ? ['slug' => $q->device->slug, 'name' => $q->device->name] : null,
                'slug' => $q->slug,
            ];
        }

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/forum/device-stats — تعداد سوالات تأییدشده‌ی هر دستگاه.
     */
    public function deviceStats(): JsonResponse
    {
        $rows = DB::table('crm_devices')
            ->leftJoin('site_forum_questions', function ($j) {
                $j->on('site_forum_questions.device_id', '=', 'crm_devices.id')
                    ->where('site_forum_questions.status', Question::STATUS_APPROVED);
            })
            ->where('crm_devices.is_active', true)
            ->groupBy('crm_devices.id', 'crm_devices.name', 'crm_devices.slug')
            ->orderByRaw('COUNT(site_forum_questions.id) DESC')
            ->get([
                'crm_devices.id',
                'crm_devices.name',
                'crm_devices.slug',
                DB::raw('COUNT(site_forum_questions.id) as question_count'),
            ]);

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'device' => ['slug' => $r->slug, 'name' => $r->name],
                'question_count' => (int) $r->question_count,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function applyFilters($query, Request $request): void
    {
        if ($deviceSlug = trim((string) $request->query('device', ''))) {
            $query->whereHas('device', fn ($q) => $q->where('slug', $deviceSlug));
        }
        if ($brandSlug = trim((string) $request->query('brand', ''))) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%"));
        }
    }

    private function applyTab($query, string $tab)
    {
        return match ($tab) {
            'hot' => $query->where('is_hot', true),
            'unanswered' => $query->where('resolution_status', Question::RES_UNANSWERED),
            'expert' => $query->whereIn('resolution_status', [Question::RES_EXPERT_ANSWERED, Question::RES_SOLVED]),
            'featured' => $query->where('is_featured', true),
            default => $query,
        };
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'popular' => $query->orderByDesc('view_count')->orderByDesc('published_at'),
            'most-answered' => $query->orderByDesc('answers_count')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };
    }

    /**
     * @return array<string, int>
     */
    private function tabCounts(Request $request): array
    {
        $build = function () use ($request) {
            $q = Question::query()->approved();
            $this->applyFilters($q, $request);

            return $q;
        };

        return [
            'latest' => $build()->count(),
            'hot' => $build()->where('is_hot', true)->count(),
            'unanswered' => $build()->where('resolution_status', Question::RES_UNANSWERED)->count(),
            'expert' => $build()->whereIn('resolution_status', [Question::RES_EXPERT_ANSWERED, Question::RES_SOLVED])->count(),
            'featured' => $build()->where('is_featured', true)->count(),
        ];
    }

    /**
     * تبدیل عنوان به slug با حفظ حروف فارسی (Persian-preserving).
     * فقط letters/digits/فاصله/dash نگه‌داری می‌شوند؛ بقیه‌ی نقطه‌گذاری
     * حذف، فاصله‌ها → dash، و حداکثر طول ۱۵۰ کاراکتر.
     */
    public static function persianSlug(string $title): string
    {
        $title = strip_tags($title);
        // ZWNJ (U+200C) و ZWJ (U+200D) → dash قبل از حذف non-letters
        // تا مرز کلمات «لباسشویی‌ام» در slug حفظ شود.
        $title = preg_replace('/[\x{200C}\x{200D}]/u', '-', $title);
        // فقط Unicode letters, digits, whitespace و dash بمانند
        $title = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $title);
        $title = preg_replace('/[\s_]+/u', '-', $title);
        $title = preg_replace('/-+/u', '-', $title);
        $title = trim((string) $title, '-');

        return mb_substr($title, 0, 150);
    }

    /**
     * ساخت slug نهایی به فرمت {id}-{persianSlug}. اگر عنوان slug-able تولید
     * نکند، فقط `{id}` برمی‌گرداند (مثلاً عنوانی که فقط نقطه‌گذاری دارد).
     */
    public static function buildQuestionSlug(int $id, string $title): string
    {
        $slug = self::persianSlug($title);

        return $slug === '' ? (string) $id : $id.'-'.$slug;
    }

    private function incrementUpvote(string $table, string $column, $owner, string $ip): void
    {
        try {
            DB::table($table)->insert([
                $column => $owner->id,
                'ip' => $ip,
                'created_at' => now(),
            ]);
            $owner->increment('upvotes_count');
            $owner->refresh();
        } catch (\Illuminate\Database\QueryException $e) {
            // duplicate — already voted
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeListItem(Question $q): array
    {
        return [
            'id' => (int) $q->id,
            'slug' => $q->slug,
            'href' => '/forum/'.$q->slug,
            'title' => $q->title,
            'device' => $q->device ? ['slug' => $q->device->slug, 'name' => $q->device->name] : null,
            'brand' => $q->brand ? ['slug' => $q->brand->slug, 'name' => $q->brand->name] : null,
            'model' => $q->model,
            'status' => $q->resolution_status,
            'answer_count' => (int) $q->answers_count,
            'view_count' => (int) $q->view_count,
            'upvotes' => (int) $q->upvotes_count,
            'is_hot' => (bool) $q->is_hot,
            'is_featured' => (bool) $q->is_featured,
            'tags' => $q->tags ?? [],
            'author' => ['name' => $q->author_name, 'avatar' => null],
            'published_at' => $q->published_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeAnswer(Answer $a): array
    {
        $expert = $a->expert;

        return [
            'id' => (int) $a->id,
            'body' => $a->body,
            'author' => $expert ? [
                'name' => $expert->name,
                'avatar' => MediaUrl::resolve($expert->avatar),
                'is_expert' => true,
                'expert_title' => $expert->title,
                'expert_rating' => (float) $expert->rating,
            ] : [
                'name' => $a->author_name,
                'avatar' => null,
                'is_expert' => (bool) $a->is_expert_reply,
            ],
            'upvotes' => (int) $a->upvotes_count,
            'is_accepted' => (bool) $a->is_accepted,
            'published_at' => ($a->approved_at ?? $a->created_at)?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * POST /v1/forum/report — ثبت گزارش روی یک سوال یا پاسخ.
     *
     * Body:
     *   target_type: 'question' | 'answer'
     *   target_id:   int
     *   reason:      spam | inappropriate | wrong-info | duplicate | other
     *   notes:       string|null (اختیاری)
     *   reporter_name / reporter_email: اختیاری
     *
     * Rate-limit: ۵ گزارش از یک IP در دقیقه (در routes.php).
     */
    public function storeReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => 'required|in:question,answer',
            'target_id' => 'required|integer|min:1',
            'reason' => 'required|in:'.implode(',', array_keys(Report::REASONS)),
            'notes' => 'nullable|string|max:1000',
            'reporter_name' => 'nullable|string|max:80',
            'reporter_email' => 'nullable|email|max:150',
        ]);

        // اطمینان از وجود target
        $exists = $data['target_type'] === 'question'
            ? Question::where('id', $data['target_id'])->exists()
            : Answer::where('id', $data['target_id'])->exists();

        if (! $exists) {
            return response()->json(['ok' => false, 'message' => 'Target not found'], 404);
        }

        // جلوگیری از duplicate همان IP روی همان target در ۲۴ ساعت
        $duplicate = Report::query()
            ->where('reportable_type', $data['target_type'])
            ->where('reportable_id', $data['target_id'])
            ->where('reporter_ip', $request->ip())
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($duplicate) {
            return response()->json(['ok' => true, 'message' => 'Already reported'], 200);
        }

        Report::create([
            'reportable_type' => $data['target_type'],
            'reportable_id' => $data['target_id'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_email' => $data['reporter_email'] ?? null,
            'reporter_ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return response()->json(['ok' => true, 'message' => 'گزارش شما ثبت شد. تیم مدیریت بررسی می‌کند.']);
    }
}
