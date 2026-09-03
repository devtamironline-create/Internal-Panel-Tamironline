<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderReview;
use Modules\CRM\Models\ReviewTag;
use Modules\CustomerApp\Http\Requests\SubmitReviewRequest;
use Modules\CustomerApp\Http\Resources\ReviewResource;

/**
 * Customer-facing Reviews API.
 *
 *   GET  /v1/customer/orders/pending-reviews
 *   POST /v1/customer/orders/{id}/review
 */
class ReviewController extends Controller
{
    /**
     * GET /v1/customer/orders/pending-reviews
     *
     * لیست سفارش‌های completed این مشتری که هنوز نظر ثبت نکرده‌اند.
     * فرانت بر اساس این لیست مودال اجباری نظرسنجی را نمایش می‌دهد.
     */
    public function pending(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $query = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Completed->value)
            ->whereDoesntHave('review');

        // فقط سفارش‌های اخیر نظرسنجیِ اجباری دارند (سفارش‌های خیلی قدیمی
        // نباید کاربر را مجبور به ثبت نظر کنند).
        $windowDays = (int) config('customerapp.reviews.pending_window_days', 30);
        if ($windowDays > 0) {
            $query->where('completed_at', '>=', now()->subDays($windowDays));
        }

        $rows = $query
            ->with(['device:id,name', 'brand:id,name', 'technician:id,first_name,last_name,firstname_tech'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'order_code', 'device_id', 'brand_id', 'technician_id', 'completed_at']);

        $data = $rows->map(fn (Order $o) => [
            'order_id' => (int) $o->id,
            'tracking_code' => $o->order_code,
            'completed_at' => $o->completed_at?->utc()->toIso8601String(),
            'device_name' => $o->device?->name,
            'brand_name' => $o->brand?->name,
            'technician_name' => $o->technician?->display_name,
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => ['total' => $data->count()],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * POST /v1/customer/orders/{id}/review
     *
     * - فقط مالک سفارش (403 در غیر این صورت)
     * - فقط روی سفارش completed (422 cannot_review otherwise)
     * - یک نظر در ازای هر سفارش (409 already_reviewed → همان نظر برمی‌گردد)
     */
    public function store(SubmitReviewRequest $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);

        $order = Order::query()->where('id', $id)->first();
        if (! $order) {
            abort(404, 'سفارش یافت نشد.');
        }
        if ((int) $order->customer_id !== (int) $customer->id) {
            abort(403, 'این سفارش به حساب شما تعلق ندارد.');
        }

        if ($order->status !== OrderStatus::Completed) {
            return response()->json([
                'message' => 'فقط برای سفارش‌های انجام‌شده می‌توان نظر ثبت کرد.',
                'code' => 'order_not_completed',
            ], 422);
        }

        // اگر قبلاً ثبت شده → نظر قبلی را با تگ‌ها برگردان (نه error)
        $existing = OrderReview::query()->where('order_id', $order->id)->with('tags')->first();
        if ($existing) {
            return response()->json([
                'message' => 'نظر قبلاً ثبت شده است.',
                'code' => 'already_reviewed',
                'data' => (new ReviewResource($existing))->resolve(),
            ], 409);
        }

        $data = $request->validated();
        $tagIds = $request->activeTagIds();

        $review = DB::transaction(function () use ($order, $customer, $data, $tagIds, $request) {
            $r = OrderReview::create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'technician_id' => $order->technician_id,
                'rating' => (int) $data['rating'],
                'criteria' => $this->sanitizeCriteria($data['criteria'] ?? null),
                'comment' => $data['comment'] ?? null,
                'would_recommend' => array_key_exists('would_recommend', $data) ? (bool) $data['would_recommend'] : null,
                // فعلاً نظرها به‌صورتِ خودکار «تأییدشده» ثبت می‌شوند (تصمیمِ
                // کارفرما)؛ مدیریتِ دستیِ ادمین همچنان از صفحهٔ نظرها ممکن است.
                'status' => OrderReview::STATUS_APPROVED,
                'moderated_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            if (! empty($tagIds)) {
                $r->tags()->sync($tagIds);
            }

            return $r;
        });

        // چون خودکار تأیید شد، میانگینِ satisfaction_score تکنسین باید فوراً
        // به‌روز شود (approvedها در میانگین می‌آیند).
        if ($review->technician_id) {
            \Modules\CRM\Services\TechnicianRatingService::recompute((int) $review->technician_id);
        }

        $review->load('tags');

        return response()->json([
            'message' => 'نظر شما ثبت شد. ممنون از همکاری.',
            'data' => (new ReviewResource($review))->resolve(),
        ], 201);
    }

    /**
     * GET /v1/customer/reviews/tags
     *
     * فهرست تگ‌های فعال نقاط قوت/ضعف، گروه‌بندی شده بر اساس type.
     * عمومی است (auth لازم نیست) — public endpoint برای splash/cache فرانت.
     * Cache 5 دقیقه‌ای روی CDN/proxy مجاز است.
     */
    public function tags(Request $request): JsonResponse
    {
        $rows = ReviewTag::query()->active()->ordered()->get(['id', 'slug', 'label', 'type', 'icon']);

        $shape = fn (ReviewTag $t) => [
            'id' => (int) $t->id,
            'slug' => $t->slug,
            'label' => $t->label,
            'icon' => $t->icon,
        ];

        return response()->json([
            'data' => [
                'pros' => $rows->where('type', ReviewTag::TYPE_PRO)->values()->map($shape)->all(),
                'cons' => $rows->where('type', ReviewTag::TYPE_CON)->values()->map($shape)->all(),
            ],
            'meta' => [
                'total_pros' => $rows->where('type', ReviewTag::TYPE_PRO)->count(),
                'total_cons' => $rows->where('type', ReviewTag::TYPE_CON)->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, int>|null
     */
    private function sanitizeCriteria($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach (OrderReview::CRITERIA_KEYS as $k) {
            if (isset($raw[$k]) && is_numeric($raw[$k])) {
                $v = (int) $raw[$k];
                if ($v >= 1 && $v <= 5) {
                    $out[$k] = $v;
                }
            }
        }

        return $out ?: null;
    }
}
