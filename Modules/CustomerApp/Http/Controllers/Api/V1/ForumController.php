<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\CRM\Models\Customer;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Comment as SiteComment;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;

/**
 * انجمن برای اپِ مشتری (PWA) — adapter روی مدلِ موجودِ انجمنِ سایت.
 *
 * نگاشتِ قرارداد:
 *   Topic   = سوالِ «منتشرشده»ی انجمن (site_forum_questions)
 *   Comment = پاسخ/دیدگاهِ داخلِ آن (site_forum_answers) — flat؛ replies=[]
 *   author.is_staff = is_expert_reply (پاسخِ AI/ادمین/کارشناس)
 *
 * حریمِ خصوصی (سمتِ سرور): pending/rejected فقط برای صاحبش (is_mine) برمی‌گردد.
 * «سوالاتِ من» = ادغامِ سوال‌های انجمن + دیدگاه‌های انجمن + کامنت‌های مقالاتِ
 * کاربر با فیلدِ context — همه‌ی وضعیت‌ها + rejection_reason.
 */
class ForumController extends Controller
{
    /**
     * GET /v1/customer/forum/topics — لیستِ گفتگوها (صفحه‌بندی‌شده).
     */
    public function topics(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 15), 50));

        $base = Question::query()->approved()->with('device:id,name,slug');
        if ($deviceId = (int) $request->query('device_id', 0)) {
            $base->where('device_id', $deviceId);
        }
        $total = (clone $base)->count();
        $rows = $base->orderByDesc('published_at')->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $rows->map(fn (Question $q) => $this->shapeTopic($q))->values(),
            'meta' => [
                'page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * GET /v1/customer/forum/topics/{id} — جزئیات + کامنت‌ها.
     * comments = همه‌ی approved ها + pending/rejected های خودِ کاربرِ جاری.
     */
    public function topicShow(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $question = Question::query()->approved()->with('device:id,name,slug')->find($id);
        if (! $question) {
            return response()->json(['success' => false, 'message' => 'گفتگو یافت نشد.', 'code' => 'not_found'], 404);
        }

        // حریمِ خصوصی سمتِ سرور: approved برای همه + غیرِ approvedِ خودِ کاربر.
        $answers = Answer::query()
            ->where('question_id', $question->id)
            ->where(fn ($q) => $q->where('status', Answer::STATUS_APPROVED)
                ->orWhere(fn ($qq) => $qq->where('customer_id', $customer->id)
                    ->whereIn('status', [Answer::STATUS_PENDING, Answer::STATUS_REJECTED])))
            ->orderBy('created_at')
            ->get();

        $comments = $answers->map(fn (Answer $a) => $this->shapeComment($a, $customer))->values();

        return response()->json([
            'data' => array_merge($this->shapeTopic($question), [
                'body' => $question->body,
                'comments' => $comments,
                // سوال‌های بازِ کاربر در همین گفتگو: pendingها یا approvedهای بی‌پاسخِ خودش.
                'my_open_questions' => $comments
                    ->filter(fn ($c) => $c['is_mine'] && in_array($c['status'], ['pending'], true))
                    ->values(),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * POST /v1/customer/forum/topics/{id}/comments — ثبتِ سوال/دیدگاه (pending).
     */
    public function storeComment(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);
        $question = Question::query()->approved()->find($id, ['id']);
        if (! $question) {
            return response()->json(['success' => false, 'message' => 'گفتگو یافت نشد.', 'code' => 'not_found'], 404);
        }
        if (\Modules\Site\Models\Forum\BanlistEntry::isBanned($request->ip(), $customer->email, $customer->mobile)) {
            return response()->json(['success' => false, 'message' => 'دسترسی شما مسدود شده است.', 'code' => 'banned'], 403);
        }

        $data = $request->validate([
            'body' => 'required|string|min:5|max:20000',
            'parent_id' => 'nullable|integer', // فعلاً مدل flat است — نادیده گرفته می‌شود
        ], [
            'body.required' => 'متنِ سوال الزامی است.',
            'body.min' => 'متنِ سوال خیلی کوتاه است.',
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
            'message' => 'سوال شما دریافت شد و پس از تأیید نمایش داده می‌شود.',
            'data' => $this->shapeComment($answer, $customer),
        ], 201);
    }

    /**
     * GET /v1/customer/forum/my-questions — همهٔ محتوای کاربر با هر وضعیتی +
     * context (گفتگوی انجمن یا مقاله). فیلترهای اختیاری: context_type/context_id.
     */
    public function myQuestions(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $ctxType = trim((string) $request->query('context_type', ''));
        $ctxId = (int) $request->query('context_id', 0);

        $items = collect();

        // ۱) سوال‌های انجمنِ خودِ کاربر (topic های خودش)
        if ($ctxType === '' || $ctxType === 'forum_topic') {
            $questions = Question::query()
                ->where(fn ($q) => $q->where('customer_id', $customer->id)
                    ->orWhere(fn ($qq) => $qq->whereNull('customer_id')->where('author_phone', $customer->mobile)))
                ->when($ctxId > 0, fn ($q) => $q->where('id', $ctxId))
                ->orderByDesc('created_at')->limit(200)->get();
            foreach ($questions as $q) {
                $items->push([
                    'id' => (int) $q->id,
                    'kind' => 'forum_question',
                    'body' => (string) ($q->body ?: $q->title),
                    'status' => $this->mapStatus($q->status),
                    'is_answered' => in_array($q->resolution_status, [
                        Question::RES_ANSWERED, Question::RES_EXPERT_ANSWERED, Question::RES_SOLVED,
                    ], true),
                    'is_mine' => true,
                    'rejection_reason' => $q->status === Question::STATUS_REJECTED || $q->status === Question::STATUS_SPAM
                        ? $q->rejection_reason : null,
                    'created_at' => $q->created_at?->toIso8601String(),
                    'context' => ['type' => 'forum_topic', 'id' => (int) $q->id, 'title' => $q->title],
                    '_sort' => $q->created_at?->getTimestamp() ?? 0,
                ]);
            }

            // ۲) دیدگاه‌های کاربر داخلِ گفتگوها
            $answers = Answer::query()
                ->where('is_expert_reply', false)
                ->where(fn ($q) => $q->where('customer_id', $customer->id)
                    ->orWhere(fn ($qq) => $qq->whereNull('customer_id')->where('author_phone', $customer->mobile)))
                ->when($ctxId > 0, fn ($q) => $q->where('question_id', $ctxId))
                ->with('question:id,title')
                ->orderByDesc('created_at')->limit(200)->get();
            foreach ($answers as $a) {
                $items->push([
                    'id' => (int) $a->id,
                    'kind' => 'forum_comment',
                    'body' => (string) $a->body,
                    'status' => $this->mapStatus($a->status),
                    'is_answered' => false,
                    'is_mine' => true,
                    'rejection_reason' => in_array($a->status, [Answer::STATUS_REJECTED, Answer::STATUS_SPAM], true)
                        ? $a->rejection_reason : null,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'context' => ['type' => 'forum_topic', 'id' => (int) $a->question_id, 'title' => $a->question?->title],
                    '_sort' => $a->created_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        // ۳) کامنت‌های مقالاتِ کاربر
        if ($ctxType === '' || $ctxType === 'article') {
            $comments = SiteComment::query()
                ->where('customer_id', $customer->id)
                ->whereNull('parent_id')->where('is_admin_reply', false)
                ->where('commentable_type', \Modules\Site\Models\Article::class)
                ->when($ctxId > 0, fn ($q) => $q->where('commentable_id', $ctxId))
                ->with(['commentable:id,title', 'replies' => fn ($q) => $q->where('is_admin_reply', true)->where('status', SiteComment::STATUS_APPROVED)])
                ->orderByDesc('created_at')->limit(200)->get();
            foreach ($comments as $c) {
                $items->push([
                    'id' => (int) $c->id,
                    'kind' => 'article_comment',
                    'body' => (string) $c->content,
                    'status' => $this->mapStatus($c->status),
                    'is_answered' => $c->replies->isNotEmpty(),
                    'is_mine' => true,
                    'rejection_reason' => in_array($c->status, [SiteComment::STATUS_REJECTED, SiteComment::STATUS_SPAM], true)
                        ? $c->rejection_reason : null,
                    'created_at' => $c->created_at?->toIso8601String(),
                    'context' => ['type' => 'article', 'id' => (int) $c->commentable_id, 'title' => $c->commentable?->title],
                    '_sort' => $c->created_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        $sorted = $items->sortByDesc('_sort')->values()->map(function ($i) {
            unset($i['_sort']);

            return $i;
        });
        $total = $sorted->count();
        $pageItems = $sorted->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $pageItems,
            'meta' => [
                'page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeTopic(Question $q): array
    {
        return [
            'id' => (int) $q->id,
            'title' => $q->title,
            'excerpt' => Str::limit(trim(strip_tags((string) $q->body)), 140),
            'comments_count' => (int) $q->answers_count, // فقط approved ها (recomputeResolution)
            'is_answered' => in_array($q->resolution_status, [
                Question::RES_ANSWERED, Question::RES_EXPERT_ANSWERED, Question::RES_SOLVED,
            ], true),
            'last_activity_at' => ($q->updated_at ?? $q->created_at)?->toIso8601String(),
            'created_at' => $q->created_at?->toIso8601String(),
            'device' => $q->device ? [
                'id' => (int) $q->device->id, 'name' => $q->device->name, 'slug' => $q->device->slug,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeComment(Answer $a, Customer $customer): array
    {
        $isMine = ((int) $a->customer_id === (int) $customer->id)
            || ($a->customer_id === null && ! empty($a->author_phone) && $a->author_phone === $customer->mobile);

        return [
            'id' => (int) $a->id,
            'body' => (string) $a->body,
            'status' => $this->mapStatus($a->status),
            'is_answered' => false, // مدل flat — پاسخِ staff خودش یک کامنت با is_staff=true است
            'is_mine' => $isMine,
            'rejection_reason' => $isMine && in_array($a->status, [Answer::STATUS_REJECTED, Answer::STATUS_SPAM], true)
                ? $a->rejection_reason : null,
            'author' => [
                'id' => (int) ($a->customer_id ?? 0),
                'name' => (string) $a->author_name,
                'is_staff' => (bool) $a->is_expert_reply,
            ],
            'created_at' => $a->created_at?->toIso8601String(),
            'replies' => [],
        ];
    }

    /** spam برای کاربر همان rejected است. */
    private function mapStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'approved',
            'rejected', 'spam' => 'rejected',
            default => 'pending',
        };
    }
}
